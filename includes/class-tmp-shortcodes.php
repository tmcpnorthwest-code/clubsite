<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Shortcodes {
    public static function init() {
        add_shortcode('tm_member_login', array(__CLASS__, 'member_login'));
        add_shortcode('tm_member_dashboard', array(__CLASS__, 'member_dashboard'));
        add_shortcode('tm_admin_portal', array(__CLASS__, 'admin_portal'));
        add_shortcode('tm_vp_education', array(__CLASS__, 'vp_education'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    public static function register_assets() {
        wp_register_style('tmp-portal', TMP_PLUGIN_URL . 'assets/portal.css', array(), TMP_VERSION);
        wp_register_script('tmp-portal', TMP_PLUGIN_URL . 'assets/portal.js', array(), TMP_VERSION, true);
    }

    private static function enqueue() {
        wp_enqueue_style('tmp-portal');
        wp_enqueue_script('tmp-portal');
        wp_localize_script('tmp-portal', 'TMPortal', array(
            'restUrl' => esc_url_raw(rest_url('toastmasters/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'loginUrl' => wp_login_url(get_permalink()),
            'logoutUrl' => wp_logout_url(home_url('/')),
            'currentUser' => is_user_logged_in() ? array(
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
                'canManageMembers' => current_user_can('tmp_manage_members'),
                'canManageMeetings' => current_user_can('tmp_manage_meetings'),
            ) : null,
        ));
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
                    <h3>Current State</h3>
                    <dl class="tmp-profile-list">
                        <div><dt>Status</dt><dd data-tmp-state></dd></div>
                        <div><dt>Project</dt><dd data-tmp-project></dd></div>
                        <div><dt>Mentor</dt><dd data-tmp-mentor></dd></div>
                    </dl>
                </article>
                <article class="tmp-panel">
                    <h3>Next Action</h3>
                    <p data-tmp-next-action></p>
                </article>
                <article class="tmp-panel">
                    <h3>Officer Notes</h3>
                    <p data-tmp-notes></p>
                </article>
                <article class="tmp-panel tmp-wide">
                    <h3>Smart Recommendations</h3>
                    <div class="tmp-rec-grid" data-tmp-recommendations>Loading suggestions...</div>
                </article>
                <article class="tmp-panel tmp-wide">
                    <h3>Available Meeting Slots</h3>
                    <div class="tmp-slot-list" data-tmp-open-slots>Checking for open roles...</div>
                </article>
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
            <?php echo self::member_form(); ?>
            <section class="tmp-panel">
                <div class="tmp-card-head">
                    <h3>Members</h3>
                    <span data-tmp-member-count>0 records</span>
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

            <form class="tmp-panel tmp-form" data-tmp-meeting-form>
                <input type="hidden" name="id" />
                <label>Meeting date <input type="date" name="meeting_date" required /></label>
                <label>Theme <input name="theme" required placeholder="Meeting theme" /></label>
                <label>Venue or link <input name="venue" placeholder="Room, address, or meeting link" /></label>
                <label class="tmp-wide">Agenda notes <textarea name="agenda_notes" rows="4"></textarea></label>
                <div class="tmp-form-actions tmp-wide">
                    <button class="tmp-button tmp-primary" type="submit">Save Meeting</button>
                    <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-meeting>Clear</button>
                </div>
            </form>

            <form class="tmp-panel tmp-form" data-tmp-assignment-form>
                <input type="hidden" name="id" />
                <label>Meeting <select name="meeting_id" required data-tmp-meeting-select></select></label>
                <label>Role <input name="role_name" required placeholder="Toastmaster, Speaker, Evaluator" /></label>
                <label>Member <select name="member_id" data-tmp-member-select></select></label>
                <label>Speech title <input name="speech_title" placeholder="Optional speech title" /></label>
                <label>Status
                    <select name="status">
                        <option>Planned</option>
                        <option>Requested</option>
                        <option>Confirmed</option>
                        <option>Needs replacement</option>
                        <option>Completed</option>
                    </select>
                </label>
                <label>Order <input type="number" name="sort_order" value="10" min="0" /></label>
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
        <?php
        return ob_get_clean();
    }

    private static function member_form() {
        ob_start();
        ?>
        <form class="tmp-panel tmp-form" data-tmp-member-form>
            <input type="hidden" name="id" />
            <label>Full name <input name="full_name" required placeholder="Member name" /></label>
            <label>Customer ID <input name="customer_id" placeholder="PN-12345678" /></label>
            <label>Email <input type="email" name="email" required placeholder="member@example.com" /></label>
            <label>Phone <input name="phone" placeholder="+91 98765 43210" /></label>
            <label>Linked WordPress user ID <input type="number" name="user_id" min="0" placeholder="Optional" /></label>
            <label>Pathway
                <select name="pathway">
                    <option>Presentation Mastery</option>
                    <option>Dynamic Leadership</option>
                    <option>Engaging Humor</option>
                    <option>Motivational Strategies</option>
                    <option>Persuasive Influence</option>
                    <option>Visionary Communication</option>
                </select>
            </label>
            <label>Current level
                <select name="level">
                    <option value="1">Level 1</option>
                    <option value="2">Level 2</option>
                    <option value="3">Level 3</option>
                    <option value="4">Level 4</option>
                    <option value="5">Level 5</option>
                </select>
            </label>
            <label>State
                <select name="state">
                    <option>Active</option>
                    <option>Needs speech slot</option>
                    <option>Awaiting level approval</option>
                    <option>On hold</option>
                    <option>New member</option>
                </select>
            </label>
            <label>Current project <input name="current_project" placeholder="Project or speech title" /></label>
            <label>Mentor <input name="mentor" placeholder="Mentor name" /></label>
            <label>Next action <input name="next_action" placeholder="One next action" /></label>
            <label class="tmp-wide">Officer notes <textarea name="officer_notes" rows="4"></textarea></label>
            <div class="tmp-form-actions tmp-wide">
                <button class="tmp-button tmp-primary" type="submit">Save Member</button>
                <button class="tmp-button tmp-secondary" type="button" data-tmp-clear-member>Clear</button>
            </div>
        </form>
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
