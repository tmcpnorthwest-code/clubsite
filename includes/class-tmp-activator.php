<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Activator {
    public static function activate() {
        self::create_roles();
        self::create_tables();
        self::create_pages();
        update_option('tmp_plugin_version', TMP_VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        if (get_option('tmp_plugin_version') === TMP_VERSION) {
            return;
        }

        self::create_roles();
        self::create_tables();
        self::create_pages();
        update_option('tmp_plugin_version', TMP_VERSION);
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
            pathways_enrolled VARCHAR(20) NULL,
            current_project VARCHAR(190) NULL,
            mentor VARCHAR(190) NULL,
            next_action VARCHAR(255) NULL,
            officer_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY customer_id (customer_id),
            KEY user_id (user_id),
            KEY pathway (pathway),
            KEY state (state)
        ) {$charset};");

        dbDelta("CREATE TABLE {$meetings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_date DATE NOT NULL,
            start_time TIME NULL,
            total_duration INT UNSIGNED NOT NULL DEFAULT 120,
            theme VARCHAR(190) NOT NULL,
            venue VARCHAR(190) NULL,
            agenda_notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY meeting_date (meeting_date)
        ) {$charset};");

        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            role_name VARCHAR(120) NOT NULL,
            speech_title VARCHAR(190) NULL,
            duration INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(80) NOT NULL DEFAULT 'Planned',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$requests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            assignment_id BIGINT UNSIGNED NOT NULL,
            priority TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id)
        ) {$charset};");

        self::seed_data();
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
            'mentor' => 'Neha Rao',
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
