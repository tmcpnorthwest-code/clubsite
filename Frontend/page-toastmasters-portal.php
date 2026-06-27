<?php
/**
 * Template Name: Toastmasters Member Portal
 * Template Post Type: page
 * Description: Home page for Toastmasters Club of Pune North West.
 *
 * Copy this file into your active child theme folder.
 * Rename styles.css → toastmasters-portal.css and app.js → toastmasters-portal.js
 * in your child theme's assets/ folder.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ── Prevent page-cache plugins from caching this page — all sections are dynamic
define('DONOTCACHEPAGE', true);
nocache_headers();

// ── YouTube channel handle (the part after @)
define('TMC_YT_CHANNEL', 'tmcpnorthwest567');

// ── OPTIONAL: hard-code your UC... channel ID to skip the page scrape.
// Find it: YouTube Studio → Settings → Channel → Basic info → Channel ID
// Leave blank to let the plugin resolve it automatically.
define('TMC_YT_CHANNEL_ID_OVERRIDE', 'UCC-9rVEwrTuDV4oQpjTyybw');

$template_dir = get_stylesheet_directory_uri();

wp_enqueue_style('toastmasters-portal', $template_dir . '/assets/toastmasters-portal.css', [], '2.1.2');
wp_enqueue_script('toastmasters-portal', $template_dir . '/assets/toastmasters-portal.js', [], '2.1.2', true);
wp_localize_script('toastmasters-portal', 'TMCPublic', [
    'restUrl' => esc_url_raw(rest_url('toastmasters/v1')),
]);

// ── Live data from plugin ─────────────────────────────────────────────────────
$next_meeting      = null;
$active_count      = 0;
$level_dist        = [];
$level_ups         = [];
$meeting_summary   = null;
$diversity_leaders = [];

if (class_exists('TMP_Repository')) {
    $all_members = TMP_Repository::members();

    $active_members = array_filter($all_members, fn($m) => !empty($m['is_eligible']));
    $active_count   = count($active_members);

    foreach ($active_members as $m) {
        $l = max(0, min(5, (int) ($m['level_completed'] ?? 0)));
        $level_dist[$l] = ($level_dist[$l] ?? 0) + 1;
    }
    ksort($level_dist);

    $today = gmdate('Y-m-d');
    foreach (TMP_Repository::meetings() as $mtg) {
        if ($mtg['meeting_date'] >= $today) {
            if (!$next_meeting || $mtg['meeting_date'] < $next_meeting['meeting_date']) {
                $next_meeting = $mtg;
            }
        }
    }

    if (method_exists('TMP_Repository', 'get_recent_level_ups')) {
        $level_ups = TMP_Repository::get_recent_level_ups(12);
    }

    $spotlight = method_exists('TMP_Repository', 'get_new_member_spotlight')
        ? TMP_Repository::get_new_member_spotlight()
        : null;
    if (method_exists('TMP_Repository', 'get_meeting_summary')) {
        $meeting_summary = TMP_Repository::get_meeting_summary();
    }
    if (method_exists('TMP_Repository', 'get_role_diversity_leaders')) {
        $diversity_leaders = TMP_Repository::get_role_diversity_leaders(5);
    }
}

// Voting: find today's meeting (if any) and its nominees
$today_meeting    = null;
$voting_nominees  = null;
if (class_exists('TMP_Repository')) {
    $today_str = gmdate('Y-m-d');
    foreach (TMP_Repository::meetings() as $mtg) {
        if ($mtg['meeting_date'] === $today_str) {
            $today_meeting = $mtg;
            break;
        }
    }
    if ($today_meeting && method_exists('TMP_Repository', 'get_vote_nominees')) {
        $voting_nominees = TMP_Repository::get_vote_nominees($today_meeting['id']);
    }
}

// ── Latest YouTube video (channel ID resolved once, video cached 6 hours) ────
function tmc_latest_youtube_video_id($handle) {
    if (!$handle) return null;

    // Allow hard-coded channel ID to skip page scrape entirely
    $channel_id = TMC_YT_CHANNEL_ID_OVERRIDE ?: null;

    if (!$channel_id) {
        $cid_key    = 'tmc_yt_cid_' . md5($handle);
        $channel_id = get_transient($cid_key);

        if (!$channel_id) {
            // Avoid hammering YouTube on every page load when it keeps failing
            $fail_key = 'tmc_yt_fail_' . md5($handle);
            if (get_transient($fail_key)) return null;

            $resp = wp_remote_get(
                'https://www.youtube.com/@' . ltrim($handle, '@'),
                [
                    'timeout'    => 10,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'headers'    => ['Accept-Language' => 'en-US,en;q=0.9'],
                ]
            );

            if (!is_wp_error($resp)) {
                $body = wp_remote_retrieve_body($resp);
                // Try several patterns — YouTube changes their page structure regularly
                foreach ([
                    '/"channelId":"(UC[\w-]+)"/',
                    '/"externalId":"(UC[\w-]+)"/',
                    '/youtube\.com\/channel\/(UC[\w-]+)/',
                    '/"ucid":"(UC[\w-]+)"/',
                ] as $pat) {
                    if (preg_match($pat, $body, $m)) {
                        $channel_id = $m[1];
                        break;
                    }
                }
            }

            if ($channel_id) {
                set_transient($cid_key, $channel_id, 30 * DAY_IN_SECONDS);
            } else {
                set_transient($fail_key, '1', 5 * MINUTE_IN_SECONDS);
                return null;
            }
        }
    }

    // Fetch latest video ID from RSS (cached 6 hours)
    $vid_key  = 'tmc_yt_vid_' . md5($channel_id);
    $video_id = get_transient($vid_key);
    if ($video_id) return $video_id;

    $feed     = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channel_id);
    $response = wp_remote_get($feed, ['timeout' => 5]);
    if (is_wp_error($response)) return null;

    $body = wp_remote_retrieve_body($response);
    if (!$body) return null;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    if (!$xml) return null;

    $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
    $ids = $xml->xpath('//yt:videoId');
    if (empty($ids)) return null;

    $video_id = (string) $ids[0];
    set_transient($vid_key, $video_id, 6 * HOUR_IN_SECONDS);
    return $video_id;
}

$yt_video_id = tmc_latest_youtube_video_id(TMC_YT_CHANNEL);

// ── Helpers ───────────────────────────────────────────────────────────────────
$level_colors = [
    0 => '#90a4ae',
    1 => '#0f766e',
    2 => '#18324a',
    3 => '#8f1737',
    4 => '#d69b24',
    5 => '#5c2d91',
];

function tmc_relative_date($date_str) {
    $ts   = strtotime((string) $date_str);
    $diff = current_time('timestamp') - $ts;
    $days = (int) floor($diff / 86400);
    if ($days === 0) return 'Today';
    if ($days === 1) return 'Yesterday';
    if ($days < 30)  return "{$days} days ago";
    if ($days < 365) return (int) floor($days / 30) . ' months ago';
    return date('M Y', $ts);
}

$days_until = null;
if ($next_meeting) {
    $days_until = (int) floor((strtotime($next_meeting['meeting_date']) - strtotime(gmdate('Y-m-d'))) / 86400);
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Toastmasters Club of Pune North West</title>
<?php wp_head(); ?>
</head>
<body>
<?php wp_body_open(); ?>

<main class="tmc-home">

  <!-- ══════════════════════════════════════════════════════════════ NAV -->
  <nav class="topbar">
    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
      <img class="brand-torch" src="<?php echo esc_url($template_dir . '/assets/logo.png'); ?>" alt="Toastmasters International" />
      <strong>Toastmasters Club of Pune North West</strong>
    </a>
    <div class="nav">
      <a href="#tmc-gallery">Gallery</a>
      <a href="#tmc-pathways">Pathways</a>
      <a href="#tmc-membership">Join</a>
      <a href="<?php echo esc_url(home_url('/member-dashboard/')); ?>" class="button primary" style="padding:8px 18px;font-size:0.88rem;">Member Login</a>
    </div>
    <button class="icon-button menu-button" data-menu-toggle aria-label="Open menu">
      <span></span><span></span><span></span>
    </button>
  </nav>

  <!-- ══════════════════════════════════════════════════════════ HERO -->
  <section class="hero" id="tmc-top">
    <img
      class="hero-img-link"
      src="<?php echo esc_url($template_dir . '/assets/hero-photo.jpeg'); ?>"
      alt="Toastmasters Club of Pune North West members at a meeting"
      loading="eager"
    />
    <?php if ($next_meeting) :
      $nm_dt = new DateTime($next_meeting['meeting_date']); ?>
      <div class="hero-meeting-chip">
        <span class="hero-chip-label">Next Meeting</span>
        <strong><?php echo esc_html($nm_dt->format('l, F j')); ?></strong>
        <?php if ($days_until !== null && $days_until >= 0) : ?>
          <span class="hero-chip-countdown">
            <?php echo $days_until === 0 ? 'Today!' : "in {$days_until} day" . ($days_until !== 1 ? 's' : ''); ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($next_meeting['theme'])) : ?>
          &middot; <em><?php echo esc_html($next_meeting['theme']); ?></em>
        <?php endif; ?>
        <?php if (!empty($next_meeting['is_published'])) : ?>
          &middot; <a class="hero-chip-agenda" href="#tmc-upcoming">View Agenda ↓</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ══════════════════════════════════════════════════════ STATS BAND -->
  <div class="stats-band">
    <?php if ($active_count) : ?>
      <div class="stat-block">
        <strong><?php echo esc_html($active_count); ?></strong>
        <span>Active Members</span>
      </div>
    <?php endif; ?>

    <?php if ($level_dist) : ?>
      <div class="stat-block stat-block--levels">
        <span class="stat-eyebrow">Members by Level</span>
        <div class="level-pills">
          <?php foreach ($level_dist as $lvl => $cnt) : ?>
            <span class="level-pill" style="background:<?php echo esc_attr($level_colors[$lvl] ?? '#999'); ?>">
              L<?php echo (int) $lvl; ?> <strong><?php echo (int) $cnt; ?></strong>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($next_meeting) :
      $sb_dt = new DateTime($next_meeting['meeting_date']); ?>
      <div class="stat-block">
        <strong><?php echo esc_html($sb_dt->format('d M')); ?></strong>
        <span>Next Meeting<?php if ($days_until !== null) echo ' &middot; ' . ($days_until === 0 ? 'Today' : $days_until . 'd away'); ?></span>
      </div>
    <?php endif; ?>

    <?php
    $pathway_count = count(array_unique(array_filter(array_column($active_members, 'pathway'), function($p) {
        return $p && $p !== 'No pathway registered' && $p !== 'Enrolled';
    })));
    if ($pathway_count) : ?>
      <div class="stat-block">
        <strong><?php echo esc_html($pathway_count); ?></strong>
        <span>Pathways</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- ═══════════════════════════════════════════ UPCOMING MEETING AGENDA -->
  <!-- Rendered by JS on page load from REST API — never server-cached -->
  <section class="section upcoming-agenda-section" id="tmc-upcoming" style="display:none" aria-live="polite"></section>

  <!-- ═══════════════════════════════════════════════════════ VOTE NOW -->
  <?php if ($today_meeting && !empty($today_meeting['poll_open'])) : ?>
  <section class="section vote-section" id="tmc-vote"
           data-tmc-vote-meeting="<?php echo (int) $today_meeting['id']; ?>"
           data-tmc-rest="<?php echo esc_url(rest_url('toastmasters/v1')); ?>">
    <p class="eyebrow">Meeting Day</p>
    <h2>Vote for Today&rsquo;s Best Performers</h2>
    <p class="section-sub">
      <?php
        $vd = new DateTime($today_meeting['meeting_date']);
        echo esc_html($vd->format('l, F j'));
        if (!empty($today_meeting['theme'])) echo ' &mdash; <em>' . esc_html($today_meeting['theme']) . '</em>';
      ?>
    </p>

    <?php
    $cat_labels = [
        'main_role'    => ['label' => 'Best Main Role',             'desc' => 'TMOD · Table Topics Master · General Evaluator'],
        'aux_role'     => ['label' => 'Best Auxiliary Role',         'desc' => 'SAA · Timer · Ah-Counter · Grammarian · Active Listener'],
        'table_topics' => ['label' => 'Best Table Topics Speaker',   'desc' => 'Added by VPE during the session'],
        'speaker'      => ['label' => 'Best Speaker',                'desc' => 'Prepared speech presenters of the day'],
        'evaluator'    => ['label' => 'Best Evaluator',              'desc' => 'Speech evaluators of the day'],
    ];
    $winners_declared = !empty($today_meeting['winners_declared']);
    ?>

    <?php if ($winners_declared) : ?>
    <div class="vote-winners-banner">
      <span>&#127942;</span> Winners have been declared — see results below!
    </div>
    <?php endif; ?>

    <div class="vote-grid" data-tmc-vote-grid>
      <?php foreach ($cat_labels as $cat => $meta) :
        $nominees_in_cat = $voting_nominees[$cat] ?? [];
      ?>
      <div class="vote-card" data-vote-cat="<?php echo esc_attr($cat); ?>">
        <p class="eyebrow"><?php echo esc_html($meta['label']); ?></p>
        <p class="vote-card__desc"><?php echo esc_html($meta['desc']); ?></p>

        <?php if (empty($nominees_in_cat)) : ?>
          <p class="vote-empty" data-vote-empty>
            <?php echo $cat === 'table_topics' ? 'Speakers will appear here once VPE adds them.' : 'Nominees will appear once roles are confirmed.'; ?>
          </p>
        <?php else : ?>
          <ul class="vote-nominee-list" data-vote-list>
            <?php foreach ($nominees_in_cat as $nom) :
              $is_winner = $winners_declared && !empty($nom['is_winner']);
            ?>
              <li class="vote-nominee<?php echo $is_winner ? ' vote-nominee--winner' : ''; ?>" data-nominee-id="<?php echo (int) $nom['id']; ?>">
                <label class="vote-option<?php echo $is_winner ? ' vote-option--winner' : ''; ?>">
                  <input type="radio" name="vote_<?php echo esc_attr($cat); ?>" value="<?php echo (int) $nom['id']; ?>" />
                  <span class="vote-name"><?php echo $is_winner ? '🏆 ' : ''; echo esc_html($nom['display_name']); ?></span>
                  <span class="vote-role"><?php echo esc_html($nom['role_name']); ?></span>
                </label>
                <span class="vote-count" data-vote-count="<?php echo (int) $nom['id']; ?>" style="<?php echo $is_winner ? '' : 'display:none;'; ?>"><?php echo (int) $nom['vote_count']; ?> vote<?php echo (int) $nom['vote_count'] !== 1 ? 's' : ''; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="vote-action">
      <button class="vote-submit-btn" id="tmc-vote-submit">Cast My Vote</button>
      <p class="vote-status" data-vote-all-status role="status"></p>
    </div>
    <p class="vote-footer-note">Pick your favourite from each category above, then cast your vote.</p>
  </section>
  <?php endif; ?>

  <!-- ════════════════════════════════════════════════ RECOGNITION WALL -->
  <?php if (!empty($level_ups)) : ?>
  <section class="section recognition-section" id="tmc-recognition">
    <p class="eyebrow">Member Recognition</p>
    <h2>Recent Level-Ups</h2>
    <p class="section-sub">Celebrating members who advanced their Pathways journey.</p>
    <div class="recognition-grid">
      <?php foreach ($level_ups as $lu) : ?>
        <div class="recognition-card">
          <div class="recognition-badges">
            <span class="level-badge" style="background:<?php echo esc_attr($level_colors[(int)$lu['old_level']] ?? '#999'); ?>">L<?php echo (int) $lu['old_level']; ?></span>
            <span class="recog-arrow">&#8594;</span>
            <span class="level-badge level-badge--new" style="background:<?php echo esc_attr($level_colors[(int)$lu['new_level']] ?? '#999'); ?>">L<?php echo (int) $lu['new_level']; ?></span>
          </div>
          <strong class="recog-name"><?php echo esc_html($lu['member_name']); ?></strong>
          <small class="recog-pathway"><?php echo esc_html($lu['pathway']); ?></small>
          <span class="recog-date"><?php echo esc_html(tmc_relative_date($lu['leveled_up_at'])); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════ MEETING PULSE -->
  <?php if ($meeting_summary) :
    $dist       = $meeting_summary['level_distribution'] ?? [];
    $dist_total = array_sum($dist);
    $pulse_dt   = new DateTime($meeting_summary['meeting_date']);
  ?>
  <section class="section meeting-pulse-section" data-tmc-pulse data-tmc-pulse-meeting-id="<?php echo (int) ($meeting_summary['meeting_id'] ?? 0); ?>">
    <div class="pulse-header">
      <div>
        <p class="eyebrow">Last Meeting</p>
        <h2>Meeting Pulse</h2>
      </div>
      <div class="pulse-meeting-meta">
        <strong><?php echo esc_html($pulse_dt->format('l, F j')); ?></strong>
        <?php if (!empty($meeting_summary['theme'])) : ?>
          <span class="pulse-theme">&ldquo;<?php echo esc_html($meeting_summary['theme']); ?>&rdquo;</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="pulse-grid">

      <div class="pulse-card">
        <p class="eyebrow">Attendance</p>
        <div class="pulse-big-stat" data-tmc-pulse-attendance>
          <?php
            $att_count = (int) ($meeting_summary['attendance_count'] ?? $meeting_summary['participants'] ?? 0);
            echo $att_count;
          ?>
          <span>members</span>
        </div>
        <?php $guest_count = (int) ($meeting_summary['guest_count'] ?? 0); ?>
        <p class="pulse-guests-line" data-tmc-pulse-guests style="font-size:0.82rem;color:var(--c-muted, #888);margin-top:4px;">
          <?php echo $guest_count ? $guest_count . ' guest' . ($guest_count !== 1 ? 's' : '') : 'No guests'; ?>
        </p>

        <?php if ($dist_total) : ?>
          <p class="eyebrow" style="margin-top:20px;margin-bottom:8px;">Club Level Distribution</p>
          <div class="dist-bar">
            <?php foreach ($dist as $lvl => $cnt) :
              if (!$cnt) continue;
              $pct = round(($cnt / $dist_total) * 100, 1);
            ?>
              <div class="dist-segment"
                   style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr($level_colors[$lvl] ?? '#ccc'); ?>;"
                   title="L<?php echo (int)$lvl; ?>: <?php echo (int)$cnt; ?> member<?php echo $cnt !== 1 ? 's' : ''; ?>">
                <span>L<?php echo (int)$lvl; ?> <?php echo (int)$cnt; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="dist-legend">
            <?php foreach ($dist as $lvl => $cnt) : if (!$cnt) continue; ?>
              <span style="color:<?php echo esc_attr($level_colors[$lvl] ?? '#999'); ?>">
                &#9632; L<?php echo (int)$lvl; ?>: <?php echo (int)$cnt; ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="pulse-card">
        <p class="eyebrow">Roles Covered</p>
        <div class="pulse-roles" data-tmc-pulse-roles>
          <?php if (!empty($meeting_summary['roles_covered'])) :
            foreach ($meeting_summary['roles_covered'] as $role) : ?>
              <span class="role-tag"><?php echo esc_html($role); ?></span>
            <?php endforeach;
          else : ?>
            <p style="color:#999;font-size:0.88rem;">No roles recorded yet.</p>
          <?php endif; ?>
        </div>

        <?php if (!empty($meeting_summary['level_ups'])) : ?>
          <p class="eyebrow" style="margin-top:20px;">Level-Ups This Meeting</p>
          <div class="pulse-levelups">
            <?php foreach ($meeting_summary['level_ups'] as $lu) : ?>
              <div class="pulse-lu-row">
                <strong><?php echo esc_html($lu['member_name']); ?></strong>
                <span class="level-badge" style="background:<?php echo esc_attr($level_colors[(int)$lu['old_level']] ?? '#999'); ?>">L<?php echo (int)$lu['old_level']; ?></span>
                <span class="pulse-lu-arrow">&#8594;</span>
                <span class="level-badge" style="background:<?php echo esc_attr($level_colors[(int)$lu['new_level']] ?? '#999'); ?>">L<?php echo (int)$lu['new_level']; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php
        $pulse_winners = $meeting_summary['winners'] ?? [];
        $pw_labels = [
          'main_role'   => 'Best Main Role',
          'aux_role'    => 'Best Auxiliary Role',
          'table_topics'=> 'Best Table Topics',
          'speaker'     => 'Best Speaker',
          'evaluator'   => 'Best Evaluator',
        ];
      ?>
      <div class="pulse-card pulse-card--winners" data-tmc-pulse-winners>
        <p class="eyebrow">&#127942; Meeting Winners</p>
        <?php if (!empty($pulse_winners)) : ?>
          <ul class="pulse-winners-list">
            <?php foreach ($pulse_winners as $w) : ?>
              <li class="pulse-winner-row">
                <span class="pulse-winner-cat"><?php echo esc_html($pw_labels[$w['category']] ?? $w['category']); ?></span>
                <span class="pulse-winner-name"><?php echo esc_html($w['display_name']); ?></span>
                <?php if (!empty($w['role_name'])) : ?>
                  <span class="pulse-winner-role"><?php echo esc_html($w['role_name']); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p style="color:#999;font-size:0.88rem;margin-top:8px;">Winners will appear after the meeting wrap-up.</p>
        <?php endif; ?>
      </div>

    </div>
  </section>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════ GALLERY -->
  <section class="section gallery-section" id="tmc-gallery">
    <p class="eyebrow">Club Life</p>
    <h2>Our Meetings in Action</h2>
    <p class="section-sub">Every week, members step up, speak out, and grow together.</p>

    <div class="gallery-grid">
      <div class="gallery-item gallery-item--large">
        <img src="<?php echo esc_url($template_dir . '/assets/hero-photo.jpeg'); ?>" alt="Club meeting in session" loading="lazy" />
      </div>
      <div class="gallery-item">
        <img src="<?php echo esc_url($template_dir . '/assets/pic 01.jpeg'); ?>" alt="Toastmasters Club of Pune North West" loading="lazy" />
      </div>
      <div class="gallery-item">
        <img src="<?php echo esc_url($template_dir . '/assets/pic 02.jpeg'); ?>" alt="Toastmasters Club of Pune North West" loading="lazy" />
      </div>
      <div class="gallery-item">
        <img src="<?php echo esc_url($template_dir . '/assets/pic 03.jpeg'); ?>" alt="Toastmasters Club of Pune North West" loading="lazy" />
      </div>
      <div class="gallery-item">
        <img src="<?php echo esc_url($template_dir . '/assets/pic 04.jpeg'); ?>" alt="Toastmasters Club of Pune North West" loading="lazy" />
      </div>

      <?php if ($yt_video_id) : ?>
      <div class="gallery-item gallery-video">
        <iframe
          src="<?php echo esc_url('https://www.youtube.com/embed/' . $yt_video_id . '?rel=0'); ?>"
          title="Latest video — Toastmasters Club of Pune North West"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
          loading="lazy"
        ></iframe>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ══════════════════════════════════════════ NEW MEMBER SPOTLIGHT -->
  <?php if ($spotlight) :
    $sp        = $spotlight['member'];
    $sp_joined = !empty($sp['created_at'])
        ? (new DateTime($sp['created_at']))->format('F Y') : null;
    $level_labels = ['Level 0 (Enrolled)', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'Level 5'];
    $sp_level  = $level_labels[(int) ($sp['level_completed'] ?? 0)] ?? 'Level 0 (Enrolled)';
  ?>
  <section class="section spotlight-section" id="tmc-spotlight">
    <p class="eyebrow">Welcome to the Club</p>
    <h2>Meet Our Newest Member</h2>
    <div class="spotlight-card">
      <?php if (!empty($spotlight['photo_url'])) : ?>
      <div class="spotlight-photo-wrap">
        <img class="spotlight-photo"
             src="<?php echo esc_url($spotlight['photo_url']); ?>"
             alt="<?php echo esc_attr($sp['full_name']); ?>"
             loading="lazy">
      </div>
      <?php endif; ?>
      <div class="spotlight-content">
        <h3 class="spotlight-name"><?php echo esc_html($sp['full_name']); ?></h3>
        <div class="spotlight-meta">
          <span class="spotlight-badge"><?php echo esc_html($sp_level); ?></span>
          <span class="spotlight-pathway"><?php echo esc_html($sp['pathway']); ?></span>
          <?php if ($sp_joined) : ?>
          <span class="spotlight-joined">Joined <?php echo esc_html($sp_joined); ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($spotlight['blurb'])) : ?>
        <p class="spotlight-blurb">&ldquo;<?php echo esc_html($spotlight['blurb']); ?>&rdquo;</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════ ROLE DIVERSITY LEADERS -->
  <?php if (!empty($diversity_leaders)) : ?>
  <section class="section diversity-section">
    <p class="eyebrow">Breadth Award</p>
    <h2>Role Diversity Leaders</h2>
    <p class="section-sub">Members who have taken on the widest range of meeting roles.</p>
    <ol class="diversity-list">
      <?php foreach ($diversity_leaders as $i => $m) : ?>
        <li class="diversity-row">
          <span class="diversity-rank"><?php echo ($i + 1); ?></span>
          <div class="diversity-info">
            <strong><?php echo esc_html($m['full_name']); ?></strong>
            <small><?php echo esc_html($m['pathway']); ?></small>
          </div>
          <span class="level-badge" style="background:<?php echo esc_attr($level_colors[(int)($m['level_completed'] ?? 0)] ?? '#999'); ?>">L<?php echo (int) ($m['level_completed'] ?? 0); ?></span>
          <div class="diversity-tally">
            <strong><?php echo (int) $m['distinct_roles']; ?></strong>
            <small>roles</small>
          </div>
          <?php if (!empty($m['roles_played'])) : ?>
            <small class="diversity-role-list"><?php echo esc_html($m['roles_played']); ?></small>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════ PATHWAYS -->
  <section class="section split" id="tmc-pathways">
    <div>
      <p class="eyebrow">Education Progress</p>
      <h2>Track your Pathways journey</h2>
      <p>Members view assigned paths, completed levels, mentor notes, and the next recommended project before each meeting. Officers see who needs a role, evaluation, or level approval.</p>
      <a class="button tertiary" href="https://www.toastmasters.org/pathways-overview" target="_blank" rel="noopener" style="margin-top:12px;display:inline-flex;">Explore all Pathways &rarr;</a>
    </div>
    <div class="path-grid">
      <article><strong>Dynamic Leadership</strong><span>Influence, change, and team leadership.</span></article>
      <article><strong>Engaging Humor</strong><span>Story craft, timing, and audience connection.</span></article>
      <article><strong>Motivational Strategies</strong><span>Coach, inspire, and move people to action.</span></article>
      <article><strong>Presentation Mastery</strong><span>Structure, delivery, and memorable speeches.</span></article>
      <article><strong>Persuasive Influence</strong><span>Negotiate, persuade, and build consensus.</span></article>
      <article><strong>Visionary Communication</strong><span>Communicate plans and lead with vision.</span></article>
    </div>
  </section>

  <!-- ══════════════════════════════════════════════ JOIN / WHATSAPP -->
  <section class="section enrolment" id="tmc-membership">
    <div>
      <p class="eyebrow">New Members</p>
      <h2>Join Toastmasters Club of<br>Pune North West</h2>
      <p>Visit us as a guest first — no commitment required. We meet every week and welcome curious minds looking to grow their speaking and leadership skills.</p>
      <ul class="join-perks">
        <li>Weekly structured meeting practice</li>
        <li>Dedicated mentor for your first year</li>
        <li>Internationally recognised Pathways credentials</li>
        <li>Leadership roles and club officer opportunities</li>
      </ul>
    </div>
    <div class="ti-brand-panel">
      <img class="ti-torch" src="<?php echo esc_url($template_dir . '/assets/logo.png'); ?>" alt="Toastmasters International" />
      <p class="ti-tagline">Where Leaders Are Made</p>
      <a class="wa-cta" href="https://chat.whatsapp.com/H3eiLPexTTbCPrbKqsvBvQ" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        Join our WhatsApp guest group
      </a>
    </div>
  </section>

</main>

<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <strong>Toastmasters Club of Pune North West</strong>
        <p>Affiliated with Toastmasters International &middot; District 125</p>
      </div>
      <div class="footer-social">
        <a href="https://www.facebook.com/tmcpnorthwest/" class="social-link social-link--fb" target="_blank" rel="noopener" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </a>
        <a href="https://www.instagram.com/tmcpnw" class="social-link social-link--ig" target="_blank" rel="noopener" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
        </a>
        <a href="https://www.linkedin.com/in/tmcpnw/" class="social-link social-link--li" target="_blank" rel="noopener" aria-label="LinkedIn">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
        </a>
        <a href="https://chat.whatsapp.com/H3eiLPexTTbCPrbKqsvBvQ" class="social-link social-link--wa" target="_blank" rel="noopener" aria-label="WhatsApp guest group">
          <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
        </a>
      </div>
    </div>
    <nav class="footer-links">
      <a href="#tmc-top">Home</a>
      <a href="#tmc-gallery">Gallery</a>
      <a href="#tmc-pathways">Pathways</a>
      <a href="<?php echo esc_url(home_url('/member-dashboard/')); ?>">Member Login</a>
      <a href="#tmc-membership">Join</a>
    </nav>
    <p class="footer-copy">&copy; <?php echo date('Y'); ?> Toastmasters Club of Pune North West. All rights reserved.</p>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
