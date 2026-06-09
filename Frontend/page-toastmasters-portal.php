<?php
/**
 * Template Name: Toastmasters Member Portal
 * Template Post Type: page
 * Description: Custom Toastmasters club portal page with member dashboard and enrolment form.
 *
 * Copy this file into your active child theme folder.
 */

if (!defined('ABSPATH')) {
    exit;
}

$template_dir = get_stylesheet_directory_uri();

wp_enqueue_style(
    'toastmasters-portal',
    $template_dir . '/assets/toastmasters-portal.css',
    array(),
    '1.0.0'
);

wp_enqueue_script(
    'toastmasters-portal',
    $template_dir . '/assets/toastmasters-portal.js',
    array(),
    '1.0.0',
    true
);

$next_meeting = null;
$active_count = 0;
$pathway_count = 0;

if (class_exists('TMP_Repository')) {
    $members = TMP_Repository::members();
    $active_count = count(array_filter($members, function ($m) {
        return $m['state'] === 'Active';
    }));

    $pathways = array_unique(array_column($members, 'pathway'));
    $pathway_count = count(array_filter($pathways, function($p) {
        return $p && $p !== 'No pathway registered';
    }));

    $today = gmdate('Y-m-d');
    foreach (TMP_Repository::meetings() as $m) {
        if ($m['meeting_date'] >= $today) {
            if (!$next_meeting || $m['meeting_date'] < $next_meeting['meeting_date']) {
                $next_meeting = $m;
            }
        }
    }
}

get_header();
?>

<main class="tmc-portal" id="tmc-home">
  <section class="tmc-hero">
    <img
      src="<?php echo esc_url($template_dir . '/assets/club-hero.png'); ?>"
      alt="Club members listening to a public speaker at a meeting"
    />
    <div class="tmc-hero-overlay">
      <p class="tmc-eyebrow">Members, guests, mentors, and speeches in one place</p>
      <h1>SpeakWell Toastmasters Club</h1>
      <p>
        Track Pathways progress, prepare for meeting roles, and enrol new members
        through a focused club portal built for weekly momentum.
      </p>
      <div class="tmc-hero-actions">
        <a class="tmc-button tmc-primary" href="#tmc-dashboard">Member Login</a>
        <a class="tmc-button tmc-secondary" href="#tmc-membership">Enrol as Member</a>
      </div>
    </div>
  </section>

  <section class="tmc-section tmc-intro-band">
    <div>
      <p class="tmc-eyebrow">Next meeting</p>
      <?php if ($next_meeting) :
          $dt = new DateTime($next_meeting['meeting_date']); ?>
        <h2><?php echo esc_html($dt->format('l, F j')); ?></h2>
        <p>
          <?php if (!empty($next_meeting['theme'])) : ?>
            Theme: <?php echo esc_html($next_meeting['theme']); ?>.
          <?php endif; ?>
          <?php if (!empty($next_meeting['venue'])) : ?>
            <?php echo esc_html($next_meeting['venue']); ?>
          <?php endif; ?>
        </p>
      <?php else : ?>
        <h2>No meeting scheduled yet</h2>
        <p>Check back soon for upcoming meeting details.</p>
      <?php endif; ?>
    </div>
    <div class="tmc-stat-strip" aria-label="Club snapshot">
      <?php if ($active_count > 0) : ?>
        <span><strong><?php echo esc_html($active_count); ?></strong> active members</span>
      <?php endif; ?>
      <?php if ($pathway_count > 0) : ?>
        <span><strong><?php echo esc_html($pathway_count); ?></strong> Pathways paths</span>
      <?php endif; ?>
    </div>
  </section>

  <section class="tmc-section tmc-split" id="tmc-pathways">
    <div>
      <p class="tmc-eyebrow">Education progress</p>
      <h2>See your Pathways, levels, and next project</h2>
      <p>
        Members can view assigned paths, completed levels, mentor notes, and the next
        recommended project before each meeting. Officers can spot who needs a role,
        evaluation, or level approval.
      </p>
    </div>
    <div class="tmc-path-grid" aria-label="Supported Pathways paths">
      <article><strong>Dynamic Leadership</strong><span>Influence, change, and team leadership.</span></article>
      <article><strong>Engaging Humor</strong><span>Story craft, timing, and audience connection.</span></article>
      <article><strong>Motivational Strategies</strong><span>Coach, inspire, and move people to action.</span></article>
      <article><strong>Presentation Mastery</strong><span>Structure, delivery, and memorable speeches.</span></article>
      <article><strong>Persuasive Influence</strong><span>Negotiate, persuade, and build consensus.</span></article>
      <article><strong>Visionary Communication</strong><span>Communicate plans and lead with vision.</span></article>
    </div>
  </section>

  <section class="tmc-section tmc-dashboard-shell" id="tmc-dashboard">
    <?php echo do_shortcode('[tm_member_dashboard]'); ?>
  </section>

  <section class="tmc-section tmc-enrolment" id="tmc-membership">
    <div>
      <p class="tmc-eyebrow">New members</p>
      <h2>Enrol with the club</h2>
      <p>
        Guests can share contact details, preferred meeting format, speaking goals,
        and payment status. The club membership team receives a clean application summary.
      </p>
    </div>
    <form class="tmc-enrol-form" data-tmc-enrol-form>
      <label>
        Full name
        <input name="name" required placeholder="Your name" />
      </label>
      <label>
        Email
        <input type="email" name="email" required placeholder="you@example.com" />
      </label>
      <label>
        Phone
        <input name="phone" required placeholder="+91 98765 43210" />
      </label>
      <label>
        Preferred path
        <select name="path">
          <option>Presentation Mastery</option>
          <option>Dynamic Leadership</option>
          <option>Engaging Humor</option>
          <option>Motivational Strategies</option>
          <option>Persuasive Influence</option>
          <option>Visionary Communication</option>
        </select>
      </label>
      <label class="tmc-wide">
        Speaking goals
        <textarea name="goals" rows="4" placeholder="Tell us what you want to improve"></textarea>
      </label>
      <label>
        Meeting preference
        <select name="preference">
          <option>In person</option>
          <option>Online</option>
          <option>Hybrid</option>
        </select>
      </label>
      <button class="tmc-button tmc-primary" type="submit">Submit Application</button>
      <p class="tmc-form-status" role="status" data-tmc-form-status></p>
    </form>
  </section>
</main>

<?php
get_footer();
