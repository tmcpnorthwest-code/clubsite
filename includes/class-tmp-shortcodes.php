<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Shortcodes {
    public static function init() {
        add_shortcode('tm_member_login',    [__CLASS__, 'member_login']);
        add_shortcode('tm_member_dashboard',[__CLASS__, 'member_dashboard']);
        add_shortcode('tm_admin_portal',    [__CLASS__, 'admin_portal']);
        add_shortcode('tm_vp_education',    [__CLASS__, 'vp_education']);
        add_shortcode('tm_recognition_wall',[__CLASS__, 'recognition_wall']);
        add_shortcode('tm_public_dashboard',[__CLASS__, 'public_dashboard']);
        add_shortcode('tm_voting',          [__CLASS__, 'voting_page']);
        add_shortcode('tm_feedback_form',   [__CLASS__, 'feedback_form']);
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

    private static function enqueue() {
        wp_enqueue_style('tmp-portal');
        wp_enqueue_script('tmp-portal');
        wp_localize_script('tmp-portal', 'TMPortal', [
            'restUrl'        => esc_url_raw(rest_url('toastmasters/v1')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'standardRoles'  => TMP_Repository::get_standard_roles(),
            'roleGateLevels' => TMP_Repository::get_current_gate_levels(),
            'loginUrl'       => wp_login_url(get_permalink()),
            'logoutUrl'      => wp_logout_url(home_url('/')),
            'clubName'       => get_bloginfo('name'),
            'clubVenue'      => get_option('tmp_default_venue', ''),
            'logoUrl'        => get_site_icon_url(80) ?: '',
            'currentUser'    => is_user_logged_in() ? [
                'id'               => get_current_user_id(),
                'name'             => wp_get_current_user()->display_name,
                'email'            => wp_get_current_user()->user_email,
                'canManageMembers' => current_user_can('tmp_manage_members'),
                'canManageMeetings'=> current_user_can('tmp_manage_meetings'),
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

        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-member-dashboard>
            <?php echo self::portal_topbar(); ?>
            <div class="tmp-panel">
                <p class="tmp-eyebrow">Member dashboard</p>
                <div class="tmp-member-header">
                    <div class="tmp-member-header-info">
                        <h2 data-tmp-member-name>Loading dashboard</h2>
                        <p data-tmp-member-summary></p>
                        <dl class="tmp-profile-list" style="margin-top:14px;">
                            <div><dt>Status</dt><dd data-tmp-state></dd></div>
                            <div><dt>Current Project</dt><dd data-tmp-project></dd></div>
                            <div><dt>Paid Until</dt><dd data-tmp-paid-until></dd></div>
                        </dl>
                    </div>
                    <div class="tmp-member-header-progress">
                        <div class="tmp-card-head">
                            <h3 style="margin:0">Pathways Progress</h3>
                            <span data-tmp-progress style="color:var(--tmp-teal);font-weight:900;">0%</span>
                        </div>
                        <div class="tmp-progress" style="margin-top:10px;"><span data-tmp-progress-bar></span></div>
                        <ol class="tmp-levels" data-tmp-levels></ol>
                    </div>
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
                        <div class="tmp-m-item" data-m="joined">Joined</div>
                        <div class="tmp-m-item" data-m="orientation">Orientation</div>
                        <div class="tmp-m-item" data-m="first_role">First Role</div>
                        <div class="tmp-m-item" data-m="icebreaker_draft">Ice Breaker Draft</div>
                        <div class="tmp-m-item" data-m="icebreaker_delivered">Ice Breaker Delivered</div>
                        <div class="tmp-m-item" data-m="level1_completed">Level 1 Completed</div>
                    </div>
                </article>

                <!-- Mentor card — populated by JS -->
                <article class="tmp-panel" data-tmp-mentor-card>
                    <h3>My Mentor</h3>
                    <div data-tmp-mentor-info><p style="color:var(--tmp-muted)">Loading...</p></div>
                    <div data-tmp-mentorship-checklist style="margin-top:16px;"></div>
                </article>

                <!-- Your Progress to Level X — compact summary (only L1–L3) -->
                <article class="tmp-panel" data-tmp-progress-summary-panel style="display:none;">
                    <h3 style="margin-bottom:16px;">Your Progress to Level <span data-tmp-progress-level></span></h3>
                    <div data-tmp-progress-summary>Loading...</div>
                </article>

                <!-- Next Action (only shown for L4+) -->
                <article class="tmp-panel" data-tmp-next-action-panel>
                    <h3>Next Action</h3>
                    <p data-tmp-next-action></p>
                </article>

                <!-- Meeting Activity — expandable card, collapsed by default -->
                <article class="tmp-panel tmp-wide" data-tmp-meeting-card>
                    <button class="tmp-collapsible-toggle" data-tmp-meeting-toggle aria-expanded="false">
                        Meeting Activity
                        <span data-tmp-meeting-badge class="tmp-badge" style="display:none;"></span>
                        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                    </button>
                    <div data-tmp-meeting-body style="display:none;">

                        <section class="tmp-meeting-section" data-tmp-active-requests-section>
                            <h4>Your Active Requests</h4>
                            <div data-tmp-active-requests>Loading requests...</div>
                        </section>

                        <section class="tmp-meeting-section" data-tmp-assigned-roles-section style="display:none;">
                            <h4>Your Assigned Roles</h4>
                            <div data-tmp-assigned-roles>Loading assignments...</div>
                        </section>

                        <section class="tmp-meeting-section">
                            <h4>Role History</h4>
                            <div data-tmp-role-history>Loading history...</div>
                        </section>

                        <!-- Request a Role — top-level section -->
                        <section class="tmp-meeting-section" data-tmp-request-section>
                            <h4>Request a Role</h4>
                            <form class="tmp-form" data-tmp-member-request-form style="background:none;border:none;padding:0;">
                                <div data-tmp-deadline-info style="margin-bottom:12px;"></div>
                                <div data-tmp-dupe-request-warning style="margin-bottom:12px;"></div>
                                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                                    <label>Meeting
                                        <select name="meeting_id" required data-tmp-req-meeting-select></select>
                                    </label>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;" data-tmp-role-selection-section>
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

                        <!-- Your Progress (shown in upper panel, old sections removed) -->
                        <section class="tmp-meeting-section" data-tmp-level-journey-panel style="display:none;">
                            <h4 style="margin-top:0;">Your Progress to Level <span data-tmp-next-level></span></h4>
                            <div data-tmp-level-status></div>
                            <div data-tmp-level-journey></div>
                        </section>

                    </div><!-- /data-tmp-meeting-body -->
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

            <!-- Recognition history — shown by JS if member has any awards -->
            <div class="tmp-panel" data-tmp-my-recognition style="display:none;">
                <p class="tmp-eyebrow">Recognition</p>
                <h3>My Awards</h3>
                <div data-tmp-my-recognition-list></div>
            </div>

            <!-- Mentor dashboard (visible only if current user is a mentor) -->
            <div class="tmp-panel" data-tmp-mentor-dashboard style="display:none;">
                <p class="tmp-eyebrow">Mentor Dashboard</p>
                <h3>My Mentees</h3>
                <div data-tmp-mentee-list>Loading mentees...</div>
            </div>

            <!-- Change Password -->
            <div class="tmp-panel" data-tmp-change-password-panel>
                <button class="tmp-collapsible-toggle" data-tmp-change-password-toggle aria-expanded="false" style="width:100%;text-align:left;">
                    <span style="display:flex;align-items:center;gap:10px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Change Password
                    </span>
                    <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                </button>
                <div data-tmp-change-password-body style="display:none;margin-top:20px;">
                    <p class="tmp-eyebrow">Account Security</p>
                    <p style="font-size:0.88rem;color:var(--tmp-muted);margin:0 0 20px;">Choose a strong password. After saving, other active sessions will be signed out automatically.</p>
                    <form data-tmp-change-password-form class="tmp-form" style="background:none;border:none;padding:0;max-width:460px;">
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
                            <button class="tmp-button tmp-primary" type="submit" style="gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Update Password
                            </button>
                            <span data-tmp-change-password-status style="font-size:13px;align-self:center;"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function admin_portal() {
        self::enqueue();
        if (!current_user_can('tmp_manage_members')) {
            return self::restricted('Club Admin', 'You need a Toastmasters Admin or Administrator account to manage members.');
        }

        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-admin>
            <?php echo self::portal_topbar(); ?>
            <div class="tmp-panel">
                <p class="tmp-eyebrow">Club admin</p>
                <h2>Manage members, Pathways, levels, and state</h2>
                <p>Member records saved here drive each member dashboard.</p>
            </div>
            <form class="tmp-panel tmp-form" data-tmp-import-form>
                <div class="tmp-wide">
                    <p class="tmp-eyebrow">One-time member import</p>
                    <h3>Upload Toastmasters membership CSV</h3>
                    <p>Customer ID becomes the WordPress username. Credentials like PM1 or DL3 become Pathway and Level. Blank credentials become No pathway registered.</p>
                </div>
                <label>Membership CSV <input type="file" name="file" accept=".csv,text/csv" required /></label>
                <label>Default password <input type="text" name="default_password" value="Welcome@123" minlength="8" required /></label>
                <div class="tmp-form-actions tmp-wide">
                    <button class="tmp-button tmp-primary" type="submit">Import Members</button>
                    <span class="tmp-inline-status" data-tmp-import-status></span>
                </div>
            </form>
            <section class="tmp-panel">
                <div class="tmp-card-head">
                    <h3>Members</h3>
                    <span data-tmp-member-count>0 records</span>
                </div>
                <div class="tmp-admin-filters" style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;background:#f9f9f9;padding:12px;border-radius:4px;border:1px solid #eee;">
                    <input type="text" data-tmp-admin-search placeholder="Search by name or email..." style="flex:1;min-width:200px;">
                    <select data-tmp-admin-status>
                        <option value="all">All (Paid/Unpaid)</option>
                        <option value="Paid">Paid Only</option>
                        <option value="Unpaid">Unpaid Only</option>
                    </select>
                    <select data-tmp-admin-level>
                        <option value="all">All Levels</option>
                        <option value="0">Level 0 (Enrolled)</option>
                        <option value="1">Level 1</option>
                        <option value="2">Level 2</option>
                        <option value="3">Level 3</option>
                        <option value="4">Level 4</option>
                        <option value="5">Level 5</option>
                    </select>
                    <select data-tmp-admin-group-by>
                        <option value="none">No Grouping</option>
                        <option value="state">Group by Status</option>
                        <option value="level">Group by Level</option>
                        <option value="pathway">Group by Pathway</option>
                    </select>
                </div>
                <div style="margin-bottom:10px;">
                    <button class="tmp-small-button" type="button" data-tmp-admin-members-toggle>Show Members</button>
                </div>
                <div class="tmp-table-wrap">
                    <table class="tmp-table">
                        <thead>
                            <tr>
                                <th data-sort-col="name">Name <span class="tmp-sort-ind">▲</span></th>
                                <th>Customer ID</th>
                                <th>Email</th>
                                <th>Pathway</th>
                                <th data-sort-col="level">Level <span class="tmp-sort-ind">↕</span></th>
                                <th>State</th>
                                <th>Recent</th>
                                <th>Exempt?</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-tmp-member-table></tbody>
                    </table>
                </div>
            </section>
            <section class="tmp-panel">
                <p class="tmp-eyebrow">Homepage spotlight</p>
                <h3>New Member Spotlight</h3>
                <p>Select a member and add a welcome message. This card appears on the homepage after "Our Meetings in Action".</p>
                <form class="tmp-form" data-tmp-spotlight-form>
                    <label class="tmp-wide">
                        Member
                        <select data-tmp-spotlight-member required>
                            <option value="">— pick a member —</option>
                        </select>
                    </label>
                    <label class="tmp-wide">
                        Welcome blurb
                        <textarea data-tmp-spotlight-blurb rows="3" placeholder="e.g. Please join us in welcoming Priya — a software engineer with a passion for public speaking!"></textarea>
                    </label>
                    <label class="tmp-wide">
                        Photo URL
                        <input type="url" data-tmp-spotlight-photo placeholder="https://…">
                        <small>Upload a photo via WP Admin &rarr; Media &rarr; Add New, then copy the file URL here.</small>
                    </label>
                    <label class="tmp-wide" style="flex-direction:row;align-items:center;gap:10px;">
                        <input type="checkbox" data-tmp-spotlight-active>
                        Show this spotlight on the homepage
                    </label>
                    <div class="tmp-form-actions tmp-wide">
                        <button class="tmp-button tmp-primary" type="submit">Save Spotlight</button>
                        <span class="tmp-inline-status" data-tmp-spotlight-status></span>
                    </div>
                </form>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function vp_education() {
        self::enqueue();
        if (!current_user_can('tmp_manage_meetings')) {
            return self::restricted('VP Education', 'You need VP Education, Toastmasters Admin, or Administrator access.');
        }

        ob_start();
        ?>
        <div class="tmp-portal" data-tmp-vpe>
            <?php echo self::portal_topbar(); ?>

            <!-- Tab navigation -->
            <nav class="tmp-tab-nav" data-tmp-tab-nav>
                <button class="tmp-tab-btn" data-tab="members">
                    Members
                    <span class="tmp-tab-badge" data-tab-badge="members" style="display:none;"></span>
                </button>
                <button class="tmp-tab-btn" data-tab="meetings">Meetings</button>
                <button class="tmp-tab-btn" data-tab="recognition">Recognition</button>
            </nav>

            <!-- ══ MEMBERS TAB ══ -->
            <div data-tab-body="members">

                <!-- Level Advancement Requests — collapsible, auto-expands when there are requests -->
                <section class="tmp-panel" data-tmp-vpe-levelup-queue>
                    <button class="tmp-collapsible-toggle" data-tmp-levelup-toggle aria-expanded="false">
                        Level Advancement Requests
                        <span data-tmp-levelup-pending-count class="tmp-badge" style="display:none;margin-left:6px;"></span>
                        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                    </button>
                    <div data-tmp-levelup-request-list style="display:none;padding-top:12px;">
                        <p style="color:var(--tmp-muted);font-size:0.88rem;">No pending requests.</p>
                    </div>
                </section>

                <!-- Pending Role Requests — collapsible, auto-expands when there are requests -->
                <section class="tmp-panel">
                    <button class="tmp-collapsible-toggle" data-tmp-requests-toggle aria-expanded="false">
                        Pending Role Requests
                        <span data-tmp-request-count class="tmp-badge" style="display:none;margin-left:6px;"></span>
                        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                    </button>
                    <div data-tmp-requests-body style="display:none;padding-top:12px;">
                        <div style="margin-bottom:12px;">
                            <button class="tmp-button tmp-primary" data-tmp-approve-all-btn style="display:none;">Approve All Recommended</button>
                        </div>
                        <div data-tmp-vpe-requests>Loading requests...</div>
                    </div>
                </section>

                <!-- Unified member table: all levels, speech/role progress for L1–L3, mentor column -->
                <section class="tmp-panel">
                    <div class="tmp-card-head">
                        <h3>Members</h3>
                        <div style="display:flex;gap:12px;align-items:center;">
                            <span data-tmp-vpe-ready-count style="color:var(--tmp-teal);font-weight:700;font-size:0.9rem;"></span>
                            <span data-tmp-vpe-member-count style="color:var(--tmp-muted);font-size:0.88rem;">0 members</span>
                        </div>
                    </div>
                    <div data-tmp-unmentored-alert></div>
                    <div class="tmp-admin-filters" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;background:#f9f9f9;padding:10px;border-radius:4px;border:1px solid #eee;">
                        <input type="text" data-tmp-vpe-search placeholder="Search by name or email..." style="flex:1;min-width:180px;">
                        <select data-tmp-vpe-pathway>
                            <option value="all">All Pathways</option>
                            <option>No pathway registered</option>
                            <option>Enrolled</option>
                            <option>Dynamic Leadership</option>
                            <option>Effective Coaching</option>
                            <option>Engaging Humor</option>
                            <option>Innovative Planning</option>
                            <option>Motivational Strategies</option>
                            <option>Persuasive Influence</option>
                            <option>Presentation Mastery</option>
                            <option>Strategic Relationships</option>
                            <option>Team Collaboration</option>
                            <option>Visionary Communication</option>
                            <option>Distinguished Toastmaster</option>
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
                            <option value="all">All Members</option>
                            <option value="none">No Mentor Assigned</option>
                            <option value="assigned">Has Mentor</option>
                        </select>
                        <select data-tmp-vpe-lp-status>
                            <option value="all">All statuses</option>
                            <option value="ready">🟢 Ready to advance</option>
                            <option value="in_progress">🟡 In progress</option>
                            <option value="stuck">🔴 Stuck</option>
                        </select>
                    </div>
                    <div class="tmp-table-wrap">
                        <table class="tmp-table" style="width:100%;">
                            <thead>
                                <tr>
                                    <th data-sort-col="name" class="tmp-sortable">Name <span class="tmp-sort-ind">▲</span></th>
                                    <th data-sort-col="level" class="tmp-sortable">Level <span class="tmp-sort-ind">↕</span></th>
                                    <th>Speeches</th>
                                    <th>Roles</th>
                                    <th>Mentor</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-tmp-unified-rows></tbody>
                        </table>
                    </div>
                </section>

            </div><!-- /tab-body members -->

            <!-- ══ MEETINGS TAB ══ -->
            <div data-tab-body="meetings" style="display:none;">

                <!-- hidden compatibility stubs kept for JS references -->
                <span data-tmp-meeting-count style="display:none;">0 meetings</span>
                <div data-tmp-meetings-compact-list style="display:none;"></div>

                <section class="tmp-panel">
                    <h3 style="margin:0 0 14px;">Meeting Management</h3>
                    <label style="display:block;margin-bottom:14px;">
                        Meeting
                        <select name="meeting_id" required data-tmp-meeting-select style="display:block;width:100%;max-width:520px;margin-top:4px;"></select>
                    </label>

                    <!-- Meeting form — shown collapsed in edit mode or expanded in create mode -->
                    <div data-tmp-meeting-form-wrap style="display:none;margin-bottom:14px;">
                        <button class="tmp-collapsible-toggle" data-tmp-meeting-form-toggle aria-expanded="false" style="width:100%;text-align:left;">
                            <span data-tmp-meeting-form-label>Edit Meeting</span>
                            <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                        </button>
                        <div data-tmp-meeting-form-body style="display:none;margin-top:14px;">
                        <form class="tmp-form" data-tmp-meeting-form>
                            <input type="hidden" name="id" />
                            <label>Meeting date <input type="date" name="meeting_date" required /></label>
                            <label>Start time <input type="time" name="start_time" value="18:30" /></label>
                            <label>Total Duration (mins) <input type="number" name="total_duration" value="120" min="0" /></label>
                            <label>Requests deadline <input type="datetime-local" name="requests_close_at" /></label>
                            <label>Theme <input name="theme" required placeholder="Meeting theme" /></label>
                            <label>Venue or link <input name="venue" placeholder="Room, address, or meeting link" /></label>
                            <div class="tmp-wide tmp-roles-setup" style="margin:10px 0;padding:10px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                                <p class="tmp-eyebrow" data-tmp-roles-setup-label>Role Slots</p>
                                <p style="font-size:12px;color:#555;margin:0 0 8px;">
                                    <span data-tmp-roles-setup-hint>Using standard agenda with all roles.</span>
                                    <button type="button" class="tmp-link-button" data-tmp-customise-roles style="margin-left:6px;font-size:12px;">Customise roles ▾</button>
                                </p>
                                <div data-tmp-roles-grid style="display:none;grid-template-columns:1fr 1fr 1fr;gap:5px;margin-bottom:10px;">
                                    <?php foreach (TMP_Repository::get_standard_roles() as $fullName => $shortName) : ?>
                                        <label><input type="checkbox" name="roles[]" value="<?php echo esc_attr($fullName); ?>" checked> <?php echo esc_html($shortName); ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <label>Number of Speech Slots <input type="number" name="speech_slots" value="3" min="0" max="10" /></label>
                                <p style="font-size:11px;color:#666;margin-top:5px;">* This will automatically create matching Evaluator slots. Table Topics Speakers are added live via the Voting panel during the meeting.</p>
                            </div>
                            <label class="tmp-wide">Agenda notes <textarea name="agenda_notes" rows="2"></textarea></label>
                            <div class="tmp-form-actions tmp-wide">
                                <button class="tmp-button tmp-primary" type="submit">Save Meeting</button>
                                <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-meeting>Clear</button>
                                <button class="tmp-button tmp-danger" type="button" data-tmp-delete-meeting style="display:none;margin-left:6px;">Delete Meeting</button>
                                <span class="tmp-inline-status" data-tmp-meeting-save-status style="margin-left:10px;font-size:13px;"></span>
                            </div>
                        </form>
                        </div>
                    </div>

                    <!-- Role Assignment collapsible -->
                    <div data-tmp-role-assignment-wrap style="display:none;margin-top:4px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <button class="tmp-collapsible-toggle" data-tmp-role-assignment-toggle aria-expanded="true" style="flex:1;text-align:left;">
                                <span>Role Assignment</span>
                                <span class="tmp-chevron" aria-hidden="true" style="transform:rotate(90deg);">&#9658;</span>
                            </button>
                            <button type="button" class="tmp-small-button" data-tmp-rebuild-agenda title="Rebuild the agenda in the standard prescribed order, preserving all member assignments">Rebuild Agenda</button>
                        </div>
                        <div data-tmp-role-assignment-body style="display:block;margin-top:14px;">
                            <div data-tmp-role-status-panel></div>
                        </div>
                    </div>

                    <!-- Agenda collapsible — shows selected meeting's timeline read-only -->
                    <div data-tmp-meeting-agenda-wrap style="display:none;margin-top:16px;">
                        <button class="tmp-collapsible-toggle" data-tmp-agenda-toggle aria-expanded="false" style="width:100%;text-align:left;">
                            <span>Agenda</span>
                            <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
                        </button>
                        <div data-tmp-agenda-body style="display:none;margin-top:14px;">
                            <div data-tmp-meeting-list></div>
                        </div>
                    </div>
                </section>

                <form data-tmp-assignment-form style="display:none;">
                    <input type="hidden" name="id" />
                    <input type="hidden" name="role_name" />
                    <input type="hidden" name="meeting_id" />
                    <input type="text" name="speech_title" placeholder="Speech title (optional)" data-tmp-speech-title-wrapper style="display:none;width:100%;margin-bottom:6px;" />
                    <select name="presentation_series" data-tmp-pres-series-wrapper style="display:none;">
                        <option value="">Not applicable</option>
                        <option value="Successful Club Series">Successful Club Series</option>
                        <option value="Better Speaker Series">Better Speaker Series</option>
                        <option value="Leadership Excellence Series">Leadership Excellence Series</option>
                    </select>
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
                    <div data-tmp-timing-wrap style="display:none;"></div>
                    <div data-tmp-role-suggestions style="display:none;"></div>
                </form>

                <!-- Voting panel -->
                <section class="tmp-panel" data-tmp-voting-panel>
                    <div class="tmp-card-head">
                        <div>
                            <p class="tmp-eyebrow">Meeting Day</p>
                            <h3 style="margin:0;">Voting &amp; Table Topics</h3>
                        </div>
                        <span data-tmp-voting-meeting-label style="color:var(--tmp-muted);font-size:0.85rem;"></span>
                    </div>
                    <p style="color:var(--tmp-muted);font-size:0.88rem;margin-top:6px;">
                        Select today&rsquo;s meeting to manage live voting. Add Table Topics speakers as they step up — the voting form on the home page updates automatically.
                    </p>
                    <label style="display:block;margin-bottom:16px;">
                        Meeting
                        <select data-tmp-voting-meeting-select style="display:block;width:100%;margin-top:4px;">
                            <option value="">— select meeting —</option>
                        </select>
                    </label>
                    <div data-tmp-tt-entry style="display:none;">
                        <p class="tmp-eyebrow" style="margin-top:20px;">Table Topics Speakers</p>
                        <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:6px;">
                            <div style="flex:1;">
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

                        <!-- Voting link — auto-generated on meeting select, valid 48 h -->
                        <div style="margin-top:16px;padding:12px;background:#f0f8ff;border-radius:6px;border:1px solid #cce5ff;">
                            <p class="tmp-eyebrow" style="margin:0 0 4px;color:var(--tmp-teal);">Moment of Glory Voting Link</p>
                            <p style="font-size:0.82rem;color:var(--tmp-muted);margin:0 0 8px;">Share with attendees — they can vote without logging in. Valid for 48 hours.</p>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <code data-tmp-vote-link-url style="flex:1;background:#fff;padding:6px 8px;border:1px solid #ccc;border-radius:4px;font-size:0.82rem;word-break:break-all;color:var(--tmp-muted);">Select a meeting above…</code>
                                <button type="button" class="tmp-small-button" data-tmp-copy-vote-link>Copy Link</button>
                            </div>
                            <p data-tmp-vote-link-expiry style="font-size:0.78rem;color:var(--tmp-muted);margin:6px 0 0;"></p>
                        </div>
                    </div>

                    <!-- Rate the Speaker -->
                    <div data-tmp-rate-speaker-section style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid var(--tmp-line);">
                        <p class="tmp-eyebrow" style="margin:0 0 4px;">Rate the Speaker</p>
                        <p style="font-size:0.82rem;color:var(--tmp-muted);margin:0 0 12px;">Share feedback links with attendees during or after each speech. Review responses and email the rollup to each speaker, VPE, and mentor.</p>
                        <div data-tmp-speaker-feedback-list></div>
                    </div>

                    <!-- Post-meeting actions — Declare Winners, Results, Send Emails all in one row -->
                    <div data-tmp-postmeeting-actions style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid var(--tmp-line);">
                        <p class="tmp-eyebrow" style="margin:0 0 10px;">Post-Meeting Actions</p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button class="tmp-button" data-tmp-declare-winners-btn style="background:#1e4a6e;color:#fff;border:none;">&#127942; Declare Winners</button>
                            <button class="tmp-small-button" data-tmp-voting-results-btn>Show Live Results</button>
                            <button class="tmp-button tmp-secondary" data-tmp-send-speaker-feedback-btn>&#9993; Send Feedback Emails</button>
                            <span data-tmp-speaker-feedback-email-status style="font-size:0.82rem;color:var(--tmp-muted);"></span>
                        </div>
                        <div data-tmp-voting-results style="display:none;margin-top:12px;"></div>
                    </div>
                </section>

                <!-- Meeting wrap-up -->
                <section class="tmp-panel" data-tmp-wrapup-panel>
                    <div class="tmp-card-head">
                        <div>
                            <p class="tmp-eyebrow">After the Meeting</p>
                            <h3 style="margin:0;">Meeting Wrap-Up</h3>
                        </div>
                        <span data-tmp-wrapup-badge style="display:none;background:#e8f5e9;color:#2e7d32;border:1px solid #4caf50;border-radius:20px;padding:3px 10px;font-size:0.78rem;font-weight:700;">✓ Completed</span>
                    </div>
                    <p style="color:var(--tmp-muted);font-size:0.88rem;margin-top:6px;">
                        Record who attended, confirm roles performed, and finalise winners. The home page Meeting Pulse updates once you complete this.
                    </p>
                    <label style="display:block;margin-bottom:16px;">
                        Meeting
                        <select data-tmp-wrapup-meeting-select style="display:block;width:100%;margin-top:4px;">
                            <option value="">— select meeting —</option>
                        </select>
                    </label>
                    <div data-tmp-wrapup-content style="display:none;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;flex-wrap:wrap;gap:6px;">
                            <p class="tmp-eyebrow" style="margin:0;">Role Performers</p>
                            <button class="tmp-link-button" data-tmp-mark-all-present style="font-size:0.82rem;color:var(--tmp-teal);">↺ Mark all present</button>
                        </div>
                        <p style="font-size:0.82rem;color:var(--tmp-muted);margin:4px 0 10px;">
                            Everyone is present by default. Tap a row to mark absent.
                        </p>
                        <div data-tmp-role-performers-list></div>
                        <div style="margin-top:20px;">
                            <p class="tmp-eyebrow" style="margin-bottom:6px;">Also Attended <span style="font-weight:400;font-size:0.78rem;color:var(--tmp-muted);">(no assigned role)</span></p>
                            <div style="position:relative;">
                                <input type="text" data-tmp-walkin-search placeholder="Search member name…" autocomplete="off"
                                       style="width:100%;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                                <div data-tmp-walkin-dropdown style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1px solid var(--tmp-line);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:100;max-height:220px;overflow-y:auto;"></div>
                            </div>
                            <div data-tmp-walkin-list style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
                        </div>
                        <div style="margin-top:16px;">
                            <p class="tmp-eyebrow" style="margin-bottom:6px;">Guests</p>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <input type="text" data-tmp-guest-name placeholder="Guest name" style="flex:1;min-width:140px;padding:8px 10px;border:1px solid var(--tmp-line);border-radius:6px;font-size:0.88rem;" />
                                <button class="tmp-button tmp-primary" data-tmp-add-guest-btn style="flex-shrink:0;white-space:nowrap;padding:8px 14px;">+ Add Guest</button>
                            </div>
                            <div data-tmp-guests-list style="margin-top:8px;"></div>
                        </div>
                        <div data-tmp-winners-section style="margin-top:20px;">
                            <p class="tmp-eyebrow">Winners</p>
                            <p style="font-size:0.82rem;color:var(--tmp-muted);margin:4px 0 10px;">Pre-filled from voting. Uncheck to exclude.</p>
                            <div data-tmp-winners-list></div>
                        </div>
                        <div class="tmp-wrapup-actions">
                            <button class="tmp-button tmp-primary" data-tmp-complete-meeting-btn>✓ Complete Meeting</button>
                            <span data-tmp-wrapup-save-status style="font-size:0.82rem;"></span>
                        </div>
                    </div>
                </section>

            </div><!-- /tab-body meetings -->

            <!-- ══ RECOGNITION TAB ══ -->
            <div data-tab-body="recognition" style="display:none;">

                <section class="tmp-panel" data-tmp-recognition-panel>
                    <div class="tmp-card-head" style="margin-bottom:16px;">
                        <h3 style="margin:0;">Member Recognition</h3>
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
            <a class="tmp-signout-btn" href="<?php echo esc_url($logout_url); ?>">
                &#10148; Sign out
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function restricted($title, $message) {
        self::enqueue();
        ob_start();
        ?>
        <div class="tmp-portal tmp-login-card">
            <p class="tmp-eyebrow"><?php echo esc_html($title); ?></p>
            <h2>Login required</h2>
            <p><?php echo esc_html($message); ?></p>
            <a class="tmp-button tmp-primary" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Login with WordPress</a>
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

        $token = isset($_GET['tmp_vote']) ? sanitize_text_field(wp_unslash($_GET['tmp_vote'])) : '';
        if (!$token || !TMP_Repository::validate_vote_token($token)) {
            ob_start();
            ?>
            <div class="tmp-vote-page">
                <div class="tmp-panel" style="max-width:620px;margin:0 auto;text-align:center;padding:40px 24px;">
                    <p style="font-size:2.4rem;margin:0 0 16px;">&#128279;</p>
                    <h2 style="margin:0 0 10px;">Link Expired or Invalid</h2>
                    <p style="color:var(--tmp-muted);margin:0;">This voting link has expired or is not valid.<br>Ask the SAA or VPE to share a fresh link for today&rsquo;s meeting.</p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        ?>
        <div class="tmp-vote-page" data-tmp-vote-page>
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
}
