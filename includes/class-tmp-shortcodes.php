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
        add_action('wp_enqueue_scripts',    [__CLASS__, 'register_assets']);
    }

    public static function register_assets() {
        wp_register_style( 'tmp-portal', TMP_PLUGIN_URL . 'assets/portal.css', [], TMP_VERSION);
        wp_register_script('tmp-portal', TMP_PLUGIN_URL . 'assets/portal.js',  [], TMP_VERSION, true);
    }

    private static function enqueue() {
        wp_enqueue_style('tmp-portal');
        wp_enqueue_script('tmp-portal');
        wp_localize_script('tmp-portal', 'TMPortal', [
            'restUrl'       => esc_url_raw(rest_url('toastmasters/v1')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'standardRoles' => TMP_Repository::get_standard_roles(),
            'loginUrl'      => wp_login_url(get_permalink()),
            'logoutUrl'     => wp_logout_url(home_url('/')),
            'currentUser'   => is_user_logged_in() ? [
                'id'              => get_current_user_id(),
                'name'            => wp_get_current_user()->display_name,
                'email'           => wp_get_current_user()->user_email,
                'canManageMembers'=> current_user_can('tmp_manage_members'),
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
            <div class="tmp-panel">
                <p class="tmp-eyebrow">Member dashboard</p>
                <h2 data-tmp-member-name>Loading dashboard</h2>
                <p data-tmp-member-summary></p>
                <dl class="tmp-profile-list" style="margin-top:14px;">
                    <div><dt>Status</dt><dd data-tmp-state></dd></div>
                    <div><dt>Current Project</dt><dd data-tmp-project></dd></div>
                    <div><dt>Paid Until</dt><dd data-tmp-paid-until></dd></div>
                </dl>
            </div>
            <div class="tmp-grid">

                <article class="tmp-panel tmp-progress-card">
                    <div class="tmp-card-head">
                        <h3>Pathways Progress</h3>
                        <span data-tmp-progress>0%</span>
                    </div>
                    <div class="tmp-progress"><span data-tmp-progress-bar></span></div>
                    <ol class="tmp-levels" data-tmp-levels></ol>
                </article>

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
                                <div class="tmp-grid" style="grid-template-columns: 1fr; gap: 10px;">
                                    <label>1. Select Meeting
                                        <select name="meeting_id" required data-tmp-req-meeting-select></select>
                                    </label>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
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
                <div class="tmp-table-wrap">
                    <table class="tmp-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Customer ID</th>
                                <th>Email</th>
                                <th>Pathway</th>
                                <th>Level</th>
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
                <div data-tmp-vpe-requests>Loading requests...</div>
            </section>

            <!-- Members due for a role (no role in last cooloff window) -->
            <section class="tmp-panel" data-tmp-due-roles-section>
                <div class="tmp-card-head">
                    <h3>Members Due for a Role</h3>
                    <span data-tmp-due-roles-count></span>
                </div>
                <div data-tmp-due-roles-list>Loading...</div>
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
                        <option>Presentation Mastery</option>
                        <option>Dynamic Leadership</option>
                        <option>Engaging Humor</option>
                        <option>Motivational Strategies</option>
                        <option>Persuasive Influence</option>
                        <option>Visionary Communication</option>
                    </select>
                    <select data-tmp-vpe-level>
                        <option value="all">All Levels</option>
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
                <div data-tmp-vpe-member-list>Loading members...</div>
            </section>

            <form class="tmp-panel tmp-form" data-tmp-meeting-form>
                <input type="hidden" name="id" />
                <label>Meeting date <input type="date" name="meeting_date" required /></label>
                <label>Start time <input type="time" name="start_time" value="18:30" /></label>
                <label>Total Duration (mins) <input type="number" name="total_duration" value="120" min="0" /></label>
                <label>Requests deadline <input type="datetime-local" name="requests_close_at" /></label>
                <label>Theme <input name="theme" required placeholder="Meeting theme" /></label>
                <label>Venue or link <input name="venue" placeholder="Room, address, or meeting link" /></label>
                <div class="tmp-wide tmp-roles-setup" style="margin:10px 0;padding:10px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                    <p class="tmp-eyebrow">Meeting Template (New meetings only)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:5px;margin-bottom:10px;">
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
                </div>
            </form>

            <form class="tmp-panel tmp-form" data-tmp-assignment-form>
                <input type="hidden" name="id" />
                <input type="hidden" name="role_name" />
                <label>Meeting <select name="meeting_id" required data-tmp-meeting-select></select></label>
                <div class="tmp-wide"><small>Create unassigned role slots here. Members can then request these roles, and you can approve them with one click.</small></div>
                <label>Role <select data-tmp-role-select required></select></label>
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
                <label>Status
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
}
