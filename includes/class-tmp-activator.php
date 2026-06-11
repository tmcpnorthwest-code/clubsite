<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Activator {
    public static function activate() {
        self::log('Activation hook triggered');
        self::create_roles();
        self::create_tables();
        self::create_pages();
        update_option('tmp_plugin_version', TMP_VERSION);
        if (!get_option('tmp_role_cooloff_weeks')) {
            update_option('tmp_role_cooloff_weeks', 4);
        }
        if (!get_option('tmp_role_gate_levels')) {
            update_option('tmp_role_gate_levels', TMP_Repository::default_gate_levels());
        }
        flush_rewrite_rules();
        self::log('Activation hook completed');
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        if (get_option('tmp_plugin_version') === TMP_VERSION) {
            return;
        }

        self::log('Upgrade required. Current version: ' . get_option('tmp_plugin_version') . ' Target: ' . TMP_VERSION);
        self::create_roles();
        self::create_tables();
        self::create_pages();
        self::migrate_mentor_text_to_id();
        self::migrate_ah_counter_normalization();
        if (!get_option('tmp_role_cooloff_weeks')) {
            update_option('tmp_role_cooloff_weeks', 4);
        }
        if (!get_option('tmp_role_gate_levels')) {
            update_option('tmp_role_gate_levels', TMP_Repository::default_gate_levels());
        }
        update_option('tmp_plugin_version', TMP_VERSION);
        self::log('Upgrade completed');
    }

    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TMP Activator: ' . $message);
        }
    }

    private static function create_roles() {
        add_role('tm_member', 'Toastmasters Member', array('read' => true));
        add_role('tm_admin', 'Toastmasters Admin', array(
            'read' => true,
            'tmp_manage_members' => true,
            'tmp_manage_meetings' => true,
            'tmp_view_all_members' => true,
        ));
        add_role('tm_vp_education', 'VP Education', array(
            'read' => true,
            'tmp_manage_meetings' => true,
            'tmp_manage_members' => true,
            'tmp_view_all_members' => true,
        ));

        self::grant_caps('tm_member', array('read'));
        self::grant_caps('tm_admin', array('read', 'tmp_manage_members', 'tmp_manage_meetings', 'tmp_view_all_members'));
        self::grant_caps('tm_vp_education', array('read', 'tmp_manage_members', 'tmp_manage_meetings', 'tmp_view_all_members'));
        self::grant_caps('administrator', array('tmp_manage_members', 'tmp_manage_meetings', 'tmp_view_all_members'));
    }

    private static function grant_caps($role_name, $caps) {
        $role = get_role($role_name);
        if (!$role) {
            return;
        }

        foreach ($caps as $cap) {
            $role->add_cap($cap);
        }
    }

    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $members = $wpdb->prefix . 'tmp_members';
        $meetings = $wpdb->prefix . 'tmp_meetings';
        $assignments = $wpdb->prefix . 'tmp_role_assignments';
        $requests = $wpdb->prefix . 'tmp_member_requests';
        $participation = $wpdb->prefix . 'tmp_participation_history';
        $overrides = $wpdb->prefix . 'tmp_req_overrides';

        // mentor VARCHAR kept for legacy data; mentor_id is the FK used by all new code
        dbDelta("CREATE TABLE {$members} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            customer_id VARCHAR(80) NULL,
            full_name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) NULL,
            pathway VARCHAR(120) NOT NULL DEFAULT 'Presentation Mastery',
            level TINYINT UNSIGNED NOT NULL DEFAULT 1,
            state VARCHAR(80) NOT NULL DEFAULT 'Active',
            paid_until DATE NULL,
            is_exempt_from_unpaid_block TINYINT(1) NOT NULL DEFAULT 0,
            pathways_enrolled VARCHAR(20) NULL,
            current_project VARCHAR(190) NULL,
            onboarding_status VARCHAR(50) DEFAULT 'Pending',
            orientation_date DATE NULL,
            icebreaker_draft_date DATE NULL,
            mentor VARCHAR(190) NULL,
            mentor_id BIGINT UNSIGNED NULL,
            next_action VARCHAR(255) NULL,
            officer_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            UNIQUE KEY customer_id (customer_id),
            KEY user_id (user_id),
            KEY pathway (pathway),
            KEY state (state),
            KEY mentor_id (mentor_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$meetings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_date DATE NOT NULL,
            start_time TIME NULL,
            total_duration INT UNSIGNED NOT NULL DEFAULT 120,
            requests_close_at DATETIME NULL,
            theme VARCHAR(190) NOT NULL,
            venue VARCHAR(190) NULL,
            agenda_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_date (meeting_date)
        ) $charset;");

        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            role_name VARCHAR(120) NOT NULL,
            speech_title VARCHAR(190) NULL,
            duration INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(80) NOT NULL DEFAULT 'Planned',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            presentation_series VARCHAR(100) NULL,
            cooloff_override TINYINT(1) NOT NULL DEFAULT 0,
            override_reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$requests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            priority TINYINT UNSIGNED NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            reason TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id),
            KEY status (status)
        ) $charset;");

        dbDelta("CREATE TABLE {$participation} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            meeting_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            role_name VARCHAR(120) NOT NULL,
            meeting_date DATE NOT NULL,
            level_at_completion TINYINT UNSIGNED NOT NULL,
            presentation_series VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY role_name (role_name)
        ) $charset;");

        dbDelta("CREATE TABLE {$overrides} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            level TINYINT UNSIGNED NOT NULL,
            req_key VARCHAR(120) NOT NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY member_level (member_id, level)
        ) $charset;");

        self::seed_data();
    }

    /**
     * Converts legacy plain-text mentor names to mentor_id FK references.
     * Runs once on upgrade; safe to run multiple times (only updates NULL mentor_id rows).
     */
    private static function migrate_mentor_text_to_id() {
        global $wpdb;
        $table = $wpdb->prefix . 'tmp_members';

        $rows = $wpdb->get_results(
            "SELECT id, mentor FROM {$table} WHERE mentor != '' AND mentor IS NOT NULL AND (mentor_id IS NULL OR mentor_id = 0)",
            ARRAY_A
        );

        foreach ($rows as $row) {
            $mentor_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE full_name = %s LIMIT 1",
                $row['mentor']
            ));
            if ($mentor_id) {
                $wpdb->update($table, array('mentor_id' => (int) $mentor_id), array('id' => (int) $row['id']));
            }
        }
    }

    /**
     * Normalises "Ah Counter" (space) → "Ah-Counter" (hyphen) in history and assignments.
     */
    private static function migrate_ah_counter_normalization() {
        global $wpdb;
        $history    = $wpdb->prefix . 'tmp_participation_history';
        $assignment = $wpdb->prefix . 'tmp_role_assignments';

        $wpdb->query("UPDATE {$history} SET role_name = 'Ah-Counter' WHERE role_name = 'Ah Counter'");
        $wpdb->query("UPDATE {$assignment} SET role_name = REPLACE(role_name, 'Ah Counter', 'Ah-Counter') WHERE role_name LIKE '%Ah Counter%'");
    }

    private static function seed_data() {
        global $wpdb;

        $members = $wpdb->prefix . 'tmp_members';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$members}");
        if ($count > 0) {
            return;
        }

        $now = current_time('mysql');
        $wpdb->insert($members, array(
            'full_name' => 'Aarav Mehta',
            'customer_id' => 'PN-DEMO001',
            'email' => 'member@speakwell.org',
            'phone' => '',
            'pathway' => 'Presentation Mastery',
            'level' => 3,
            'state' => 'Active',
            'paid_until' => null,
            'pathways_enrolled' => 'Yes',
            'current_project' => 'Persuasive speaking',
            'mentor_id' => null,
            'next_action' => 'Request Level 3 completion review',
            'officer_notes' => 'Mentor recommends one more persuasive speech before submitting Level 3.',
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    private static function create_pages() {
        self::maybe_create_page('member-login', 'Member Login', '[tm_member_login]');
        self::maybe_create_page('member-dashboard', 'Member Dashboard', '[tm_member_dashboard]');
        self::maybe_create_page('club-admin', 'Club Admin', '[tm_admin_portal]');
        self::maybe_create_page('vp-education', 'VP Education', '[tm_vp_education]');
    }

    private static function maybe_create_page($slug, $title, $content) {
        $page = get_page_by_path($slug);
        if ($page) {
            return;
        }

        wp_insert_post(array(
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => 'page',
        ));
    }
}
