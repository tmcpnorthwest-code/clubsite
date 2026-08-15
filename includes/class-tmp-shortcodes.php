<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Shortcodes {
    public static function init() {
        add_shortcode('tm_member_login',    [__CLASS__, 'member_login']);
        add_shortcode('tm_member_dashboard',[__CLASS__, 'member_dashboard']);
        add_shortcode('tm_recognition_wall',[__CLASS__, 'recognition_wall']);
        add_shortcode('tm_public_dashboard',[__CLASS__, 'public_dashboard']);
        add_shortcode('tm_voting',          [__CLASS__, 'voting_page']);
        add_shortcode('tm_feedback_form',   [__CLASS__, 'feedback_form']);
        add_shortcode('tm_meeting_hub',     [__CLASS__, 'meeting_hub_page']);
        add_action('wp_enqueue_scripts',    [__CLASS__, 'register_assets']);
    }

    public static function register_assets() {
        wp_register_style( 'tmp-portal',    TMP_PLUGIN_URL . 'assets/portal.css',           [], TMP_VERSION);
        wp_register_script('tmp-portal',    TMP_PLUGIN_URL . 'assets/portal.js',            [], TMP_VERSION, true);
        wp_register_script('tmp-public-dashboard', TMP_PLUGIN_URL . 'assets/public-dashboard.js', [], TMP_VERSION, true);
    }

    private static function enqueue_public() {
        wp_enqueue_style('tmp-portal');
        wp_enqueue_script('tmp-public-dashboard');
        wp_localize_script('tmp-public-dashboard', 'TMPublic', [
            'restUrl' => esc_url_raw(rest_url('toastmasters/v1')),
        ]);
    }

    private static function tm_logo_data_url() {
        $path = TMP_PLUGIN_DIR . 'assets/logo.png';
        if (file_exists($path)) {
            $data = @file_get_contents($path);
            if ($data !== false) {
                return 'data:image/png;base64,' . base64_encode($data);
            }
            return TMP_PLUGIN_URL . 'assets/logo.png';
        }
        return '';
    }

    private static function enqueue() {
        wp_enqueue_style('tmp-portal');
        wp_enqueue_script('tmp-portal');
        wp_enqueue_editor(); // TinyMCE for the bulk-email composer (rich text: bold/links/bullets)
        wp_localize_script('tmp-portal', 'TMPortal', [
            'pluginVersion'  => TMP_VERSION,
            'restUrl'        => esc_url_raw(rest_url('toastmasters/v1')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'standardRoles'  => TMP_Repository::get_standard_roles(),
            'roleGateLevels' => TMP_Repository::get_current_gate_levels(),
            'loginUrl'       => wp_login_url(get_permalink()),
            'logoutUrl'      => wp_logout_url(home_url('/')),
            'clubName'       => get_bloginfo('name'),
            'clubVenue'      => get_option('tmp_default_venue', ''),
            'clubMission'    => get_option('tmp_club_mission', ''),
            'logoUrl'        => get_site_icon_url(80) ?: '',
            'tmLogoUrl'      => self::tm_logo_data_url(),
            'currentUser'    => is_user_logged_in() ? [
                'id'               => get_current_user_id(),
                'name'             => wp_get_current_user()->display_name,
                'email'            => wp_get_current_user()->user_email,
                'canManageMembers' => current_user_can('tmp_manage_members'),
                'canManageMeetings'=> current_user_can('tmp_manage_meetings'),
                'canExCom'         => current_user_can('tmp_ex_com_meeting'),
            ] : null,
        ]);
    }

    public static function member_login() {
        self::enqueue();
        ob_start();
        ?>
        <div class="tmp-portal tmp-login-card">
            <p class="tmp-eyebrow">Member access</p>
            <h2>Login to your club dashboard</h2>
            <?php if (is_user_logged_in()) : ?>
                <p>You are logged in as <?php echo esc_html(wp_get_current_user()->display_name); ?>.</p>
                <a class="tmp-button tmp-primary" href="<?php echo esc_url(home_url('/member-dashboard/')); ?>">Open dashboard</a>
                <a class="tmp-button tmp-secondary" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Logout</a>
            <?php else : ?>
                <p>Use your WordPress member account to view Pathways progress, current level, and club notes.</p>
                <a class="tmp-button tmp-primary" href="<?php echo esc_url(wp_login_url(home_url('/member-dashboard/'))); ?>">Login with WordPress</a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function member_dashboard() {
        self::enqueue();
        if (!is_user_logged_in()) {
            return self::member_login();
        }

        $can_manage_meetings = current_user_can('tmp_manage_meetings');
        $can_ex_com          = current_user_can('tmp_ex_com_meeting');
        $can_meetings_tab    = $can_manage_meetings || $can_ex_com;

        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-member-dashboard <?php if ($can_meetings_tab): ?>data-tmp-vpe<?php endif; ?>>
            <?php echo self::portal_topbar(); ?>

            <!-- Tab navigation — always shown; every member gets My Dashboard + Request Role,
                 VPE/Ex-Com/Admin get additional tabs based on their capabilities. -->
            <nav class="tmp-tab-nav" data-tmp-tab-nav>
                <button class="tmp-tab-btn" data-tab="dashboard">My Dashboard</button>
                <button class="tmp-tab-btn" data-tab="request-role">Request Role</button>
                <?php if ($can_manage_meetings): ?>
                <button class="tmp-tab-btn" data-tab="members">Members</button>
                <?php endif; ?>
                <?php if ($can_meetings_tab): ?>
                <button class="tmp-tab-btn" data-tab="meetings">
                    Manage Meetings
                    <span class="tmp-tab-badge" data-tab-badge="meetings" style="display:none;"></span>
                </button>
                <?php endif; ?>
                <?php if ($can_meetings_tab): ?>
                <button class="tmp-tab-btn" data-tab="spotlight">Spotlight</button>
                <?php endif; ?>
                <?php if ($can_manage_meetings): ?>
                <button class="tmp-tab-btn" data-tab="recognition">Recognition</button>
                <?php endif; ?>
            </nav>

            <div data-tab-body="dashboard">

            <div class="tmp-panel tmp-member-summary-card">
                <p class="tmp-eyebrow">Member dashboard</p>
                <div class="tmp-member-topline">
                    <span class="tmp-member-topline-name" data-tmp-member-name>Loading dashboard</span>
                    <span class="tmp-member-topline-sep">&middot;</span>
                    <span data-tmp-member-summary></span>
                    <span class="tmp-member-topline-sep">&middot;</span>
                    <span class="tmp-status-pill" data-tmp-state><span class="tmp-status-pill-dot"></span></span>
                    <span class="tmp-member-topline-sep">&middot;</span>
                    <span data-tmp-paid-until></span>
                </div>
                <div class="tmp-member-progress">
                    <div class="tmp-card-head">
                        <span class="tmp-member-progress-label">Pathways Progress</span>
                        <span data-tmp-progress class="tmp-member-progress-pct">0%</span>
                    </div>
                    <div class="tmp-level-track" data-tmp-levels></div>
                </div>
            </div>

            <!-- SAA Attendance panel — shown by JS only when logged-in member is today's SAA -->
            <article class="tmp-panel tmp-wide" data-tmp-saa-panel style="display:none;border-left:4px solid var(--tmp-teal);">
                <div class="tmp-card-head">
                    <div>
                        <p class="tmp-eyebrow" style="margin:0;color:var(--tmp-teal);">Your Role Today</p>
                        <h3 style="margin:4px 0 0;">Mark Attendance</h3>
                    </div>
                    <span data-tmp-saa-meeting-label style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                </div>
                <p style="font-size:0.82rem;color:var(--tmp-muted);margin:8px 0 14px;">
                    As today's Sergeant at Arms, mark who attended. VPE will confirm roles and winners after the meeting.
                </p>
                <!-- Assigned members -->
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">
                    <p class="tmp-eyebrow" style="margin:0;font-size:0.72rem;">Assigned Members</p>
                    <button type="button" class="tmp-link-button" data-tmp-saa-mark-all style="font-size:0.8rem;">Mark All Present</button>
                </div>
                <div data-tmp-saa-performers-list></div>
                <!-- Walk-ins -->
                <div style="margin-top:14px;">
                    <p class="tmp-eyebrow" style="margin-bottom:6px;font-size:0.72rem;">Walk-in Members</p>
                    <div style="position:relative;">
                        <input type="text" data-tmp-saa-walkin-search placeholder="Search and add member…" autocomplete="off"
                               style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                        <div data-tmp-saa-walkin-dropdown style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1px solid var(--tmp-line);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:100;max-height:220px;overflow-y:auto;"></div>
                    </div>
                    <div data-tmp-saa-walkin-list style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
                </div>
                <!-- Guests -->
                <div style="margin-top:14px;">
                    <p class="tmp-eyebrow" style="margin-bottom:6px;font-size:0.72rem;">Guests</p>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <input type="text" data-tmp-saa-guest-name placeholder="Guest name"
                               style="flex:1;min-width:140px;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                        <button class="tmp-button tmp-secondary" data-tmp-saa-add-guest style="flex-shrink:0;padding:8px 14px;white-space:nowrap;">+ Guest</button>
                    </div>
                    <div data-tmp-saa-guests-list style="margin-top:8px;"></div>
                </div>
                <div style="margin-top:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <button class="tmp-button tmp-primary" data-tmp-saa-save>Save Attendance</button>
                    <span data-tmp-saa-status style="font-size:0.82rem;"></span>
                </div>
            </article>

            <!-- SAA Voting panel — shown by JS only when logged-in member is today's SAA -->
            <article class="tmp-panel tmp-wide" data-tmp-saa-voting-panel style="display:none;border-left:4px solid var(--tmp-teal);">
                <div class="tmp-card-head">
                    <div>
                        <p class="tmp-eyebrow" style="margin:0;color:var(--tmp-teal);">Your Role Today</p>
                        <h3 style="margin:4px 0 0;">Voting &amp; Table Topics</h3>
                    </div>
                    <span data-tmp-saa-voting-label style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                </div>
                <p style="font-size:0.82rem;color:var(--tmp-muted);margin:8px 0 14px;">
                    Add Table Topics speakers as they step up, then open the poll so members can vote.
                </p>
                <!-- Table Topics speakers -->
                <p class="tmp-eyebrow" style="margin-top:0;">Table Topics Speakers</p>
                <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:6px;">
                    <div style="flex:1;">
                        <label style="display:block;margin-bottom:4px;font-size:0.88rem;font-weight:700;">Add speaker</label>
                        <select data-tmp-saa-tt-select style="display:block;width:100%;">
                            <option value="">— select member —</option>
                        </select>
                    </div>
                    <button class="tmp-button tmp-primary" data-tmp-saa-tt-add style="flex-shrink:0;white-space:nowrap;">+ Add</button>
                </div>
                <div data-tmp-saa-tt-guest-wrap style="display:none;margin-bottom:10px;">
                    <input type="text" data-tmp-saa-tt-name placeholder="Enter guest name" style="display:block;width:100%;" />
                </div>
                <div data-tmp-saa-tt-list></div>
                <!-- Nominees -->
                <div style="margin-top:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                        <p class="tmp-eyebrow" style="margin:0;">Current Nominees</p>
                        <button class="tmp-small-button" data-tmp-saa-refresh-btn title="Re-sync nominees from role assignments">&#8635; Refresh from Assignments</button>
                    </div>
                    <div data-tmp-saa-nominees-summary></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center;">
                        <button class="tmp-button tmp-primary" data-tmp-saa-open-poll style="flex-shrink:0;">Moment of Glory</button>
                        <span data-tmp-saa-poll-status style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <button class="tmp-button" data-tmp-saa-declare-winners-btn style="background:#1e4a6e;color:#fff;border:none;"> 🏆 Declare Winners</button>
                </div>

                <button class="tmp-small-button" data-tmp-saa-results-btn style="margin-top:14px;">Show Live Results</button>

                <div data-tmp-saa-results style="display:none;margin-top:12px;"></div>

                <!-- Shareable voting link — generated on demand -->
                <div style="margin-top:18px;padding:12px;background:#f0f8ff;border-radius:6px;border:1px solid #cce5ff;">
                    <p class="tmp-eyebrow" style="margin:0 0 4px;color:var(--tmp-teal);">Share Voting Link with Members</p>
                    <p style="font-size:0.82rem;color:var(--tmp-muted);margin:0 0 8px;">Generates a secure link valid for 24 hours.</p>
                    <button type="button" class="tmp-small-button" data-tmp-saa-gen-link>Generate Link</button>
                    <div data-tmp-saa-link-display style="display:none;margin-top:10px;">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <code data-tmp-saa-link-url style="flex:1;background:#fff;padding:6px 8px;border:1px solid #ccc;border-radius:4px;font-size:0.82rem;word-break:break-all;"></code>
                            <button type="button" class="tmp-small-button" data-tmp-saa-copy-link>Copy</button>
                        </div>
                        <p data-tmp-saa-link-expiry style="font-size:0.78rem;color:var(--tmp-muted);margin:6px 0 0;"></p>
                    </div>
                </div>
            </article>

            <!-- Member voting panel — auto-shown when a poll opens -->
            <article class="tmp-panel tmp-wide" data-tmp-member-vote-panel style="display:none;border-left:4px solid var(--tmp-teal);">
                <div class="tmp-card-head">
                    <div>
                        <p class="tmp-eyebrow" style="margin:0;color:var(--tmp-teal);">Live Poll</p>
                        <h3 style="margin:4px 0 0;">Cast Your Vote</h3>
                    </div>
                    <span data-tmp-member-vote-label style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                </div>
                <p style="font-size:0.82rem;color:var(--tmp-muted);margin:8px 0 14px;">Vote for the best performer in each category. One vote per category.</p>
                <div data-tmp-member-vote-body><p style="color:var(--tmp-muted);">Checking for active poll…</p></div>
                <div data-tmp-member-vote-status style="margin-top:12px;font-size:0.85rem;font-weight:600;"></div>
            </article>

            <div class="tmp-grid">

                <article class="tmp-panel">
                    <h3>My Milestones</h3>
                    <div class="tmp-milestone-track" data-tmp-milestones>
                        <div class="tmp-m-item" data-m="joined"><span class="tmp-m-dot"></span>Joined</div>
                        <div class="tmp-m-item" data-m="orientation"><span class="tmp-m-dot"></span>Orientation</div>
                        <div class="tmp-m-item" data-m="first_role"><span class="tmp-m-dot"></span>First Role</div>
                        <div class="tmp-m-item" data-m="icebreaker_draft"><span class="tmp-m-dot"></span>Ice Breaker Draft</div>
                        <div class="tmp-m-item" data-m="icebreaker_delivered"><span class="tmp-m-dot"></span>Ice Breaker Delivered</div>
                        <div class="tmp-m-item" data-m="level1_completed"><span class="tmp-m-dot"></span>Level 1 Completed</div>
                    </div>
                </article>

                <!-- Mentor card — populated by JS -->
                <article class="tmp-panel" data-tmp-mentor-card>
                    <h3>My Mentor</h3>
                    <div data-tmp-mentor-info><p style="color:var(--tmp-muted)">Loading...</p></div>
                    <div data-tmp-mentorship-checklist style="margin-top:16px;"></div>
                </article>

                <!-- Your Progress to Level X — consolidated speech + role progress -->
                <article class="tmp-panel" data-tmp-level-status-panel>
                    <h3 style="margin-bottom:16px;">Your Progress to Level <span data-tmp-next-level></span></h3>
                    <div data-tmp-level-status>Loading...</div>
                </article>

                <!-- Suggested Path — non-binding roadmap of roles/speeches to level completion -->
                <article class="tmp-panel" data-tmp-suggested-path-panel>
                    <h3 style="margin-bottom:4px;">Your path to Level <span data-tmp-path-next-level></span> looks like this</h3>
                    <p style="font-size:0.8rem;color:var(--tmp-muted);margin:0 0 16px;">Pick these slots as they open up and you'll be there before you know it!</p>
                    <div data-tmp-suggested-path>Loading...</div>
                </article>

                <!-- Mentor dashboard (visible only if current user is a mentor) — a normal
                     single-column card so it fills the grid slot next to Your Progress instead
                     of forcing its own full-width row. -->
                <article class="tmp-panel" data-tmp-mentor-dashboard style="display:none;">
                    <p class="tmp-eyebrow">Mentor dashboard</p>
                    <h3>My Mentees</h3>
                    <div data-tmp-mentee-list>Loading mentees...</div>
                </article>

            </div><!-- .tmp-grid -->

            <!-- Rate Your Mentor — shown by JS when member has a mentor -->
            <div class="tmp-panel" data-tmp-mentor-rating-panel style="display:none;">
                <p class="tmp-eyebrow">Mentor feedback</p>
                <h3>Rate Your Mentor</h3>
                <p style="font-size:0.85rem;color:var(--tmp-muted);margin:0 0 14px;" data-tmp-mentor-rating-desc></p>
                <div data-tmp-mentor-rating-submitted style="display:none;">
                    <p style="color:var(--tmp-teal);font-weight:600;" data-tmp-mentor-rating-done-msg></p>
                </div>
                <form data-tmp-mentor-rating-form style="display:none;">
                    <div style="display:flex;gap:6px;margin-bottom:12px;" data-tmp-star-picker>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label style="cursor:pointer;font-size:1.6rem;color:#ccc;" data-star="<?php echo $i; ?>">
                            <input type="radio" name="rating" value="<?php echo $i; ?>" style="display:none;" required />
                            &#9733;
                        </label>
                        <?php endfor; ?>
                    </div>
                    <label style="display:block;margin-bottom:12px;">
                        Comments (optional)
                        <textarea name="feedback" rows="3" style="display:block;width:100%;margin-top:4px;padding:8px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" placeholder="What did your mentor do well? Any suggestions?"></textarea>
                    </label>
                    <button class="tmp-button tmp-primary" type="submit">Submit Rating</button>
                    <span data-tmp-mentor-rating-status style="margin-left:10px;font-size:0.82rem;"></span>
                </form>
            </div>

            <!-- My Recognition Score — TM of Month/Quarter live score + breakdown -->
            <div class="tmp-panel tmp-wide" data-tmp-my-score-panel>
                <p class="tmp-eyebrow">Recognition</p>
                <h3>My Recognition Score</h3>
                <p style="font-size:0.82rem;color:var(--tmp-muted);margin:0 0 16px;">
                    Live standings for Toastmaster of the Month &amp; Quarter — attendance, service roles, wins, and level-ups.
                </p>
                <div data-tmp-my-score-body>Loading...</div>
            </div>

            <!-- Recognition history — shown by JS if member has any awards -->
            <div class="tmp-panel" data-tmp-my-recognition style="display:none;">
                <p class="tmp-eyebrow">Recognition</p>
                <h3>My Awards</h3>
                <div data-tmp-my-recognition-list></div>
            </div>

            </div><!-- /data-tab-body="dashboard" -->

            <!-- ══ REQUEST ROLE TAB — visible to every member ══ -->
            <div data-tab-body="request-role" style="display:none;">
                <article class="tmp-panel" data-tmp-meeting-card>
                    <div class="tmp-card-head">
                        <h3 style="margin:0;">Role requests for upcoming meetings</h3>
                        <span data-tmp-meeting-badge class="tmp-badge" style="display:none;"></span>
                    </div>
                    <div data-tmp-meeting-body>
                        <div class="tmp-activity-tabs">
                            <button class="tmp-activity-tab--btn tmp-activity-tab--active" data-tmp-activity-tab="active-requests">Active Requests</button>
                            <button class="tmp-activity-tab--btn" data-tmp-activity-tab="assigned-roles">Assigned Roles</button>
                            <button class="tmp-activity-tab--btn" data-tmp-activity-tab="role-history">Role History</button>
                            <button class="tmp-activity-tab--btn" data-tmp-activity-tab="request-role">Request a Role</button>
                        </div>

                        <section class="tmp-activity-tab--body" data-tmp-activity-tab-body="active-requests" data-tmp-active-requests-section>
                            <div data-tmp-active-requests>Loading requests...</div>
                        </section>

                        <section class="tmp-activity-tab--body" data-tmp-activity-tab-body="assigned-roles" style="display:none;" data-tmp-assigned-roles-section>
                            <div data-tmp-assigned-roles>Loading assignments...</div>
                        </section>

                        <section class="tmp-activity-tab--body" data-tmp-activity-tab-body="role-history" style="display:none;">
                            <div data-tmp-role-history>Loading history...</div>
                        </section>

                        <section class="tmp-activity-tab--body" data-tmp-activity-tab-body="request-role" style="display:none;" data-tmp-request-section>
                            <form class="tmp-form" data-tmp-member-request-form style="background:none;border:none;padding:0;">
                                <div data-tmp-deadline-info style="margin-bottom:12px;"></div>
                                <div data-tmp-dupe-request-warning style="margin-bottom:12px;"></div>
                                <div class="tmp-form-section">
                                    <label class="tmp-form-section-label">Meeting</label>
                                    <div class="tmp-field-grid">
                                        <select name="meeting_id" required data-tmp-req-meeting-select></select>
                                    </div>
                                </div>
                                <div class="tmp-form-section">
                                    <label class="tmp-form-section-label">Priorities</label>
                                    <div class="tmp-field-grid" data-tmp-role-selection-section>
                                        <label>Priority 1 <select name="priorities[]" required data-tmp-req-role-select></select></label>
                                        <label>Priority 2 <select name="priorities[]" data-tmp-req-role-select></select></label>
                                        <label>Priority 3 <select name="priorities[]" data-tmp-req-role-select></select></label>
                                    </div>
                                </div>
                                <div data-tmp-role-info style="margin:10px 0;font-size:0.85rem;"></div>
                                <div style="margin-top:10px;">
                                    <button class="tmp-button tmp-primary" type="submit" style="width:100%;">Submit Request</button>
                                </div>
                            </form>
                        </section>

                    </div><!-- /data-tmp-meeting-body -->
                </article>
            </div><!-- /data-tab-body="request-role" -->

            <?php if ($can_manage_meetings): ?>
            <!-- ══ MEMBERS TAB ══ -->
            <div data-tab-body="members" style="display:none;">

                <!-- CSV member import — single-row, always visible -->
                <section class="tmp-panel">
                    <div class="tmp-card-head" style="margin-bottom:12px;">
                        <h3>Import Members from CSV</h3>
                        <span class="tmp-eyebrow" title='Customer ID becomes the WordPress username. Credentials like PM1 or DL3 become Pathway and Level. Blank credentials become No pathway registered. An optional "Current Position" column grants extra dashboard tabs — any position containing "VP Education" grants the VP Education tabs; any other non-blank position grants the Ex Com meeting-day tab.'>Hover for column format &#9432;</span>
                    </div>
                    <form class="tmp-form" data-tmp-import-form style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                        <label style="flex:1;min-width:200px;">Membership CSV <input type="file" name="file" accept=".csv,text/csv" required /></label>
                        <label style="flex:0 0 200px;">Default password <input type="text" name="default_password" value="Welcome@123" minlength="8" required /></label>
                        <button class="tmp-button tmp-primary" type="submit" style="flex-shrink:0;">Import Members</button>
                        <span class="tmp-inline-status" data-tmp-import-status></span>
                    </form>
                    <p style="font-size:0.82rem;color:var(--tmp-muted);margin:10px 0 0;">
                        Every newly created member automatically gets a welcome email with the WhatsApp group link below, a link to their dashboard, and instructions for requesting a role.
                    </p>
                </section>

                <!-- Welcome email settings -->
                <section class="tmp-panel" data-tmp-welcome-settings-panel>
                    <div class="tmp-card-head" style="margin-bottom:12px;">
                        <h3>New Member Welcome Email</h3>
                        <span class="tmp-eyebrow">Sent automatically on import</span>
                    </div>
                    <form class="tmp-form" data-tmp-welcome-settings-form style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                        <label style="flex:1;min-width:280px;">Orientation WhatsApp group invite link
                            <input type="url" data-tmp-whatsapp-url placeholder="https://chat.whatsapp.com/..." style="width:100%;" />
                        </label>
                        <button class="tmp-button tmp-primary" type="submit" style="flex-shrink:0;">Save</button>
                        <span class="tmp-inline-status" data-tmp-welcome-settings-status></span>
                    </form>
                    <form class="tmp-form" data-tmp-test-welcome-form style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:12px;padding-top:12px;border-top:1px solid var(--tmp-line);">
                        <label style="flex:1;min-width:220px;">Send a test copy to
                            <input type="email" data-tmp-test-welcome-email placeholder="you@example.com" required style="width:100%;" />
                        </label>
                        <button class="tmp-button tmp-secondary" type="submit" style="flex-shrink:0;">Send Test Email</button>
                        <span class="tmp-inline-status" data-tmp-test-welcome-status></span>
                    </form>
                </section>

                <!-- Unified member table: all levels, speech/role progress for L1–L5, mentor column -->
                <section class="tmp-panel" data-tmp-members-panel>
                    <div class="tmp-card-head" style="margin-bottom:16px;">
                        <h3>Members</h3>
                        <span class="tmp-eyebrow">Roster &amp; Pathways Progress</span>
                    </div>
                    <div class="tmp-stat-strip">
                        <span class="tmp-stat-pill" data-tmp-vpe-ready-count style="display:none;"></span>
                        <span class="tmp-stat-pill tmp-stat-pill--muted" data-tmp-vpe-member-count>0 members</span>
                    </div>
                    <div data-tmp-unmentored-alert></div>
                    <div class="tmp-admin-filters" style="margin:16px 0;">
                        <input type="text" data-tmp-vpe-search placeholder="Search by name or email...">
                        <select data-tmp-vpe-pathway>
                            <option value="all">All Pathways</option>
                        </select>
                        <select data-tmp-vpe-level>
                            <option value="all">All Levels</option>
                            <option value="0">Level 0 (New — no levels completed)</option>
                            <option value="1">Level 1</option>
                            <option value="2">Level 2</option>
                            <option value="3">Level 3</option>
                            <option value="4">Level 4</option>
                            <option value="5">Level 5</option>
                        </select>
                        <select data-tmp-vpe-mentor-filter>
                            <option value="all">All Mentors</option>
                            <option value="none">No Mentor Assigned</option>
                            <option value="is_mentor">Is a Mentor (to anyone)</option>
                        </select>
                        <select data-tmp-vpe-lp-status>
                            <option value="all">All statuses</option>
                            <option value="ready">🟢 Ready to advance</option>
                            <option value="in_progress">🟡 In progress</option>
                            <option value="stuck">🔴 Stuck</option>
                        </select>
                    </div>
                    <div class="tmp-bulk-email-bar" data-tmp-bulk-email-bar style="display:none;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:10px 14px;background:#f0f8ff;border:1px solid #cce5ff;border-radius:6px;">
                        <span data-tmp-bulk-email-count style="font-weight:600;font-size:0.88rem;"></span>
                        <button class="tmp-button tmp-primary" type="button" data-tmp-bulk-email-btn>✉ Send Email</button>
                        <button class="tmp-link-button" type="button" data-tmp-bulk-email-clear style="font-size:0.82rem;">Clear selection</button>
                    </div>
                    <div class="tmp-table-wrap">
                        <table class="tmp-table" style="width:100%;">
                            <colgroup>
                                <col class="tmp-col-select" style="width:34px;">
                                <col class="tmp-col-name">
                                <col class="tmp-col-progress">
                                <col class="tmp-col-mentor">
                                <col class="tmp-col-actions">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><input type="checkbox" data-tmp-select-all-members title="Select all (filtered)" /></th>
                                    <th data-sort-col="name" class="tmp-sortable">Name <span class="tmp-sort-ind">▲</span></th>
                                    <th>Speeches / Roles</th>
                                    <th>Mentor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody data-tmp-unified-rows></tbody>
                        </table>
                    </div>
                </section>

                <!-- Bulk email composer modal -->
                <div class="tmp-modal-overlay" data-tmp-bulk-email-modal style="display:none;">
                    <div class="tmp-modal-card" role="dialog" aria-modal="true" aria-labelledby="tmp-bulk-email-title" style="max-width:640px;">
                        <button type="button" class="tmp-modal-close" data-tmp-bulk-email-close aria-label="Close">&times;</button>
                        <h3 id="tmp-bulk-email-title">Send Email</h3>
                        <p class="tmp-modal-hint" data-tmp-bulk-email-recipients></p>
                        <label style="display:block;margin-bottom:14px;">Template
                            <select data-tmp-bulk-email-template style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:5px;">
                                <option value="">— Blank —</option>
                                <option value="orientation">Orientation Invite</option>
                                <option value="announcement">General Announcement</option>
                            </select>
                        </label>
                        <div data-tmp-orientation-fields style="display:none;margin-bottom:14px;padding:12px;background:#f0f8ff;border:1px solid #cce5ff;border-radius:6px;">
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                                <label style="flex:1;min-width:180px;">Date &amp; time
                                    <input type="text" data-tmp-orientation-datetime placeholder="e.g. Saturday, Aug 9 at 6:00 PM IST" style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:5px;" />
                                </label>
                                <label style="flex:1;min-width:220px;">Google Meet link
                                    <input type="url" data-tmp-orientation-meet-link placeholder="https://meet.google.com/..." style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:5px;" />
                                </label>
                            </div>
                            <button class="tmp-small-button" type="button" data-tmp-orientation-apply>Insert into message</button>
                            <span style="font-size:0.78rem;color:var(--tmp-muted);margin-left:8px;">Create the meeting in Google Calendar/Meet first, then paste its link here.</span>
                        </div>
                        <form data-tmp-bulk-email-form class="tmp-form" style="background:none;border:none;padding:0;">
                            <label style="display:block;margin-bottom:10px;">Subject
                                <input type="text" data-tmp-bulk-email-subject required style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:5px;" />
                            </label>
                            <label style="display:block;margin-bottom:4px;">Message <span style="font-weight:400;color:var(--tmp-muted);font-size:0.8rem;">— use <code>{{name}}</code> anywhere to insert each recipient's name</span></label>
                            <div data-tmp-bulk-email-editor-wrap style="margin-bottom:14px;">
                                <?php
                                wp_editor('', 'tmp_bulk_email_body', [
                                    'textarea_name' => 'tmp_bulk_email_body',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                    'quicktags'     => false,
                                ]);
                                ?>
                            </div>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <button class="tmp-button tmp-primary" type="submit">Send</button>
                                <span class="tmp-inline-status" data-tmp-bulk-email-status></span>
                            </div>
                        </form>
                    </div>
                </div>

            </div><!-- /tab-body members -->
            <?php endif; ?>

            <?php if ($can_meetings_tab): ?>
            <!-- ══ MEETINGS TAB ══ -->
            <div data-tab-body="meetings" style="display:none;">

                <!-- hidden compatibility stubs kept for JS references -->
                <span data-tmp-meeting-count style="display:none;">0 meetings</span>
                <div data-tmp-meetings-compact-list style="display:none;"></div>

                <!-- Current Meeting bar — single shared selector driving Setup / Day-of / Wrap-up -->
                <section class="tmp-panel tmp-meeting-bar">
                    <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;">
                        <label style="display:block;flex:1;min-width:220px;">
                            Current meeting
                            <select name="meeting_id" required data-tmp-meeting-select style="display:block;width:100%;max-width:520px;margin-top:4px;"></select>
                        </label>
                        <span data-tmp-meeting-status-badge class="tmp-stage-badge" style="display:none;"></span>
                    </div>
                    <div class="tmp-stage-pills" data-tmp-stage-nav>
                        <?php if ($can_manage_meetings): ?>
                        <button type="button" class="tmp-stage-pill" data-tmp-stage-btn="setup">Setup<span class="tmp-stage-pill-sub">Details &amp; roles</span></button>
                        <?php endif; ?>
                        <button type="button" class="tmp-stage-pill" data-tmp-stage-btn="dayof">Day-of<span class="tmp-stage-pill-sub">Voting &amp; Table Topics</span></button>
                        <button type="button" class="tmp-stage-pill" data-tmp-stage-btn="wrapup">Wrap-up<span class="tmp-stage-pill-sub">Attendance &amp; close-out</span></button>
                    </div>
                </section>

                <?php if ($can_manage_meetings): ?>
                <div data-tmp-stage-body="setup">
                <section class="tmp-panel">
                    <!-- Meeting form — single collapsible; label/expanded-state reflect create vs. edit -->
                    <div data-tmp-meeting-form-wrap style="display:none;">
                        <button class="tmp-collapsible-toggle" data-tmp-meeting-form-toggle aria-expanded="true" style="width:100%;text-align:left;">
                            <span data-tmp-meeting-form-label>Schedule New Meeting</span>
                            <span class="tmp-chevron" aria-hidden="true" style="transform:rotate(90deg);">&#9658;</span>
                        </button>
                        <div data-tmp-meeting-form-body style="display:block;margin-top:14px;">
                        <form class="tmp-form" data-tmp-meeting-form>
                            <input type="hidden" name="id" />

                            <div class="tmp-wide tmp-form-section">
                                <p class="tmp-form-section-label">Basics</p>
                                <div class="tmp-field-grid">
                                    <label>Chapter Meeting # <input type="number" name="chapter_number" min="1" placeholder="e.g. 477" /></label>
                                    <label class="tmp-field-wide">Theme <input name="theme" required placeholder="Meeting theme" /></label>
                                    <label class="tmp-field-wide">Venue or link <input name="venue" placeholder="Room, address, or meeting link" /></label>
                                </div>
                            </div>

                            <div class="tmp-wide tmp-form-section">
                                <p class="tmp-form-section-label">Schedule</p>
                                <div class="tmp-field-grid">
                                    <label>Meeting date <input type="date" name="meeting_date" required /></label>
                                    <label>Start time <input type="time" name="start_time" value="11:00" /></label>
                                    <label>Total Duration (mins) <input type="number" name="total_duration" value="120" min="0" /></label>
                                    <label>Requests deadline <input type="datetime-local" name="requests_close_at" /></label>
                                </div>
                            </div>

                            <div class="tmp-wide tmp-form-section">
                                <div class="tmp-roles-setup" style="padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                                        <p class="tmp-form-section-label" data-tmp-roles-setup-label style="margin:0;">Role Slots</p>
                                        <label style="font-size:12px;color:#555;display:flex;align-items:center;gap:6px;">
                                            Template
                                            <select data-tmp-role-preset style="font-size:12px;padding:2px 4px;">
                                                <option value="standard">Standard agenda (all roles)</option>
                                                <option value="custom">Custom Meeting (limited roles)</option>
                                            </select>
                                        </label>
                                    </div>
                                    <p style="font-size:12px;color:#555;margin:0 0 10px;">
                                        <span data-tmp-roles-setup-hint>Tap a role to add or remove it. Filled chips are already on this meeting's agenda.</span>
                                    </p>
                                    <div class="tmp-chip-list" data-tmp-roles-grid>
                                        <?php foreach (TMP_Repository::get_standard_roles() as $fullName => $shortName) : ?>
                                            <label class="tmp-chip tmp-role-chip" data-tmp-role-chip>
                                                <input type="checkbox" name="roles[]" value="<?php echo esc_attr($fullName); ?>" checked style="display:none;">
                                                <?php echo esc_html($shortName); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="tmp-stepper-row">
                                        <div class="tmp-stepper">
                                            <span class="tmp-stepper-label">Speech Slots</span>
                                            <div class="tmp-stepper-controls">
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-dec="speech_slots">−</button>
                                                <span class="tmp-stepper-val" data-tmp-stepper-val="speech_slots">3</span>
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-inc="speech_slots">+</button>
                                            </div>
                                            <input type="number" name="speech_slots" value="3" min="0" max="10" />
                                        </div>
                                        <div class="tmp-stepper">
                                            <span class="tmp-stepper-label">Ad Hoc Speakers</span>
                                            <div class="tmp-stepper-controls">
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-dec="adhoc_slots">−</button>
                                                <span class="tmp-stepper-val" data-tmp-stepper-val="adhoc_slots">0</span>
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-inc="adhoc_slots">+</button>
                                            </div>
                                            <input type="number" name="adhoc_slots" value="0" min="0" max="10" />
                                        </div>
                                        <div class="tmp-stepper">
                                            <span class="tmp-stepper-label">Fun Sessions</span>
                                            <div class="tmp-stepper-controls">
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-dec="fun_slots">−</button>
                                                <span class="tmp-stepper-val" data-tmp-stepper-val="fun_slots">0</span>
                                                <button type="button" class="tmp-stepper-btn" data-tmp-stepper-inc="fun_slots">+</button>
                                            </div>
                                            <input type="number" name="fun_slots" value="0" min="0" max="5" />
                                        </div>
                                    </div>
                                    <p style="font-size:11px;color:#666;margin-top:8px;">* This will automatically create matching Evaluator slots. Table Topics Speakers are added live via the Voting panel during the meeting. Ad Hoc Speakers and Fun Session organizers are assigned by name (they don't need club member accounts) from the Role Assignment panel below.</p>
                                </div>
                            </div>

                            <div class="tmp-wide tmp-form-section">
                                <p class="tmp-form-section-label">Agenda notes</p>
                                <label class="tmp-wide">Notes <textarea name="agenda_notes" rows="2"></textarea></label>
                            </div>

                            <div class="tmp-form-actions tmp-wide">
                                <button class="tmp-button tmp-primary" type="submit">Save Meeting</button>
                                <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-meeting>Clear</button>
                                <button class="tmp-button tmp-danger" type="button" data-tmp-delete-meeting style="display:none;margin-left:6px;">Delete Meeting</button>
                                <span class="tmp-inline-status" data-tmp-meeting-save-status style="margin-left:10px;font-size:13px;"></span>
                            </div>
                        </form>
                        </div>
                    </div>
                </section>

                <!-- Pending Role Requests — scoped to the currently-selected meeting -->
                <section class="tmp-panel">
                    <button class="tmp-collapsible-toggle" data-tmp-requests-card-toggle aria-expanded="true" style="width:100%;text-align:left;">
                        <span>Pending Role Requests</span>
                        <span data-tmp-request-count class="tmp-badge" style="display:none;margin-left:6px;"></span>
                        <span class="tmp-chevron" aria-hidden="true" style="transform:rotate(90deg);">&#9658;</span>
                    </button>
                    <div data-tmp-requests-card-body style="display:block;margin-top:14px;">
                    <div class="tmp-request-filter-row" style="margin-bottom:14px;display:none;" data-tmp-request-filter-row>
                        <label style="font-size:0.85rem;font-weight:700;display:flex;align-items:center;gap:8px;">
                            Filter by role
                            <select data-tmp-request-role-filter style="min-width:200px;">
                                <option value="">All Roles</option>
                            </select>
                        </label>
                    </div>
                    <div data-tmp-vpe-requests>Loading requests...</div>
                    </div><!-- /data-tmp-requests-card-body -->
                </section>

                <!-- Role Assignment -->
                <div data-tmp-role-assignment-wrap style="display:none;">
                <section class="tmp-panel">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <button class="tmp-collapsible-toggle" data-tmp-role-card-toggle aria-expanded="true" style="flex:1;text-align:left;">
                            <span>Role Assignment</span>
                            <span class="tmp-chevron" aria-hidden="true" style="transform:rotate(90deg);">&#9658;</span>
                        </button>
                        <button type="button" class="tmp-small-button" data-tmp-rebuild-agenda title="Rebuild the agenda in the standard prescribed order, preserving all member assignments">Rebuild Agenda</button>
                    </div>
                    <div data-tmp-role-card-body style="display:block;margin-top:14px;">
                        <div data-tmp-role-status-panel></div>
                    </div><!-- /data-tmp-role-card-body -->
                </section>
                </div>

                <!-- Agenda collapsible — shows selected meeting's timeline read-only -->
                <div data-tmp-meeting-agenda-wrap style="display:none;">
                <section class="tmp-panel">
                    <button class="tmp-collapsible-toggle" data-tmp-agenda-toggle aria-expanded="false" style="width:100%;text-align:left;">
                        <span>Agenda</span>
                        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                    </button>
                    <div data-tmp-agenda-body style="display:none;margin-top:14px;">
                        <div data-tmp-meeting-list></div>
                    </div>
                </section>
                </div>
                </div><!-- /data-tmp-stage-body="setup" -->
                <?php endif; ?>

                <form data-tmp-assignment-form style="display:none;">
                    <input type="hidden" name="id" />
                    <input type="hidden" name="role_name" />
                    <input type="hidden" name="meeting_id" />
                    <input type="text" name="speech_title" placeholder="Speech title (optional)" data-tmp-speech-title-wrapper style="display:none;width:100%;margin-bottom:6px;" />
                    <input type="text" name="time_green" style="display:none;" />
                    <input type="text" name="time_yellow" style="display:none;" />
                    <input type="text" name="time_red" style="display:none;" />
                    <select name="status" style="display:none;">
                        <option>Planned</option>
                        <option>Requested</option>
                        <option>Confirmed</option>
                        <option>Needs replacement</option>
                        <option>Completed</option>
                    </select>
                    <input type="checkbox" name="cooloff_override" value="1" style="display:none;" />
                    <input type="text" name="override_reason" style="display:none;" />
                    <div data-tmp-cooloff-warning style="display:none;"></div>
                    <div data-tmp-cooloff-override-wrapper style="display:none;"></div>
                    <div data-tmp-service-role-warning style="display:none;"></div>
                    <div data-tmp-timing-wrap style="display:none;"></div>
                    <div data-tmp-role-suggestions style="display:none;"></div>
                </form>

                <!-- Voting panel -->
                <div data-tmp-stage-body="dayof">
                <section class="tmp-panel" data-tmp-voting-panel>
                    <div class="tmp-card-head">
                        <div>
                            <p class="tmp-eyebrow">Meeting Day</p>
                            <h3 style="margin:0;">Voting &amp; Table Topics</h3>
                        </div>
                        <span data-tmp-voting-meeting-label style="color:var(--tmp-muted);font-size:0.85rem;"></span>
                    </div>
                    <p style="color:var(--tmp-muted);font-size:0.88rem;margin-top:6px;">
                        Uses the meeting selected above. Add Table Topics speakers as they step up — the voting form on the home page updates automatically.
                    </p>
                    <div data-tmp-tt-entry style="display:none;">
                        <p class="tmp-eyebrow" style="margin-top:20px;">Table Topics Speakers</p>
                        <div class="tmp-tt-add-row" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:6px;">
                            <div style="flex:1;min-width:0;">
                                <label style="display:block;margin-bottom:4px;font-size:0.88rem;font-weight:700;">Add speaker</label>
                                <select data-tmp-tt-member-select style="display:block;width:100%;">
                                    <option value="">— select member —</option>
                                </select>
                            </div>
                            <button class="tmp-button tmp-primary" data-tmp-tt-add-btn style="flex-shrink:0;white-space:nowrap;">+ Add</button>
                        </div>
                        <div data-tmp-tt-guest-wrap style="display:none;margin-bottom:10px;">
                            <input type="text" data-tmp-tt-name placeholder="Enter guest name" style="display:block;width:100%;" />
                        </div>
                        <div data-tmp-tt-speaker-list></div>
                    </div>
                    <div data-tmp-voting-nominees style="display:none;margin-top:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                            <p class="tmp-eyebrow" style="margin:0;">Current Nominees</p>
                            <button class="tmp-small-button" data-tmp-refresh-nominees-btn title="Re-sync main and auxiliary nominees from confirmed role assignments">&#8635; Refresh from Assignments</button>
                        </div>
                        <div data-tmp-nominees-summary></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center;">
                            <button class="tmp-button tmp-primary" data-tmp-open-poll-btn style="flex-shrink:0;">Moment of Glory</button>
                            <span data-tmp-poll-status style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                        </div>
                    </div>

                    <!-- Post-meeting actions — Declare Winners, Results, Send Emails all in one row -->
                    <div data-tmp-postmeeting-actions style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid var(--tmp-line);">
                        <p class="tmp-eyebrow" style="margin:0 0 10px;">Post-Meeting Actions</p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button class="tmp-button" data-tmp-declare-winners-btn style="background:#1e4a6e;color:#fff;border:none;">&#127942; Declare Winners</button>
                            <button class="tmp-small-button" data-tmp-voting-results-btn>Show Live Results</button>
                        </div>
                        <div data-tmp-voting-results style="display:none;margin-top:12px;"></div>
                    </div>
                </section>
                </div><!-- /data-tmp-stage-body="dayof" -->

                <!-- Meeting wrap-up -->
                <div data-tmp-stage-body="wrapup">
                <section class="tmp-panel" data-tmp-wrapup-panel>
                    <div class="tmp-card-head">
                        <div>
                            <p class="tmp-eyebrow">After the Meeting</p>
                            <h3 style="margin:0;">Meeting Wrap-Up</h3>
                        </div>
                        <span data-tmp-wrapup-badge style="display:none;background:#e8f5e9;color:#2e7d32;border:1px solid #4caf50;border-radius:20px;padding:3px 10px;font-size:0.78rem;font-weight:700;">✓ Completed</span>
                    </div>
                    <p style="color:var(--tmp-muted);font-size:0.88rem;margin-top:6px;">
                        Uses the meeting selected above. Meeting Pulse on the home page updates once you complete this.
                    </p>
                    <div data-tmp-wrapup-content style="display:none;">
                        <div style="margin-bottom:20px;">
                            <div class="tmp-wrapup-stat-row">
                                <div class="tmp-wrapup-stat"><b data-tmp-stat-roles>0</b>Roles performed</div>
                                <div class="tmp-wrapup-stat"><b data-tmp-stat-present>0</b>Members present</div>
                                <div class="tmp-wrapup-stat"><b data-tmp-stat-guests>0</b>Guests</div>
                            </div>
                            <p data-tmp-role-attendance-count style="margin:6px 0 0;font-size:0.82rem;color:var(--tmp-muted);">Loading…</p>
                            <button class="tmp-link-button" data-tmp-refresh-role-attendance style="font-size:0.82rem;color:var(--tmp-teal);margin-top:4px;">↺ Refresh from Assignments</button>
                        </div>
                        <div style="margin-bottom:20px;">
                            <p class="tmp-eyebrow" style="margin-bottom:6px;">Also Attended <span style="font-weight:400;font-size:0.78rem;color:var(--tmp-muted);">(no assigned role)</span></p>
                            <div style="position:relative;">
                                <input type="text" data-tmp-walkin-search placeholder="Search member name…" autocomplete="off"
                                       style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                                <div data-tmp-walkin-dropdown style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1px solid var(--tmp-line);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:100;max-height:220px;overflow-y:auto;"></div>
                            </div>
                            <div data-tmp-walkin-list style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
                        </div>
                        <div style="margin-bottom:20px;">
                            <p class="tmp-eyebrow" style="margin-bottom:6px;">Guests</p>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="text" data-tmp-guest-name placeholder="Guest name" style="flex:1;min-width:140px;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                                <button class="tmp-button tmp-primary" data-tmp-add-guest-btn style="flex-shrink:0;white-space:nowrap;padding:8px 14px;">+ Add Guest</button>
                            </div>
                            <div data-tmp-guests-list class="tmp-chip-list" style="margin-top:0;"></div>
                        </div>
                        <?php if ($can_manage_meetings): ?>
                        <div data-tmp-rate-speaker-section style="display:none;margin-bottom:20px;">
                            <p class="tmp-eyebrow" style="margin:0 0 4px;">Rate the Speaker</p>
                            <p style="font-size:0.82rem;color:var(--tmp-muted);margin:0 0 12px;">Share feedback links with attendees during or after each speech. Review responses and email the rollup to each speaker, VPE, and mentor.</p>
                            <div data-tmp-speaker-feedback-list></div>
                        </div>
                        <?php endif; ?>
                        <div class="tmp-wrapup-actions">
                            <button class="tmp-button tmp-primary" data-tmp-complete-meeting-btn>&#10003; Complete Meeting</button>
                        <div class="tmp-wrapup-status">
                            <div data-tmp-speaker-feedback-email-status class="tmp-status-muted"></div>
                            <div data-tmp-wrapup-save-status class="tmp-status"></div>
                        </div>
                        </div>
                    </div>
                </section>
                </div><!-- /data-tmp-stage-body="wrapup" -->

            </div><!-- /tab-body meetings -->
            <?php endif; ?>

            <?php if ($can_meetings_tab): ?>
            <!-- ══ SPOTLIGHT TAB ══ -->
            <div data-tab-body="spotlight" style="display:none;">
                <section class="tmp-panel" data-tmp-excom-spotlight-panel>
                    <p class="tmp-eyebrow">Homepage spotlight</p>
                    <h3>New Member Spotlight</h3>
                    <p>Feature brand-new members on the homepage with a welcome message. Multiple members can be live at once.</p>
                    <ul class="tmp-spotlight-live-list" data-tmp-spotlight-live-list></ul>
                    <form class="tmp-form" data-tmp-spotlight-form>
                        <label class="tmp-wide">
                            Member <span style="font-weight:400;color:var(--tmp-muted);">(Level 0 only)</span>
                            <select data-tmp-spotlight-member required>
                                <option value="">— pick a new member —</option>
                            </select>
                        </label>
                        <label class="tmp-wide">
                            Photo
                            <div class="tmp-spotlight-photo-row">
                                <div class="tmp-spotlight-photo-preview" data-tmp-spotlight-photo-preview></div>
                                <div>
                                    <button type="button" class="tmp-button tmp-secondary" data-tmp-spotlight-upload-btn>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        Upload Photo
                                    </button>
                                    <input type="file" accept="image/*" data-tmp-spotlight-photo-input style="display:none;" />
                                    <p class="tmp-spotlight-photo-hint" data-tmp-spotlight-photo-hint>Becomes the member's profile picture site-wide. JPG or PNG, up to 4MB.</p>
                                </div>
                            </div>
                        </label>
                        <label class="tmp-wide">
                            Welcome blurb
                            <textarea data-tmp-spotlight-blurb rows="3" placeholder="e.g. Please join us in welcoming Priya — a software engineer with a passion for public speaking!"></textarea>
                        </label>
                        <div class="tmp-spotlight-publish-row tmp-wide">
                            <button class="tmp-button tmp-primary" type="submit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                                Publish to Home Page
                            </button>
                            <span class="tmp-inline-status" data-tmp-spotlight-status></span>
                        </div>
                    </form>
                </section>
            </div><!-- /tab-body spotlight -->
            <?php endif; ?>

            <?php if ($can_manage_meetings): ?>
            <!-- ══ RECOGNITION TAB ══ -->
            <div data-tab-body="recognition" style="display:none;">

                <section class="tmp-panel" data-tmp-recognition-panel>
                    <div class="tmp-card-head" style="margin-bottom:16px;">
                        <h3>Member Recognition</h3>
                        <span class="tmp-eyebrow">Toastmaster of the Month / Quarter</span>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:flex-end;">
                        <label style="flex:0 0 auto;">
                            Award type
                            <select data-tmp-recog-type style="display:block;margin-top:4px;">
                                <option value="month">Toastmaster of the Month</option>
                                <option value="quarter">Toastmaster of the Quarter</option>
                            </select>
                        </label>
                        <label style="flex:0 0 auto;">
                            Period start
                            <input type="date" data-tmp-recog-start style="display:block;margin-top:4px;" />
                        </label>
                        <label style="flex:0 0 auto;">
                            Period end
                            <input type="date" data-tmp-recog-end style="display:block;margin-top:4px;" />
                        </label>
                        <button class="tmp-button tmp-primary" data-tmp-recog-compute style="flex:0 0 auto;align-self:flex-end;">Compute Scores</button>
                    </div>
                    <div data-tmp-recog-scores style="overflow-x:auto;"></div>
                    <div style="margin-top:28px;">
                        <h4 style="margin:0 0 10px;">Past Awards</h4>
                        <div data-tmp-recog-awards-list></div>
                    </div>
                </section>

                <section class="tmp-panel" data-tmp-mentor-feedback-panel style="margin-top:20px;">
                    <div class="tmp-card-head" style="margin-bottom:16px;">
                        <h3>Mentor Feedback</h3>
                        <span class="tmp-eyebrow">Ratings &amp; comments submitted by mentees (VPE only)</span>
                    </div>
                    <div data-tmp-mentor-feedback-list></div>
                </section>

            </div><!-- /tab-body recognition -->

            <!-- Mentor assignment modal -->
            <div id="tmp-mentor-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;border-radius:8px;padding:30px;max-width:480px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                    <h3 style="margin:0 0 6px;">Assign Mentor</h3>
                    <p id="tmp-mentor-modal-member" style="color:var(--tmp-muted);margin:0 0 16px;font-size:13px;"></p>
                    <label style="display:block;margin-bottom:16px;">
                        Select eligible mentor (Level 2+, active)
                        <select id="tmp-mentor-select" style="width:100%;margin-top:6px;"></select>
                    </label>
                    <p id="tmp-mentor-modal-error" style="display:none;color:var(--tmp-burgundy);font-size:13px;margin:0 0 12px;"></p>
                    <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                        <button class="tmp-button tmp-secondary" id="tmp-mentor-modal-cancel">Cancel</button>
                        <button class="tmp-button tmp-primary" id="tmp-mentor-modal-save">Save Mentor</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function recognition_wall() {
        self::enqueue_public();
        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-recognition-wall>
            <div class="tmp-panel">
                <p class="tmp-eyebrow">Club Awards</p>
                <h2>Toastmaster of the Month &amp; Quarter</h2>
                <div data-tmp-tm-awards><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>
            <div class="tmp-panel">
                <p class="tmp-eyebrow">Member recognition</p>
                <h2>Recent Level-Ups</h2>
                <div data-tmp-level-ups-list><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function public_dashboard() {
        self::enqueue_public();
        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-public-dashboard>

            <div class="tmp-panel">
                <p class="tmp-eyebrow">Club Awards</p>
                <h2>Toastmaster of the Month &amp; Quarter</h2>
                <div data-tmp-public-tm-awards><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>

            <div class="tmp-panel">
                <p class="tmp-eyebrow">Member recognition</p>
                <h2>Recent Level-Ups</h2>
                <div data-tmp-public-level-ups><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>

            <div class="tmp-panel">
                <p class="tmp-eyebrow">Last meeting</p>
                <h2>Meeting Report</h2>
                <div data-tmp-public-meeting><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>

            <div class="tmp-panel">
                <p class="tmp-eyebrow">Breadth award</p>
                <h2>Role Diversity Leaders</h2>
                <div data-tmp-public-diversity><p style="color:var(--tmp-muted)">Loading...</p></div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    private static function portal_topbar() {
        $user       = wp_get_current_user();
        $logout_url = wp_logout_url(home_url('/'));
        $home_url   = home_url('/');
        ob_start();
        ?>
        <div class="tmp-portal-topbar">
            <div style="display:flex;align-items:center;gap:14px;">
                <span class="tmp-portal-topbar__user"><?php echo esc_html($user->display_name); ?></span>
                <a class="tmp-portal-topbar__home" href="<?php echo esc_url($home_url); ?>">&#8592; Club Home</a>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" class="tmp-topbar-btn" data-tmp-change-password-open>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Change Password
                </button>
                <a class="tmp-signout-btn" href="<?php echo esc_url($logout_url); ?>">
                    &#10148; Sign out
                </a>
            </div>
        </div>

        <!-- Change Password modal — shared across every portal page via the topbar -->
        <div class="tmp-modal-overlay" data-tmp-change-password-modal style="display:none;">
            <div class="tmp-modal-card" role="dialog" aria-modal="true" aria-labelledby="tmp-cp-modal-title">
                <button type="button" class="tmp-modal-close" data-tmp-change-password-close aria-label="Close">&times;</button>
                <h3 id="tmp-cp-modal-title">Change Password</h3>
                <p class="tmp-modal-hint">Choose a strong password. After saving, other active sessions will be signed out automatically.</p>
                <form data-tmp-change-password-form class="tmp-form" style="background:none;border:none;padding:0;">
                    <label class="tmp-wide">
                        Current password
                        <div class="tmp-pw-field-wrap">
                            <input type="password" name="current_password" required autocomplete="current-password" />
                            <button type="button" class="tmp-pw-reveal" data-pw-reveal tabindex="-1" aria-label="Show password">
                                <svg class="tmp-eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="tmp-eye-shut" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </label>
                    <label class="tmp-wide">
                        New password
                        <div class="tmp-pw-field-wrap">
                            <input type="password" name="new_password" required autocomplete="new-password" minlength="8" data-tmp-new-password />
                            <button type="button" class="tmp-pw-reveal" data-pw-reveal tabindex="-1" aria-label="Show password">
                                <svg class="tmp-eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="tmp-eye-shut" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div data-tmp-pw-strength style="margin-top:8px;">
                            <div style="display:flex;gap:4px;">
                                <div data-pw-bar="1" style="height:3px;flex:1;border-radius:2px;background:var(--tmp-line);transition:background .2s;"></div>
                                <div data-pw-bar="2" style="height:3px;flex:1;border-radius:2px;background:var(--tmp-line);transition:background .2s;"></div>
                                <div data-pw-bar="3" style="height:3px;flex:1;border-radius:2px;background:var(--tmp-line);transition:background .2s;"></div>
                                <div data-pw-bar="4" style="height:3px;flex:1;border-radius:2px;background:var(--tmp-line);transition:background .2s;"></div>
                            </div>
                            <p data-pw-strength-label style="font-size:0.78rem;margin:5px 0 0;color:var(--tmp-muted);min-height:1.2em;"></p>
                        </div>
                    </label>
                    <label class="tmp-wide">
                        Confirm new password
                        <div class="tmp-pw-field-wrap">
                            <input type="password" name="confirm_password" required autocomplete="new-password" />
                            <button type="button" class="tmp-pw-reveal" data-pw-reveal tabindex="-1" aria-label="Show password">
                                <svg class="tmp-eye-open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="tmp-eye-shut" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </label>
                    <div class="tmp-form-actions tmp-wide" style="margin-top:4px;">
                        <button type="button" class="tmp-button tmp-secondary" data-tmp-change-password-close>Cancel</button>
                        <button class="tmp-button tmp-primary" type="submit" style="gap:8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Update Password
                        </button>
                        <span data-tmp-change-password-status style="font-size:13px;align-self:center;"></span>
                    </div>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [tm_feedback_form] — public speech feedback page.
     * URL: /speech-feedback/?aid=42&hash=abc123
     */
    public static function feedback_form() {
        self::enqueue();

        $aid  = isset($_GET['aid'])  ? absint($_GET['aid'])                             : 0;
        $hash = isset($_GET['hash']) ? sanitize_text_field(wp_unslash($_GET['hash']))   : '';

        if (!$aid || !$hash || !TMP_Repository::validate_feedback_hash($aid, $hash)) {
            ob_start();
            ?>
            <div class="tmp-portal">
                <div class="tmp-panel" style="max-width:620px;margin:0 auto;text-align:center;padding:40px 24px;">
                    <p style="font-size:2.4rem;margin:0 0 16px;">&#128279;</p>
                    <h2 style="margin:0 0 10px;">Link Invalid or Expired</h2>
                    <p style="color:var(--tmp-muted);margin:0;">This feedback link is not valid.<br>Ask the VPE or SAA to share a fresh link.</p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-feedback-page data-tmp-feedback-aid="<?php echo esc_attr($aid); ?>" data-tmp-feedback-hash="<?php echo esc_attr($hash); ?>">
            <div class="tmp-panel" style="max-width:620px;margin:0 auto;">
                <p class="tmp-eyebrow" style="color:var(--tmp-teal);">Speech Feedback</p>
                <div data-tmp-feedback-header>
                    <p style="color:var(--tmp-muted);">Loading…</p>
                </div>
                <div data-tmp-feedback-body style="display:none;">
                    <div style="background:#fff3e0;border:1px solid #ffcc02;border-radius:6px;padding:10px 14px;margin:14px 0;font-size:0.85rem;">
                        <strong>Please note:</strong> Your feedback will be shared with the speaker, VPE, and their mentor.
                    </div>
                    <form data-tmp-feedback-form>
                        <label style="display:block;margin-bottom:14px;">
                            <span style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                                Your name
                                <label style="font-size:0.82rem;font-weight:400;cursor:pointer;">
                                    <input type="checkbox" data-tmp-feedback-anon style="margin-right:4px;" /> Submit anonymously
                                </label>
                            </span>
                            <input type="text" name="respondent_name" placeholder="Your name" style="display:block;width:100%;" data-tmp-feedback-name />
                        </label>
                        <label style="display:block;margin-bottom:16px;">
                            Your feedback
                            <textarea name="feedback_text" rows="6" required placeholder="Share your thoughts on the speech…" style="display:block;width:100%;margin-top:4px;padding:8px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;"></textarea>
                        </label>
                        <button class="tmp-button tmp-primary" type="submit" style="width:100%;">Submit Feedback</button>
                        <p data-tmp-feedback-status style="margin-top:10px;font-size:0.85rem;"></p>
                    </form>
                    <div data-tmp-feedback-done style="display:none;text-align:center;padding:20px 0;">
                        <p style="font-size:2rem;">&#10003;</p>
                        <p style="font-weight:600;color:var(--tmp-teal);">Thank you! Your feedback has been submitted.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [tm_voting] — standalone public voting page.
     * Members or guests open this URL to cast their vote when a poll is active.
     */
    public static function voting_page() {
        self::enqueue();

        $mid  = isset($_GET['mid'])  ? (int) $_GET['mid']                             : 0;
        $hash = isset($_GET['hash']) ? sanitize_text_field(wp_unslash($_GET['hash'])) : '';

        if (!$mid || !TMP_Repository::validate_vote_hash($mid, $hash)) {
            ob_start();
            ?>
            <div class="tmp-portal tmp-vote-page">
                <div class="tmp-panel" style="max-width:620px;margin:0 auto;text-align:center;padding:40px 24px;">
                    <p style="font-size:2.4rem;margin:0 0 16px;">&#128279;</p>
                    <h2 style="margin:0 0 10px;">Link Invalid</h2>
                    <p style="color:var(--tmp-muted);margin:0;">This voting link is not valid.<br>Ask the SAA or VPE to share the correct link for today&rsquo;s meeting.</p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div class="tmp-portal tmp-vote-page" data-tmp-vote-page data-tmp-meeting-id="<?php echo (int) $mid; ?>">
            <div class="tmp-panel" style="max-width:620px;margin:0 auto;">
                <p class="tmp-eyebrow" style="color:var(--tmp-teal);">Live Voting</p>
                <h2 style="margin:4px 0 16px;" data-tmp-vote-page-title>Cast Your Vote</h2>
                <div data-tmp-vote-page-body>
                    <p style="color:var(--tmp-muted);">Checking for active poll…</p>
                </div>
                <div data-tmp-vote-page-status style="margin-top:16px;font-size:0.9rem;font-weight:600;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [tm_meeting_hub] — public "Meeting Hub" page.
     * One static, shareable link (no per-meeting hash) that always shows whichever
     * meeting is currently published: each speaker's feedback link, plus the
     * Moment of Glory vote link, which lights up once the poll opens. Replaces
     * having to hand out separate feedback + vote links every meeting.
     */
    public static function meeting_hub_page() {
        self::enqueue();

        ob_start();
        ?>
        <div class="tmp-portal tmp-hub-page" data-tmp-hub-page>
            <div class="tmp-panel" style="max-width:640px;margin:0 auto;">
                <p class="tmp-eyebrow" style="color:var(--tmp-teal);">Meeting Hub</p>
                <div data-tmp-hub-body>
                    <p style="color:var(--tmp-muted);">Loading…</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
