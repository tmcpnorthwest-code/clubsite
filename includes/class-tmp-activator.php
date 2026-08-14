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
        self::migrate_v080_timing_and_publish();
        self::migrate_v090_level_progress();
        self::migrate_v100_recognition();
        self::migrate_v120_pathway_tables();
        self::migrate_v130_level_completed();
        self::migrate_v132_enforce_level_invariant();
        self::migrate_v133_normalize_states();
        self::migrate_v140_gate_levels();
        self::migrate_v150_timer_duration();
        self::migrate_v160_speech_feedback();
        self::migrate_v170_request_role_name();
        self::migrate_v250_role_catalog_tables();
        self::migrate_v250_role_catalog_columns();
        self::migrate_v250_seed_role_catalog();
        self::migrate_v250_seed_agenda_template();
        self::migrate_v260_pathways_project_column();
        update_option('tmp_plugin_version', TMP_VERSION);
        if (!get_option('tmp_role_cooloff_weeks')) {
            update_option('tmp_role_cooloff_weeks', 4);
        }
        if (!get_option('tmp_role_gate_levels')) {
            update_option('tmp_role_gate_levels', TMP_Repository::default_gate_levels());
        }
        if (!get_option('tmp_default_venue')) {
            update_option('tmp_default_venue', 'Room 106, MM Polytechnic, Thergaon, Pimpri Chinchwad');
        }
        if (!get_option('tmp_default_maps_url')) {
            update_option('tmp_default_maps_url', 'https://maps.app.goo.gl/oHWqSrXgNsCBcwv28');
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
        self::migrate_v060_voting_columns();
        self::migrate_v070_wrap_up_tables();
        self::migrate_v080_timing_and_publish();
        self::migrate_v090_level_progress();
        self::migrate_v100_recognition();
        self::migrate_v120_pathway_tables();
        self::migrate_v130_level_completed();
        self::migrate_v132_enforce_level_invariant();
        self::migrate_v133_normalize_states();
        self::migrate_v140_gate_levels();
        self::migrate_v150_timer_duration();
        self::migrate_v160_speech_feedback();
        self::migrate_v170_request_role_name();
        self::migrate_v180_chapter_number();
        self::migrate_v190_ex_com_role();
        self::migrate_v200_guest_name();
        self::migrate_v210_club_position();
        self::migrate_v250_role_catalog_tables();
        self::migrate_v250_role_catalog_columns();
        self::migrate_v250_seed_role_catalog();
        self::migrate_v250_seed_agenda_template();
        self::migrate_v260_pathways_project_column();
        if (!get_option('tmp_role_cooloff_weeks')) {
            update_option('tmp_role_cooloff_weeks', 4);
        }
        if (!get_option('tmp_role_gate_levels')) {
            update_option('tmp_role_gate_levels', TMP_Repository::default_gate_levels());
        }
        if (!get_option('tmp_default_venue')) {
            update_option('tmp_default_venue', 'Room 106, MM Polytechnic, Thergaon, Pimpri Chinchwad');
        }
        if (!get_option('tmp_default_maps_url')) {
            update_option('tmp_default_maps_url', 'https://maps.app.goo.gl/oHWqSrXgNsCBcwv28');
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
        add_role('tm_ex_com', 'Ex Com Member', array(
            'read' => true,
            'tmp_ex_com_meeting' => true,
            'tmp_view_all_members' => true,
        ));

        self::grant_caps('tm_member', array('read'));
        self::grant_caps('tm_admin', array('read', 'tmp_manage_members', 'tmp_manage_meetings', 'tmp_view_all_members'));
        self::grant_caps('tm_vp_education', array('read', 'tmp_manage_members', 'tmp_manage_meetings', 'tmp_view_all_members'));
        self::grant_caps('tm_ex_com', array('read', 'tmp_ex_com_meeting', 'tmp_view_all_members'));
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
        $overrides   = $wpdb->prefix . 'tmp_req_overrides';
        $level_ups   = $wpdb->prefix . 'tmp_level_up_history';
        $nominees    = $wpdb->prefix . 'tmp_vote_nominees';
        $votes       = $wpdb->prefix . 'tmp_votes';

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
            level_completed TINYINT UNSIGNED NOT NULL DEFAULT 0,
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
            club_position VARCHAR(190) NULL,
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
            chapter_number INT UNSIGNED NULL,
            theme VARCHAR(190) NOT NULL,
            venue VARCHAR(190) NULL,
            agenda_notes TEXT NULL,
            poll_open TINYINT(1) NOT NULL DEFAULT 0,
            winners_declared TINYINT(1) NOT NULL DEFAULT 0,
            wrapped_up TINYINT(1) NOT NULL DEFAULT 0,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_date (meeting_date)
        ) $charset;");

        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            guest_name VARCHAR(190) NULL,
            role_name VARCHAR(120) NOT NULL,
            speech_title VARCHAR(190) NULL,
            duration INT UNSIGNED NOT NULL DEFAULT 0,
            timer_duration TINYINT UNSIGNED NULL,
            status VARCHAR(80) NOT NULL DEFAULT 'Planned',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            presentation_series VARCHAR(100) NULL,
            cooloff_override TINYINT(1) NOT NULL DEFAULT 0,
            override_reason VARCHAR(255) NULL,
            time_green SMALLINT UNSIGNED NULL,
            time_yellow SMALLINT UNSIGNED NULL,
            time_red SMALLINT UNSIGNED NULL,
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

        dbDelta("CREATE TABLE {$level_ups} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            member_name VARCHAR(190) NOT NULL,
            pathway VARCHAR(120) NOT NULL,
            old_level TINYINT UNSIGNED NOT NULL,
            new_level TINYINT UNSIGNED NOT NULL,
            leveled_up_at DATETIME NOT NULL,
            meeting_id BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY new_level (new_level),
            KEY leveled_up_at (leveled_up_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$nominees} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(20) NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(190) NOT NULL,
            role_name VARCHAR(120) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_winner TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_id (meeting_id),
            KEY category (category),
            KEY meeting_category (meeting_id, category)
        ) $charset;");

        dbDelta("CREATE TABLE {$votes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            nominee_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(20) NOT NULL,
            voter_token VARCHAR(64) NOT NULL,
            wp_user_id BIGINT UNSIGNED NULL,
            voted_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_category (voter_token, category, meeting_id),
            KEY meeting_id (meeting_id),
            KEY nominee_id (nominee_id)
        ) $charset;");

        $attendance          = $wpdb->prefix . 'tmp_attendance';
        $win_history         = $wpdb->prefix . 'tmp_win_history';
        $mentor_ratings      = $wpdb->prefix . 'tmp_mentor_ratings';
        $recognition_awards  = $wpdb->prefix . 'tmp_recognition_awards';

        dbDelta("CREATE TABLE {$mentor_ratings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mentor_id BIGINT UNSIGNED NOT NULL,
            mentee_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
            feedback TEXT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY mentee_period (mentee_id, period_start, period_end),
            KEY mentor_id (mentor_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$recognition_awards} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            member_name VARCHAR(190) NOT NULL DEFAULT '',
            period_type VARCHAR(20) NOT NULL,
            period_label VARCHAR(60) NOT NULL DEFAULT '',
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            score DECIMAL(6,2) NOT NULL DEFAULT 0,
            score_breakdown LONGTEXT NULL,
            declared_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            declared_at DATETIME NOT NULL,
            display_on_homepage TINYINT(1) NOT NULL DEFAULT 1,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY period_type (period_type),
            KEY period_start (period_start)
        ) $charset;");

        dbDelta("CREATE TABLE {$attendance} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            guest_name VARCHAR(190) NULL,
            marked_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$win_history} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            meeting_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NULL,
            display_name VARCHAR(190) NOT NULL DEFAULT '',
            category VARCHAR(20) NOT NULL,
            role_name VARCHAR(120) NOT NULL DEFAULT '',
            vote_count INT UNSIGNED NOT NULL DEFAULT 0,
            is_tie TINYINT(1) NOT NULL DEFAULT 0,
            won_at DATE NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY meeting_id (meeting_id),
            KEY member_id (member_id),
            KEY won_at (won_at)
        ) $charset;");

        $pathway_offsets = $wpdb->prefix . 'tmp_pathway_offsets';
        dbDelta("CREATE TABLE {$pathway_offsets} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            level TINYINT UNSIGNED NOT NULL,
            offset TINYINT UNSIGNED NOT NULL DEFAULT 0,
            notes VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY member_level (member_id, level)
        ) $charset;");

        $level_up_requests = $wpdb->prefix . 'tmp_level_up_requests';
        dbDelta("CREATE TABLE {$level_up_requests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            from_level TINYINT UNSIGNED NOT NULL,
            to_level TINYINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            member_note TEXT NULL,
            evidence JSON NOT NULL,
            system_verdict VARCHAR(20) NOT NULL DEFAULT 'incomplete',
            vpe_note TEXT NULL,
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY status (status)
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
     * v0.6.0: Add poll_open / winners_declared to meetings; is_winner to nominees.
     */
    private static function migrate_v060_voting_columns() {
        global $wpdb;
        $meetings  = $wpdb->prefix . 'tmp_meetings';
        $nominees  = $wpdb->prefix . 'tmp_vote_nominees';

        $mcols = $wpdb->get_col("DESCRIBE {$meetings}");
        if (!in_array('poll_open', $mcols, true)) {
            $wpdb->query("ALTER TABLE {$meetings} ADD COLUMN poll_open TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('winners_declared', $mcols, true)) {
            $wpdb->query("ALTER TABLE {$meetings} ADD COLUMN winners_declared TINYINT(1) NOT NULL DEFAULT 0");
        }

        $ncols = $wpdb->get_col("DESCRIBE {$nominees}");
        if (!in_array('is_winner', $ncols, true)) {
            $wpdb->query("ALTER TABLE {$nominees} ADD COLUMN is_winner TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    /**
     * v0.7.0: Add wrapped_up to meetings; create tmp_attendance and tmp_win_history.
     * New tables are created by create_tables() via dbDelta; only the column needs ALTER.
     */
    private static function migrate_v070_wrap_up_tables() {
        global $wpdb;
        $meetings = $wpdb->prefix . 'tmp_meetings';
        $cols = $wpdb->get_col("DESCRIBE {$meetings}");
        if (!in_array('wrapped_up', $cols, true)) {
            $wpdb->query("ALTER TABLE {$meetings} ADD COLUMN wrapped_up TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    /**
     * v0.8.0: Add is_published to meetings; backfill timing columns on existing assignment rows.
     */
    private static function migrate_v080_timing_and_publish() {
        global $wpdb;
        $meetings    = $wpdb->prefix . 'tmp_meetings';
        $assignments = $wpdb->prefix . 'tmp_role_assignments';

        $mcols = $wpdb->get_col("DESCRIBE {$meetings}");
        if (!in_array('is_published', $mcols, true)) {
            $wpdb->query("ALTER TABLE {$meetings} ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER wrapped_up");
        }

        // Backfill timing for existing rows created before v0.8.0
        $rows = $wpdb->get_results(
            "SELECT id, role_name, duration FROM {$assignments} WHERE time_green IS NULL AND duration > 0",
            ARRAY_A
        );
        foreach ($rows as $row) {
            [$tg, $ty, $tr] = TMP_Repository::get_timing_for_role($row['role_name'], (int) $row['duration']);
            if ($tg !== null) {
                $wpdb->update($assignments, [
                    'time_green'  => $tg,
                    'time_yellow' => $ty,
                    'time_red'    => $tr,
                ], ['id' => (int) $row['id']]);
            }
        }
    }

    /**
     * Normalises "Ah Counter" (space) → "Ah-Counter" (hyphen) in history and assignments.
     */
    /**
     * v0.9.0: pathway_offsets and level_up_requests — superseded by migrate_v120_pathway_tables().
     */
    private static function migrate_v090_level_progress() {
        // Intentionally empty; v0.12.0 migration handles these tables robustly.
    }

    /**
     * v0.12.0: Create pathway_offsets and level_up_requests using direct SQL.
     * dbDelta was skipping pathway_offsets because 'offset' is a MySQL keyword
     * that confuses its regex parser.
     */
    private static function migrate_v120_pathway_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_pathway_offsets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            level TINYINT UNSIGNED NOT NULL,
            `offset` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            notes VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tmp_po_member_level (member_id, level)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_level_up_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            from_level TINYINT UNSIGNED NOT NULL,
            to_level TINYINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            member_note TEXT NULL,
            evidence JSON NOT NULL,
            system_verdict VARCHAR(20) NOT NULL DEFAULT 'incomplete',
            vpe_note TEXT NULL,
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY tmp_lu_member_id (member_id),
            KEY tmp_lu_status (status)
        ) {$charset}");
    }

    /**
     * v0.10.0: Creates tmp_mentor_ratings and tmp_recognition_awards tables (handled by
     * dbDelta in create_tables). No ALTER needed — purely new tables.
     */
    private static function migrate_v100_recognition() {
        // Tables are created by create_tables() via dbDelta. Nothing extra needed.
    }

    private static function migrate_ah_counter_normalization() {
        global $wpdb;
        $history    = $wpdb->prefix . 'tmp_participation_history';
        $assignment = $wpdb->prefix . 'tmp_role_assignments';

        $wpdb->query("UPDATE {$history} SET role_name = 'Ah-Counter' WHERE role_name = 'Ah Counter'");
        $wpdb->query("UPDATE {$assignment} SET role_name = REPLACE(role_name, 'Ah Counter', 'Ah-Counter') WHERE role_name LIKE '%Ah Counter%'");
    }

    /**
     * v0.13.0: Add level_completed to track highest completed level separately from current level.
     * Backfill: old `level` field represented highest completed level, so copy it directly.
     */
    private static function migrate_v130_level_completed() {
        global $wpdb;
        $members = $wpdb->prefix . 'tmp_members';

        $cols = $wpdb->get_col("DESCRIBE {$members}");
        if (!in_array('level_completed', $cols, true)) {
            $wpdb->query("ALTER TABLE {$members} ADD COLUMN level_completed TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER level");
            $wpdb->query("UPDATE {$members} SET level_completed = level");
        }
    }

    /**
     * v0.13.2: Enforce the canonical invariant: level = max(1, min(level_completed + 1, 5)).
     * This corrects both old and new data to match the semantic definition:
     * - level_completed = highest completed level (the credential)
     * - level = level currently working toward (always >= 1)
     */
    private static function migrate_v132_enforce_level_invariant() {
        global $wpdb;
        $members = $wpdb->prefix . 'tmp_members';

        // Enforce: level is always min(level_completed + 1, 5) but at least 1
        $wpdb->query("UPDATE {$members} SET level = GREATEST(LEAST(level_completed + 1, 5), 1)");
    }

    private static function migrate_v133_normalize_states() {
        global $wpdb;
        $members = $wpdb->prefix . 'tmp_members';

        // TI CSV exports 'PaidMember' and 'UnpaidMember'. Normalise to internal values.
        $wpdb->query("UPDATE {$members} SET state = 'Active'   WHERE state = 'PaidMember'");
        $wpdb->query("UPDATE {$members} SET state = 'Inactive' WHERE state = 'UnpaidMember'");
    }

    /**
     * v0.14.0: Clean up role gate level keys.
     * Removes non-role entries (introductory mentor, intro mentor, table topics speaker)
     * and renames patterns to match actual role names (toastmaster of the day, table topics master).
     */
    private static function migrate_v140_gate_levels() {
        $stored = (array) get_option('tmp_role_gate_levels', []);
        if (empty($stored)) {
            return;
        }

        $remove  = ['introductory mentor', 'intro mentor', 'table topics speaker', 'ah counter', 'topics master', 'toastmaster'];
        $renamed = [
            'toastmaster' => 'toastmaster of the day',
            'topics master' => 'table topics master',
        ];

        $updated = $stored;
        foreach ($renamed as $old => $new) {
            if (isset($updated[$old]) && !isset($updated[$new])) {
                $updated[$new] = $updated[$old];
            }
        }
        foreach ($remove as $key) {
            unset($updated[$key]);
        }

        if ($updated !== $stored) {
            update_option('tmp_role_gate_levels', $updated);
        }
    }

    private static function migrate_v150_timer_duration() {
        global $wpdb;
        $table = $wpdb->prefix . 'tmp_role_assignments';
        if (empty($wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'timer_duration'"))) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN timer_duration TINYINT UNSIGNED NULL AFTER duration");
        }
    }

    /**
     * v0.16.0: Create wp_tmp_speech_feedback table for per-speech written feedback.
     */
    private static function migrate_v160_speech_feedback() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_speech_feedback (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            assignment_id BIGINT UNSIGNED NOT NULL,
            meeting_id BIGINT UNSIGNED NOT NULL,
            respondent_name VARCHAR(100) NULL,
            feedback_text LONGTEXT NOT NULL,
            submitted_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY tmp_sf_assignment (assignment_id),
            KEY tmp_sf_meeting (meeting_id)
        ) {$charset}");
    }

    /**
     * v0.17.0: Requests now store the generic role type ("Speaker") instead of a specific
     * numbered slot assignment_id. This allows multiple open slots (Speaker 1/2/3) to share
     * a single request queue; slot assignment is deferred to approval time.
     */
    private static function migrate_v170_request_role_name() {
        global $wpdb;
        $requests    = $wpdb->prefix . 'tmp_member_requests';
        $assignments = $wpdb->prefix . 'tmp_role_assignments';

        $rcols = $wpdb->get_col("DESCRIBE {$requests}");

        if (!in_array('role_name', $rcols, true)) {
            $wpdb->query("ALTER TABLE {$requests} ADD COLUMN role_name VARCHAR(120) NULL AFTER assignment_id");
        }

        // Make assignment_id nullable so it can be filled in at approval time
        if (in_array('assignment_id', $rcols, true)) {
            $wpdb->query("ALTER TABLE {$requests} MODIFY assignment_id BIGINT UNSIGNED NULL DEFAULT NULL");
        }

        // Backfill role_name for existing rows: strip trailing " N" number from slot name
        $rows = $wpdb->get_results(
            "SELECT r.id, a.role_name as slot_name
             FROM {$requests} r
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.role_name IS NULL",
            ARRAY_A
        );
        foreach ($rows as $row) {
            // "Speaker 1 (Speech)" → "Speaker", "Evaluator 2 (Evaluation)" → "Evaluator"
            $base = preg_replace('/\s+\d+(\s+\(.*\))?$/', '', $row['slot_name']);
            $wpdb->update($requests, ['role_name' => $base], ['id' => (int) $row['id']]);
        }
    }

    /**
     * v0.19.0: Add tm_ex_com role with meeting-day capabilities.
     * add_role() is a no-op if the role already exists, so safe to re-run.
     */
    private static function migrate_v190_ex_com_role() {
        self::create_roles(); // add_role is idempotent; also re-grants caps to all roles
    }

    private static function migrate_v200_guest_name() {
        global $wpdb;
        $assignments = $wpdb->prefix . 'tmp_role_assignments';
        $cols = $wpdb->get_col("DESCRIBE {$assignments}");
        if (!in_array('guest_name', $cols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN guest_name VARCHAR(190) NULL AFTER member_id");
        }
    }

    /**
     * v0.21.0: Add club_position to members — drives automatic tm_vp_education/tm_ex_com
     * role assignment on CSV import (see TMP_REST_API::import_members()).
     */
    private static function migrate_v210_club_position() {
        global $wpdb;
        $members = $wpdb->prefix . 'tmp_members';
        $cols = $wpdb->get_col("DESCRIBE {$members}");
        if (!in_array('club_position', $cols, true)) {
            $wpdb->query("ALTER TABLE {$members} ADD COLUMN club_position VARCHAR(190) NULL AFTER officer_notes");
        }
    }

    /**
     * v0.25.0 (phase 1 — additive only): role_catalog / agenda_template /
     * agenda_template_items tables. These replace role_name string-parsing
     * (get_base_role_name(), assorted regexes) with a proper role registry
     * and an admin-editable default agenda. Nothing here touches existing
     * role_name data — role_id columns are added but left NULL until the
     * explicit, dry-run-first backfill action is run (see
     * TMP_Repository::backfill_role_ids()).
     */
    private static function migrate_v250_role_catalog_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_role_catalog (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            role_key VARCHAR(60) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            short_label VARCHAR(40) NULL,
            is_numbered TINYINT(1) NOT NULL DEFAULT 0,
            is_service_role TINYINT(1) NOT NULL DEFAULT 0,
            is_cooloff_eligible TINYINT(1) NOT NULL DEFAULT 0,
            is_speech_role TINYINT(1) NOT NULL DEFAULT 0,
            default_gate_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
            vote_category VARCHAR(20) NULL,
            default_time_green SMALLINT UNSIGNED NULL,
            default_time_yellow SMALLINT UNSIGNED NULL,
            default_time_red SMALLINT UNSIGNED NULL,
            default_duration_minutes SMALLINT UNSIGNED NULL,
            ti_level_requirement_keys VARCHAR(255) NULL,
            sort_priority SMALLINT NOT NULL DEFAULT 50,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY role_key (role_key),
            KEY is_active (is_active)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_agenda_template (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(255) NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY is_default (is_default)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tmp_agenda_template_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            segment_label VARCHAR(120) NOT NULL,
            instance_group VARCHAR(40) NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_optional TINYINT(1) NOT NULL DEFAULT 0,
            requires_role_key VARCHAR(60) NULL,
            default_duration_minutes SMALLINT UNSIGNED NULL,
            default_timer_minutes SMALLINT UNSIGNED NULL,
            repeat_per_speech TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY role_id (role_id)
        ) {$charset}");
    }

    /**
     * v0.25.0 (phase 1 — additive only): role_id / segment_label /
     * instance_number / template_item_id on role_assignments, and role_id
     * on the four other tables that carry their own role_name column.
     * All nullable; existing role_name columns are untouched.
     */
    private static function migrate_v250_role_catalog_columns() {
        global $wpdb;

        $assignments = $wpdb->prefix . 'tmp_role_assignments';
        $acols = $wpdb->get_col("DESCRIBE {$assignments}");
        if (!in_array('role_id', $acols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER role_name");
            $wpdb->query("ALTER TABLE {$assignments} ADD KEY role_id (role_id)");
        }
        if (!in_array('segment_label', $acols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN segment_label VARCHAR(120) NULL AFTER role_id");
        }
        if (!in_array('instance_number', $acols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN instance_number SMALLINT UNSIGNED NULL AFTER segment_label");
        }
        if (!in_array('template_item_id', $acols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN template_item_id BIGINT UNSIGNED NULL AFTER instance_number");
        }

        $role_id_tables = [
            $wpdb->prefix . 'tmp_member_requests',
            $wpdb->prefix . 'tmp_participation_history',
            $wpdb->prefix . 'tmp_vote_nominees',
            $wpdb->prefix . 'tmp_win_history',
        ];
        foreach ($role_id_tables as $table) {
            $cols = $wpdb->get_col("DESCRIBE {$table}");
            if (!in_array('role_id', $cols, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER role_name");
                $wpdb->query("ALTER TABLE {$table} ADD KEY role_id (role_id)");
            }
        }
    }

    /**
     * v0.25.0 (phase 1 — additive only): seed the canonical role catalog.
     * Runs once (guarded by row count); safe to re-run.
     *
     * Evaluator / Table Topics Evaluator / General Evaluator are kept as
     * separate rows deliberately — they already have distinct gate levels
     * and vote categories in the current string-based code, so collapsing
     * them would reintroduce the exact conflation this table replaces.
     */
    private static function migrate_v250_seed_role_catalog() {
        global $wpdb;
        $table = $wpdb->prefix . 'tmp_role_catalog';

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0) {
            return;
        }

        $now = current_time('mysql');

        // [role_key, display_name, short_label, is_numbered, is_service_role,
        //  is_cooloff_eligible, is_speech_role, default_gate_level, vote_category,
        //  green, yellow, red, duration_minutes, ti_level_requirement_keys, sort_priority]
        $roles = [
            ['saa', 'Sergeant at Arms', 'SAA', 0, 1, 0, 0, 0, 'aux_role', 60, 90, 120, 2, 'Sergeant at Arms', 10],
            ['presiding_officer', 'Presiding Officer', 'Presiding Officer', 0, 0, 0, 0, 1, null, 180, 240, 300, 5, null, 20],
            ['tmod', 'Toastmaster of the Day', 'TMOD', 0, 1, 1, 0, 2, 'main_role', null, null, null, 1, 'Toastmaster of the Day', 30],
            ['table_topics_master', 'Table Topics Master', 'Topics Master', 0, 1, 0, 0, 0, 'main_role', 600, 900, 1200, 10, 'Table Topics Master', 40],
            ['table_topics_evaluator', 'Table Topics Evaluator', 'TT Evaluator', 0, 1, 0, 0, 1, null, 120, 180, 240, 2, null, 50],
            ['table_topics_speaker', 'Table Topics Speaker', 'TT Speaker', 0, 0, 0, 0, 0, null, null, null, null, null, 'Table Topics Speaker', 60],
            ['general_evaluator', 'General Evaluator', 'GE', 0, 1, 1, 0, 2, 'main_role', 180, 240, 300, 3, 'General Evaluator', 70],
            ['evaluator', 'Evaluator', 'Evaluator', 1, 1, 0, 0, 1, 'evaluator', 180, 240, 300, 3, 'Evaluator', 80],
            ['speaker', 'Speaker', 'Speaker', 1, 0, 1, 1, 0, 'speaker', 300, 360, 420, 6, null, 90],
            ['ad_hoc_speaker', 'Ad Hoc Speaker', 'Ad Hoc Speaker', 1, 0, 0, 1, 0, 'speaker', 300, 360, 420, 5, null, 100],
            ['fun_session', 'Fun Session', 'Fun Session', 1, 0, 0, 0, 0, null, null, null, null, 10, null, 110],
            ['timer', 'Timer', 'Timer', 0, 1, 0, 0, 0, 'aux_role', 60, 90, 120, 2, 'Timer', 120],
            ['ah_counter', 'Ah-Counter', 'Ah-Counter', 0, 1, 0, 0, 0, 'aux_role', 60, 120, 180, 2, 'Ah-Counter', 130],
            ['grammarian', 'Grammarian', 'Grammarian', 0, 1, 0, 0, 0, 'aux_role', 60, 120, 180, 3, 'Grammarian', 140],
            ['educational_presentation', 'Educational Presentation', 'Edu Presentation', 0, 1, 0, 0, 0, null, null, null, null, 5, null, 150],
            ['active_listener', 'Active Listener', 'Active Listener', 0, 1, 0, 0, 0, 'aux_role', null, null, null, 3, 'Active Listener', 160],
            ['break', 'Break', 'Break', 0, 0, 0, 0, 0, null, null, null, null, 5, null, 999],
        ];

        foreach ($roles as $r) {
            [$role_key, $display_name, $short_label, $is_numbered, $is_service, $is_cooloff,
             $is_speech, $gate_level, $vote_cat, $green, $yellow, $red, $duration, $ti_keys, $priority] = $r;

            $wpdb->insert($table, [
                'role_key'                  => $role_key,
                'display_name'               => $display_name,
                'short_label'                => $short_label,
                'is_numbered'                => $is_numbered,
                'is_service_role'            => $is_service,
                'is_cooloff_eligible'        => $is_cooloff,
                'is_speech_role'             => $is_speech,
                'default_gate_level'         => $gate_level,
                'vote_category'              => $vote_cat,
                'default_time_green'         => $green,
                'default_time_yellow'        => $yellow,
                'default_time_red'           => $red,
                'default_duration_minutes'   => $duration,
                'ti_level_requirement_keys'  => $ti_keys,
                'sort_priority'              => $priority,
                'is_active'                  => 1,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);
        }
    }

    /**
     * v0.25.0 (phase 1 — additive only): seed the default weekly-meeting
     * agenda template. Runs once (guarded by row count); safe to re-run.
     * Requires migrate_v250_seed_role_catalog() to have run first.
     *
     * Matches the club's standard flow: SAA opens -> Presiding Officer ->
     * TMOD introduces role players -> optional Educational Presentation ->
     * Break -> per-speech loop (TMOD intro, Speech) -> Timer's Report ->
     * Table Topics round -> Evaluation segment -> Timer's Report ->
     * role players' reports -> Presiding Officer closes.
     */
    private static function migrate_v250_seed_agenda_template() {
        global $wpdb;
        $template_table = $wpdb->prefix . 'tmp_agenda_template';
        $items_table    = $wpdb->prefix . 'tmp_agenda_template_items';
        $catalog_table  = $wpdb->prefix . 'tmp_role_catalog';

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$template_table}") > 0) {
            return;
        }

        // Catalog must exist first — if it doesn't yet (unexpected ordering),
        // bail out silently; this method is re-run on every upgrade check
        // via maybe_upgrade(), so it will succeed once the catalog is seeded.
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$catalog_table}") === 0) {
            return;
        }

        $now = current_time('mysql');

        $wpdb->insert($template_table, [
            'name'        => 'Default Weekly Meeting',
            'description' => 'Standard club meeting agenda',
            'is_default'  => 1,
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $template_id = (int) $wpdb->insert_id;

        // [role_key, segment_label, is_optional, requires_role_key,
        //  repeat_per_speech, instance_group, default_duration_minutes]
        $items = [
            ['saa', 'Starts meeting', 0, null, 0, null, 2],
            ['presiding_officer', 'Address and Guest Introduction', 0, null, 0, null, 5],
            ['tmod', 'Introduces the theme', 0, null, 0, null, 4],
            ['tmod', 'Introduces the segments', 0, null, 0, null, 2],
            ['timer', 'Explains role', 0, null, 0, null, 2],
            ['ah_counter', 'Explains role', 0, null, 0, null, 2],
            ['grammarian', 'Explains role and Word/Phrase of the Day', 0, null, 0, null, 3],
            ['general_evaluator', 'Explains role', 0, null, 0, null, 2],
            ['table_topics_evaluator', 'Introduction of role', 1, null, 0, null, 2],
            ['tmod', 'Introduces Educational Presentation', 1, 'educational_presentation', 0, null, 1],
            ['educational_presentation', 'Presentation', 1, null, 0, null, 5],
            ['evaluator', 'Introduces speaker', 0, null, 1, 'speech_block', 2],
            ['speaker', 'Speech', 0, null, 1, 'speech_block', 6],
            ['timer', 'Report', 0, null, 0, null, 1],
            ['break', 'Networking', 0, null, 0, null, 5],
            ['tmod', 'Theme interlude', 0, null, 0, null, 2],
            ['table_topics_master', 'Table Topics Session', 1, null, 0, null, 10],
            ['table_topics_evaluator', 'Table Topics Session Evaluation', 1, null, 0, null, 3],
            ['tmod', 'Introduces Evaluation Session', 0, null, 0, null, 1],
            ['evaluator', 'Evaluation', 0, null, 1, 'eval_block', 3],
            ['timer', 'Report', 0, null, 0, null, 1],
            ['tmod', 'Introduces Ad Hoc Speaker', 1, null, 1, 'adhoc_block', 1],
            ['ad_hoc_speaker', 'Guest Speech', 1, null, 1, 'adhoc_block', 5],
            ['tmod', 'Introduces Fun Session', 1, null, 1, 'fun_block', 1],
            ['fun_session', 'Organizes fun session', 1, null, 1, 'fun_block', 10],
            ['grammarian', 'Report', 0, null, 0, null, 4],
            ['ah_counter', 'Report', 0, null, 0, null, 3],
            ['active_listener', 'Report', 1, null, 0, null, 3],
            ['general_evaluator', 'Final Report', 0, null, 0, null, 9],
            ['tmod', 'Theme Closure', 0, null, 0, null, 2],
            ['presiding_officer', 'Closing Remarks, Feedback and Announcements', 0, null, 0, null, 6],
        ];

        $sort_order = 10;
        foreach ($items as $item) {
            [$role_key, $segment_label, $is_optional, $requires_role_key,
             $repeat_per_speech, $instance_group, $duration] = $item;

            $role_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$catalog_table} WHERE role_key = %s", $role_key
            ));
            if (!$role_id) {
                continue; // catalog row missing — skip rather than insert an orphaned FK
            }

            $wpdb->insert($items_table, [
                'template_id'               => $template_id,
                'role_id'                   => (int) $role_id,
                'segment_label'              => $segment_label,
                'instance_group'             => $instance_group,
                'sort_order'                 => $sort_order,
                'is_optional'                => $is_optional,
                'requires_role_key'          => $requires_role_key,
                'default_duration_minutes'   => $duration,
                'default_timer_minutes'      => null,
                'repeat_per_speech'          => $repeat_per_speech,
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);
            $sort_order += 10;
        }
    }

    /**
     * v0.26.0: adds project_name to assignments/participation_history so
     * officers can record a speaker's real Pathways project per-instance,
     * alongside (not replacing) the legacy presentation_series column.
     */
    private static function migrate_v260_pathways_project_column() {
        global $wpdb;

        $assignments = $wpdb->prefix . 'tmp_role_assignments';
        $acols = $wpdb->get_col("DESCRIBE {$assignments}");
        if (!in_array('project_name', $acols, true)) {
            $wpdb->query("ALTER TABLE {$assignments} ADD COLUMN project_name VARCHAR(190) NULL AFTER speech_title");
        }

        $history = $wpdb->prefix . 'tmp_participation_history';
        $hcols = $wpdb->get_col("DESCRIBE {$history}");
        if (!in_array('project_name', $hcols, true)) {
            $wpdb->query("ALTER TABLE {$history} ADD COLUMN project_name VARCHAR(190) NULL AFTER presentation_series");
        }
    }

    private static function migrate_v180_chapter_number() {
        global $wpdb;
        $meetings = $wpdb->prefix . 'tmp_meetings';
        $cols = $wpdb->get_col("DESCRIBE {$meetings}");
        if (!in_array('chapter_number', $cols, true)) {
            $wpdb->query("ALTER TABLE {$meetings} ADD COLUMN chapter_number INT UNSIGNED NULL AFTER requests_close_at");
        }
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
        self::maybe_create_page('club-home', 'Club Home', '[tm_public_dashboard]');
        self::maybe_create_page('speech-feedback', 'Speech Feedback', '[tm_feedback_form]');
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
