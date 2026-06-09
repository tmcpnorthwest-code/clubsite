<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_Repository {
    public static function member_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_members';
    }

    public static function meeting_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_meetings';
    }

    public static function assignment_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_role_assignments';
    }

    public static function current_member() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }

        global $wpdb;
        $table = self::member_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d OR email = %s LIMIT 1",
            $user->ID,
            $user->user_email
        ), ARRAY_A);
    }

    public static function members() {
        global $wpdb;
        $table = self::member_table();
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY full_name ASC", ARRAY_A);
    }

    public static function get_member($id) {
        global $wpdb;
        $table = self::member_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    public static function save_member($data) {
        global $wpdb;
        $table = self::member_table();
        $now = current_time('mysql');

        $record = array(
            'user_id' => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'customer_id' => sanitize_text_field($data['customer_id'] ?? ''),
            'full_name' => sanitize_text_field($data['full_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'pathway' => sanitize_text_field($data['pathway'] ?? 'Presentation Mastery'),
            'level' => max(1, min(5, absint($data['level'] ?? 1))),
            'state' => sanitize_text_field($data['state'] ?? 'Active'),
            'paid_until' => !empty($data['paid_until']) ? sanitize_text_field($data['paid_until']) : null,
            'pathways_enrolled' => sanitize_text_field($data['pathways_enrolled'] ?? ''),
            'current_project' => sanitize_text_field($data['current_project'] ?? ''),
            'mentor' => sanitize_text_field($data['mentor'] ?? ''),
            'next_action' => sanitize_text_field($data['next_action'] ?? ''),
            'officer_notes' => sanitize_textarea_field($data['officer_notes'] ?? ''),
            'updated_at' => $now,
        );

        if (empty($record['full_name']) || empty($record['email'])) {
            return new WP_Error('tmp_invalid_member', 'Member name and email are required.', array('status' => 400));
        }

        if (!empty($data['id'])) {
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            return self::get_member(absint($data['id']));
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return self::get_member((int) $wpdb->insert_id);
    }

    public static function upsert_imported_member($data) {
        global $wpdb;
        $table = self::member_table();
        $existing_id = null;

        if (!empty($data['customer_id'])) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE customer_id = %s LIMIT 1",
                $data['customer_id']
            ));
        }

        if (!$existing_id && !empty($data['email'])) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
                $data['email']
            ));
        }

        if ($existing_id) {
            $data['id'] = $existing_id;
        }

        return self::save_member($data);
    }

    public static function delete_member($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::member_table(), array('id' => absint($id)));
    }

    public static function meetings() {
        global $wpdb;
        $meetings = self::meeting_table();
        $assignments = self::assignment_table();
        $members = self::member_table();

        $rows = $wpdb->get_results("SELECT * FROM {$meetings} ORDER BY meeting_date DESC, id DESC LIMIT 25", ARRAY_A);
        foreach ($rows as &$meeting) {
            $meeting['assignments'] = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, m.full_name AS member_name
                 FROM {$assignments} a
                 LEFT JOIN {$members} m ON m.id = a.member_id
                 WHERE a.meeting_id = %d
                 ORDER BY a.sort_order ASC, a.id ASC",
                $meeting['id']
            ), ARRAY_A);
        }

        return $rows;
    }

    public static function save_meeting($data) {
        global $wpdb;
        $table = self::meeting_table();
        $now = current_time('mysql');

        $record = array(
            'meeting_date' => sanitize_text_field($data['meeting_date'] ?? ''),
            'theme' => sanitize_text_field($data['theme'] ?? ''),
            'venue' => sanitize_text_field($data['venue'] ?? ''),
            'agenda_notes' => sanitize_textarea_field($data['agenda_notes'] ?? ''),
            'updated_at' => $now,
        );

        if (empty($record['meeting_date']) || empty($record['theme'])) {
            return new WP_Error('tmp_invalid_meeting', 'Meeting date and theme are required.', array('status' => 400));
        }

        if (!empty($data['id'])) {
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            $id = absint($data['id']);
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($table, $record);
            $id = (int) $wpdb->insert_id;
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    public static function save_assignment($data) {
        global $wpdb;
        $table = self::assignment_table();
        $now = current_time('mysql');

        $record = array(
            'meeting_id' => absint($data['meeting_id'] ?? 0),
            'member_id' => !empty($data['member_id']) ? absint($data['member_id']) : null,
            'role_name' => sanitize_text_field($data['role_name'] ?? ''),
            'speech_title' => sanitize_text_field($data['speech_title'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? 'Planned'),
            'sort_order' => absint($data['sort_order'] ?? 0),
            'updated_at' => $now,
        );

        if (empty($record['meeting_id']) || empty($record['role_name'])) {
            return new WP_Error('tmp_invalid_assignment', 'Meeting and role are required.', array('status' => 400));
        }

        if (!empty($data['id'])) {
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            return array('id' => absint($data['id'])) + $record;
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return array('id' => (int) $wpdb->insert_id) + $record;
    }

    public static function delete_assignment($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::assignment_table(), array('id' => absint($id)));
    }
}
