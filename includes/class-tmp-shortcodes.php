<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Shortcodes {
    public static function init() {
        // Register handles now (at WordPress 'init') so they exist when template_redirect fires.
        // wp_enqueue_scripts still re-registers them (safe no-op) for the standard WP asset pipeline.
        self::register_assets();
        add_shortcode('tm_member_login',    [__CLASS__, 'member_login']);
        add_shortcode('tm_member_dashboard',[__CLASS__, 'member_dashboard']);
        add_shortcode('tm_admin_portal',    [__CLASS__, 'admin_portal']);
        add_shortcode('tm_vp_education',    [__CLASS__, 'vp_education']);
        add_action('wp_enqueue_scripts',    [__CLASS__, 'register_assets']);
        add_action('template_redirect',     [__CLASS__, 'auth_redirects']);
    }

    public static function register_assets() {
        wp_register_style( 'tmp-portal', TMP_PLUGIN_URL . 'assets/portal.css', [], TMP_VERSION);
        wp_register_script('tmp-portal', TMP_PLUGIN_URL . 'assets/portal.js',  [], TMP_VERSION, true);
        wp_register_style( 'tmp-login',  TMP_PLUGIN_URL . 'assets/login.css',  [], TMP_VERSION);
        wp_register_script('tmp-login',  TMP_PLUGIN_URL . 'assets/login.js',   [], TMP_VERSION, true);
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
        // auth_redirects() handles logged-in users at template_redirect level.
        // If somehow we still reach here while logged in, show nothing.
        if (is_user_logged_in()) {
            return '';
        }

        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/member-dashboard/');
        $has_google  = !empty(get_option('tmp_google_client_id'));
        $lost_pw_url = wp_lostpassword_url(home_url('/member-login/'));

        wp_localize_script('tmp-login', 'TMLogin', [
            'loginUrl'       => rest_url('toastmasters/v1/login'),
            'googleUrl'      => rest_url('toastmasters/v1/auth/google'),
            'nonce'          => wp_create_nonce('wp_rest'),
            'redirectDefault' => esc_url($redirect_to),
            'hasRedirectTo'  => isset($_GET['redirect_to']),
            'hasGoogle'      => $has_google,
        ]);

        ob_start();
        ?>
        <div class="tmp-login-page">
          <div class="tmp-login-box">

            <div class="tmp-login-logo">
              <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <circle cx="18" cy="18" r="18" fill="#8f1737"/>
                <path d="M10 24 L18 12 L26 24" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <circle cx="18" cy="12" r="2.5" fill="#d69b24"/>
              </svg>
              <span class="tmp-login-club-name">Toastmasters Club of Pune North West</span>
            </div>

            <h1 class="tmp-login-title">Welcome back</h1>
            <p class="tmp-login-subtitle">Sign in to your member portal.</p>

            <div id="tmp-login-error" class="tmp-login-error" style="display:none;" role="alert" aria-live="polite"></div>

            <form id="tmp-login-form" novalidate>
              <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

              <div class="tmp-field">
                <label for="tmp-username">Username or Email</label>
                <input type="text" id="tmp-username" name="username"
                       autocomplete="username" autocapitalize="off" spellcheck="false" required>
              </div>

              <div class="tmp-field">
                <label for="tmp-password">Password</label>
                <div class="tmp-password-wrap">
                  <input type="password" id="tmp-password" name="password"
                         autocomplete="current-password" required>
                  <button type="button" class="tmp-toggle-password" aria-label="Show password" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
              </div>

              <div class="tmp-field tmp-field-row">
                <label class="tmp-remember"><input type="checkbox" name="remember"> Remember me</label>
                <a href="<?php echo esc_url($lost_pw_url); ?>">Forgot password?</a>
              </div>

              <button type="submit" class="tmp-login-submit">Sign in</button>
            </form>

            <?php if ($has_google) : ?>
            <div class="tmp-login-divider"><span>or</span></div>
            <button id="tmp-google-btn" class="tmp-google-button" type="button">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              Sign in with Google
            </button>
            <?php endif; ?>

          </div>
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

                <article class="tmp-panel">
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

                        <section class="tmp-meeting-section">
                            <h4>Active Requests</h4>
                            <div data-tmp-active-requests>Loading requests...</div>
                        </section>

                        <section class="tmp-meeting-section">
                            <h4>Request History</h4>
                            <div data-tmp-request-history>Loading history...</div>
                        </section>

                        <section class="tmp-meeting-section">
                            <h4>Your Pending Requests</h4>
                            <div data-tmp-pending-requests>Loading pending requests...</div>
                        </section>

                        <section class="tmp-meeting-section">
                            <h4>Role History</h4>
                            <div data-tmp-role-history>Loading history...</div>
                        </section>

                        <section class="tmp-meeting-section" data-tmp-level-journey-panel>
                            <h4>Required Roles — Level Journey</h4>
                            <div data-tmp-level-journey>Loading...</div>
                        </section>

                        <section class="tmp-meeting-section">
                            <h4>Smart Recommendations</h4>
                            <div class="tmp-rec-grid" data-tmp-recommendations>Loading suggestions...</div>
                        </section>

                        <section class="tmp-meeting-section" data-tmp-request-section>
                            <h4>Available Meeting Slots</h4>
                            <form class="tmp-panel tmp-form" data-tmp-member-request-form style="margin-top:10px; border:1px dashed #ccc;">
                                <p class="tmp-eyebrow">Request a role</p>
                                <div data-tmp-deadline-info></div>
                                <div data-tmp-dupe-request-warning></div>
                                <div class="tmp-grid" style="grid-template-columns: 1fr; gap: 10px;">
                                    <label>1. Select Meeting
                                        <select name="meeting_id" required data-tmp-req-meeting-select></select>
                                    </label>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;" data-tmp-role-selection-section>
                                        <label>Priority 1 <select name="priorities[]" required data-tmp-req-role-select></select></label>
                                        <label>Priority 2 <select name="priorities[]" data-tmp-req-role-select></select></label>
                                        <label>Priority 3 <select name="priorities[]" data-tmp-req-role-select></select></label>
                                    </div>
                                </div>
                                <div data-tmp-role-info class="tmp-wide"></div>
                                <div style="margin-top:10px; text-align:right;">
                                    <button class="tmp-button tmp-primary" type="submit">Submit Request</button>
                                </div>
                            </form>
                        </section>

                    </div><!-- /data-tmp-meeting-body -->
                </article>

            </div><!-- .tmp-grid -->

            <!-- Mentor dashboard (visible only if current user is a mentor) -->
            <div class="tmp-panel" data-tmp-mentor-dashboard style="display:none;">
                <p class="tmp-eyebrow">Mentor Dashboard</p>
                <h3>My Mentees</h3>
                <div data-tmp-mentee-list>Loading mentees...</div>
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
            <div class="tmp-panel">
                <p class="tmp-eyebrow">VP Education</p>
                <h2>Schedule roles, speeches, and meeting agendas</h2>
                <p>Create meetings, assign roles, add speech titles, and publish a usable agenda view.</p>
            </div>

            <section class="tmp-panel">
                <div class="tmp-card-head">
                    <h3>Pending Role Requests</h3>
                    <span data-tmp-request-count>0 pending</span>
                </div>
                <div style="margin-bottom:16px;">
                    <button class="tmp-button tmp-primary" data-tmp-approve-all-btn style="display:none;">Approve All Recommended</button>
                </div>
                <div data-tmp-vpe-requests>Loading requests...</div>
            </section>

            <section class="tmp-panel">
                <div class="tmp-card-head">
                    <h3>Member Overview (Paid)</h3>
                    <span data-tmp-vpe-member-count>0 members</span>
                </div>
                <!-- Unmentored alert injected by JS -->
                <div data-tmp-unmentored-alert></div>
                <div class="tmp-admin-filters" style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;background:#f9f9f9;padding:12px;border-radius:4px;border:1px solid #eee;">
                    <input type="text" data-tmp-vpe-search placeholder="Search by name or email..." style="flex:1;min-width:200px;">
                    <select data-tmp-vpe-pathway>
                        <option value="all">All Pathways</option>
                        <option>No pathway registered</option>
                        <option>Enrolled — Pathway TBD</option>
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
                        <option value="0">Level 0 (Enrolled)</option>
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
                </div>
                <div style="margin-bottom:10px;">
                    <button class="tmp-small-button" type="button" data-tmp-vpe-members-toggle>Show Members</button>
                </div>
                <div data-tmp-vpe-member-list>Loading members...</div>
            </section>

            <section class="tmp-panel">
                <button class="tmp-collapsible-toggle" data-tmp-meeting-form-toggle aria-expanded="false" style="width:100%;text-align:left;">
                    Schedule New Meeting
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
                        <p class="tmp-eyebrow">Meeting Template (New meetings only)</p>
                        <p style="font-size:12px;color:#555;margin:0 0 8px;">
                            Using standard agenda with all roles.
                            <button type="button" class="tmp-link-button" data-tmp-customise-roles style="margin-left:6px;font-size:12px;">Customise roles ▾</button>
                        </p>
                        <div data-tmp-roles-grid style="display:none;grid-template-columns:1fr 1fr 1fr;gap:5px;margin-bottom:10px;">
                            <?php foreach (TMP_Repository::get_standard_roles() as $fullName => $shortName) : ?>
                                <label><input type="checkbox" name="roles[]" value="<?php echo esc_attr($fullName); ?>" checked> <?php echo esc_html($shortName); ?></label>
                            <?php endforeach; ?>
                        </div>
                        <label>Number of Speech Slots <input type="number" name="speech_slots" value="3" min="0" max="10" /></label>
                        <p style="font-size:11px;color:#666;margin-top:5px;">* This will automatically create matching Evaluator and Table Topics Speaker slots.</p>
                    </div>
                    <label class="tmp-wide">Agenda notes <textarea name="agenda_notes" rows="2"></textarea></label>
                    <div class="tmp-form-actions tmp-wide">
                        <button class="tmp-button tmp-primary" type="submit">Save Meeting</button>
                        <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-meeting>Clear</button>
                        <span class="tmp-inline-status" data-tmp-meeting-save-status style="margin-left:10px;font-size:13px;"></span>
                    </div>
                </form>
                </div>
            </section>

            <form class="tmp-panel tmp-form" data-tmp-assignment-form>
                <input type="hidden" name="id" />
                <input type="hidden" name="role_name" />
                <label>Meeting <select name="meeting_id" required data-tmp-meeting-select></select></label>
                <div class="tmp-wide"><small>Add a new role slot, change duration, or set a speech title. To assign members to existing slots, use the Role Assignments table below.</small></div>
                <label>Role <select data-tmp-role-select required></select></label>
                <!-- Inline role suggestions — shown by JS when a role slot is selected -->
                <div data-tmp-role-suggestions class="tmp-wide" style="display:none;"></div>
                <label>Member <select name="member_id" data-tmp-member-select></select></label>
                <!-- Cooloff warning injected here by JS -->
                <div data-tmp-cooloff-warning style="display:none;" class="tmp-wide"></div>
                <label data-tmp-speech-title-wrapper>Speech title <input name="speech_title" placeholder="Optional speech title" /></label>
                <!-- Presentation series — shown only for Educational Presentation roles -->
                <label data-tmp-pres-series-wrapper style="display:none;">
                    Presentation Series
                    <select name="presentation_series">
                        <option value="">Not applicable</option>
                        <option value="Successful Club Series">Successful Club Series</option>
                        <option value="Better Speaker Series">Better Speaker Series</option>
                        <option value="Leadership Excellence Series">Leadership Excellence Series</option>
                    </select>
                </label>
                <label>Duration (mins) <input type="number" name="duration" min="0" placeholder="e.g. 7" /></label>
                <label style="display:none;">Status
                    <select name="status">
                        <option>Planned</option>
                        <option>Requested</option>
                        <option>Confirmed</option>
                        <option>Needs replacement</option>
                        <option>Completed</option>
                    </select>
                </label>
                <!-- Cooloff override — shown by JS when needed -->
                <div data-tmp-cooloff-override-wrapper class="tmp-wide" style="display:none;padding:10px;background:#fff8e1;border:1px solid #ffd54f;border-radius:4px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:bold;">
                        <input type="checkbox" name="cooloff_override" value="1"> Override cooloff period
                    </label>
                    <label style="margin-top:6px;">
                        Override reason <input type="text" name="override_reason" placeholder="Brief reason for the exception" />
                    </label>
                </div>
                <div class="tmp-form-actions tmp-wide">
                    <button class="tmp-button tmp-primary" type="submit">Save Assignment</button>
                    <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-assignment>Clear</button>
                </div>
            </form>

            <!-- Role Status panel — populated by JS when a meeting is selected in the form above -->
            <section class="tmp-panel" data-tmp-role-status-panel style="display:none;">
                <p class="tmp-eyebrow">Role assignments</p>
                <!-- content injected by renderRoleStatus() -->
            </section>

            <section class="tmp-panel">
                <div class="tmp-card-head">
                    <h3>Meeting Agendas</h3>
                    <span data-tmp-meeting-count>0 meetings</span>
                </div>
                <div data-tmp-meeting-list></div>
            </section>
        </div>

        <!-- Mentor assignment modal (hidden, shown by JS) -->
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
        <?php
        return ob_get_clean();
    }

    // Runs at template_redirect — before any output, so wp_safe_redirect works reliably.
    public static function auth_redirects() {
        global $post;
        if (!$post) return;

        $content = $post->post_content;

        // Login page: enqueue assets here (before wp_head) so CSS loads in <head>
        if (has_shortcode($content, 'tm_member_login')) {
            if (is_user_logged_in()) {
                wp_safe_redirect(self::portal_home());
                exit;
            }
            wp_enqueue_style('tmp-login');
            wp_enqueue_script('tmp-login');
            return; // nothing else to check on the login page
        }

        // Protected pages: redirect to login if the user lacks the required capability
        if (has_shortcode($content, 'tm_vp_education') && !current_user_can('tmp_manage_meetings')) {
            wp_safe_redirect(wp_login_url(get_permalink()));
            exit;
        }

        if (has_shortcode($content, 'tm_admin_portal') && !current_user_can('tmp_manage_members')) {
            wp_safe_redirect(wp_login_url(get_permalink()));
            exit;
        }

        if (has_shortcode($content, 'tm_member_dashboard') && !is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(get_permalink()));
            exit;
        }
    }

    private static function portal_home() {
        $user = wp_get_current_user();
        if (user_can($user, 'tmp_manage_members'))  return home_url('/club-admin/');
        if (user_can($user, 'tmp_manage_meetings')) return home_url('/vp-education/');
        return home_url('/member-dashboard/');
    }

    private static function restricted($title, $message) {
        // template_redirect normally handles auth before we get here.
        // This is a fallback for edge cases (e.g. shortcode used in widgets).
        self::enqueue();
        $login_url = esc_url(wp_login_url(get_permalink()));
        ob_start();
        ?>
        <div class="tmp-portal tmp-login-card">
            <p class="tmp-eyebrow"><?php echo esc_html($title); ?></p>
            <h2>Sign in required</h2>
            <p><?php echo esc_html($message); ?></p>
            <a class="tmp-button tmp-primary" href="<?php echo $login_url; ?>">Sign in</a>
        </div>
        <?php
        return ob_get_clean();
    }
}
