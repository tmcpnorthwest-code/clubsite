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

    public static function participation_history_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_participation_history';
    }

    public static function request_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_member_requests';
    }

    public static function attendance_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_attendance';
    }

    public static function win_history_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_win_history';
    }

    // -------------------------------------------------------------------------
    // Standard roles & TI requirements
    // -------------------------------------------------------------------------

    public static function overrides_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_req_overrides';
    }

    /**
     * Ordered map of role name substrings → minimum level required.
     * Order matters: 'general evaluator' must precede 'evaluator', 'speaker' must precede nothing.
     * These patterns match role names case-insensitively via stripos() and includes().
     */
    public static function default_gate_levels() {
        return [
            'general evaluator'      => 2,  // L2+ only
            'toastmaster of the day' => 2,  // L2+ only
            'presiding officer'      => 1,  // L1+ only
            'table topics evaluator' => 1,  // L1+ only
            'evaluator'              => 1,  // L1+ only (covers Evaluator 1, Evaluator 2, etc.)
            'speaker'                => 0,  // L0+ (any level) — covers all Speaker roles
            'table topics master'    => 0,  // L0+ (any level)
            'grammarian'             => 0,  // L0+ (any level)
            'ah-counter'             => 0,  // L0+ (any level)
            'timer'                  => 0,  // L0+ (any level)
            'sergeant at arms'       => 0,  // L0+ (any level)
            'active listener'        => 0,  // L0+ (any level)
        ];
    }

    public static function get_current_gate_levels() {
        $stored   = get_option('tmp_role_gate_levels', null);
        $defaults = self::default_gate_levels();
        if ($stored === null) return $defaults;
        // Merge so that new role patterns added to defaults always appear,
        // while admin-saved values override the defaults for existing patterns.
        // array_merge preserves default key order; stored values win on conflicts.
        return array_merge($defaults, (array) $stored);
    }

    public static function get_standard_roles() {
        return [
            'Sergeant at Arms'       => 'SAA',
            'Presiding Officer'      => 'Presiding Officer',
            'Toastmaster of the Day' => 'TMOD',
            'Table Topics Master'     => 'Topics Master',
            'Table Topics Evaluator'  => 'TT Evaluator',
            'General Evaluator'       => 'GE',
            'Timer'                  => 'Timer',
            'Ah-Counter'             => 'Ah-Counter',
            'Grammarian'             => 'Grammarian',
            'Educational Presentation' => 'Edu Presentation',
            'Active Listener'        => 'Active Listener',
        ];
    }

    /**
     * TI mandatory role/presentation requirements per level (effective Oct 2025).
     * Source: d3toastmasters.org/whats-now-mandatory-to-complete-your-pathways-levels/
     *
     * type = 'role'         → all listed roles must be completed (min times each)
     * type = 'role_or'      → at least one of the listed roles must be completed
     * type = 'presentation' → an Educational Presentation of the given series (min times)
     */
    public static function get_level_requirements() {
        return [
            1 => [
                // TT Speaker must precede Ice Breaker (enforced separately)
                ['type' => 'role',    'roles' => ['Table Topics Speaker'], 'min' => 1, 'label' => 'Table Topics Speaker'],
                ['type' => 'role',    'roles' => ['Evaluator'],            'min' => 1, 'label' => 'Evaluator'],
                ['type' => 'role_or', 'roles' => ['Timer', 'Ah-Counter', 'Sergeant at Arms'],  'min' => 1, 'label' => 'Timer, Ah-Counter, or SAA'],
            ],
            2 => [
                ['type' => 'role',    'roles' => ['Grammarian'],           'min' => 1, 'label' => 'Grammarian'],
                ['type' => 'role',    'roles' => ['Table Topics Master'],  'min' => 1, 'label' => 'Table Topics Master'],
                ['type' => 'role',    'roles' => ['Evaluator'],            'min' => 1, 'label' => 'Evaluator'],
                ['type' => 'role_or', 'roles' => ['Toastmaster of the Day', 'Timer', 'Ah-Counter'], 'min' => 1, 'label' => 'TMOD, Timer, or Ah-Counter'],
            ],
            3 => [
                ['type' => 'role',    'roles' => ['Toastmaster of the Day'], 'min' => 1, 'label' => 'Toastmaster of the Day'],
                ['type' => 'role',    'roles' => ['Evaluator'],              'min' => 1, 'label' => 'Evaluator'],
                ['type' => 'role_or', 'roles' => ['Table Topics Master', 'Table Topics Speaker', 'Introductory Mentor'], 'min' => 1, 'label' => 'TTM, TT Speaker, or Introductory Mentor'],
                ['type' => 'presentation', 'series' => 'Successful Club Series', 'min' => 1, 'label' => 'Educational Presentation (Successful Club Series)'],
            ],
            4 => [
                ['type' => 'role',    'roles' => ['Toastmaster of the Day'], 'min' => 1, 'label' => 'Toastmaster of the Day'],
                ['type' => 'role',    'roles' => ['General Evaluator'],      'min' => 1, 'label' => 'General Evaluator'],
                ['type' => 'role',    'roles' => ['Evaluator'],              'min' => 1, 'label' => 'Evaluator'],
                ['type' => 'role_or', 'roles' => ['Table Topics Speaker', 'Table Topics Master', 'Specialized Role'], 'min' => 1, 'label' => 'TT Speaker, TTM, or Specialized Role'],
                ['type' => 'presentation', 'series' => 'Successful Club Series', 'min' => 1, 'label' => 'Educational Presentation (Successful Club Series)'],
                ['type' => 'presentation', 'series' => 'Better Speaker Series',  'min' => 1, 'label' => 'Educational Presentation (Better Speaker Series)'],
            ],
            5 => [
                ['type' => 'role', 'roles' => ['Toastmaster of the Day'], 'min' => 2, 'label' => 'Toastmaster of the Day (×2)'],
                ['type' => 'role', 'roles' => ['General Evaluator'],      'min' => 2, 'label' => 'General Evaluator (×2)'],
                ['type' => 'role', 'roles' => ['Evaluator'],              'min' => 2, 'label' => 'Evaluator (×2)'],
                ['type' => 'presentation', 'series' => 'Successful Club Series',      'min' => 1, 'label' => 'Educational Presentation (Successful Club Series)'],
                ['type' => 'presentation', 'series' => 'Leadership Excellence Series','min' => 1, 'label' => 'Educational Presentation (Leadership Excellence Series)'],
            ],
        ];
    }

    /**
     * Returns the gap status for each requirement at the given level for a member.
     * Uses pre-fetched counts to avoid extra queries when called inside a loop.
     *
     * @param int   $member_id
     * @param int   $level
     * @param array $role_counts  [role_name => count]  (optional, fetched if null)
     * @param array $pres_counts  [series => count]     (optional, fetched if null)
     * @return array[]  Each item: {type, label, roles/series, needed, done, met}
     */
    public static function get_member_level_gaps($member_id, $level, $role_counts = null, $pres_counts = null) {
        global $wpdb;
        $history = self::participation_history_table();

        if ($role_counts === null) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT role_name, COUNT(*) as cnt FROM {$history} WHERE member_id = %d AND level_at_completion = %d GROUP BY role_name",
                $member_id, $level
            ), ARRAY_A);
            $role_counts = [];
            foreach ($rows as $r) {
                $role_counts[$r['role_name']] = (int) $r['cnt'];
            }
        }

        if ($pres_counts === null) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT presentation_series, COUNT(*) as cnt FROM {$history}
                 WHERE member_id = %d AND level_at_completion = %d AND presentation_series IS NOT NULL AND presentation_series != ''
                 GROUP BY presentation_series",
                $member_id, $level
            ), ARRAY_A);
            $pres_counts = [];
            foreach ($rows as $r) {
                $pres_counts[$r['presentation_series']] = (int) $r['cnt'];
            }
        }

        $requirements = self::get_level_requirements();
        $level_reqs   = $requirements[$level] ?? [];
        $gaps = [];

        foreach ($level_reqs as $req) {
            if ($req['type'] === 'role') {
                $done = $role_counts[$req['roles'][0]] ?? 0;
                $gaps[] = [
                    'type'  => 'role',
                    'label' => $req['label'],
                    'roles' => $req['roles'],
                    'needed'=> $req['min'],
                    'done'  => $done,
                    'met'   => $done >= $req['min'],
                ];
            } elseif ($req['type'] === 'role_or') {
                $done = 0;
                foreach ($req['roles'] as $r) {
                    $done += $role_counts[$r] ?? 0;
                }
                $gaps[] = [
                    'type'  => 'role_or',
                    'label' => $req['label'],
                    'roles' => $req['roles'],
                    'needed'=> $req['min'],
                    'done'  => min($done, $req['min']),
                    'met'   => $done >= $req['min'],
                ];
            } elseif ($req['type'] === 'presentation') {
                $done = $pres_counts[$req['series']] ?? 0;
                $gaps[] = [
                    'type'   => 'presentation',
                    'label'  => $req['label'],
                    'series' => $req['series'],
                    'needed' => $req['min'],
                    'done'   => $done,
                    'met'    => $done >= $req['min'],
                ];
            }
        }

        // Apply manual overrides (mark unmet requirements as met)
        $override_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, req_key FROM " . self::overrides_table() . " WHERE member_id = %d AND level = %d",
            $member_id, $level
        ), ARRAY_A);
        if (!empty($override_rows)) {
            $override_map = [];
            foreach ($override_rows as $row) {
                $override_map[$row['req_key']] = (int) $row['id'];
            }
            foreach ($gaps as &$gap) {
                if ($gap['met']) {
                    continue;
                }
                $key = self::make_req_key($gap);
                if (isset($override_map[$key])) {
                    $gap['done']            = $gap['needed'];
                    $gap['met']             = true;
                    $gap['manual_override'] = true;
                    $gap['override_id']     = $override_map[$key];
                }
            }
            unset($gap);
        }

        return $gaps;
    }

    // -------------------------------------------------------------------------
    // Requirement override helpers
    // -------------------------------------------------------------------------

    /**
     * Cooloff only applies to high-repetition roles that benefit from rotation:
     * Speaker (Ice Breaker), Toastmaster of the Day, General Evaluator.
     */
    private static function is_cooloff_role($base_role) {
        $lower = strtolower($base_role);
        return preg_match('/^speaker(\s+\d+)?$/i', $base_role)
            || strpos($lower, 'toastmaster') !== false
            || strpos($lower, 'general evaluator') !== false;
    }

    private static function make_req_key($gap) {
        if ($gap['type'] === 'presentation') {
            return $gap['series'];
        }
        return implode('|', $gap['roles']);
    }

    public static function create_requirement_override($member_id, $level, $req_key, $note = '') {
        global $wpdb;
        $wpdb->insert(self::overrides_table(), [
            'member_id'  => absint($member_id),
            'level'      => absint($level),
            'req_key'    => sanitize_text_field($req_key),
            'note'       => sanitize_text_field($note),
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function delete_requirement_override($id, $member_id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::overrides_table(), [
            'id'        => absint($id),
            'member_id' => absint($member_id),
        ]);
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
            $log_file = TMP_PLUGIN_DIR . 'debug.log';
            $timestamp = gmdate('Y-m-d H:i:s');
            @file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
        }
    }

    // -------------------------------------------------------------------------
    // Current member
    // -------------------------------------------------------------------------

    public static function current_member() {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return null;
        }

        global $wpdb;
        $table = self::member_table();

        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, mentor.full_name as mentor_name, mentor.level as mentor_level, mentor.pathway as mentor_pathway, mentor.email as mentor_email
             FROM {$table} m
             LEFT JOIN {$table} mentor ON m.mentor_id = mentor.id
             WHERE m.user_id = %d OR m.email = %s
             LIMIT 1",
            $user->ID,
            $user->user_email
        ), ARRAY_A);

        return $member;
    }

    // -------------------------------------------------------------------------
    // All members
    // -------------------------------------------------------------------------

    public static function members() {
        global $wpdb;
        $table         = self::member_table();
        $history_table = self::participation_history_table();
        $meetings_table = self::meeting_table();

        $last_3_meeting_ids = $wpdb->get_col(
            "SELECT id FROM {$meetings_table} WHERE meeting_date <= CURRENT_DATE ORDER BY meeting_date DESC LIMIT 3"
        );

        $participation_map = [];
        if (!empty($last_3_meeting_ids)) {
            $ids_csv = implode(',', array_map('intval', $last_3_meeting_ids));
            $results = $wpdb->get_results(
                "SELECT member_id, COUNT(DISTINCT meeting_id) as count
                 FROM {$history_table}
                 WHERE meeting_id IN ($ids_csv)
                 GROUP BY member_id",
                ARRAY_A
            );
            foreach ($results as $r) {
                $participation_map[$r['member_id']] = (int) $r['count'];
            }
        }

        $rows = $wpdb->get_results(
            "SELECT m.*, mentor.full_name as mentor_name
             FROM {$table} m
             LEFT JOIN {$table} mentor ON m.mentor_id = mentor.id
             ORDER BY m.full_name ASC",
            ARRAY_A
        );

        $now = time();
        foreach ($rows as &$row) {
            $is_unpaid = !empty($row['paid_until']) && strtotime($row['paid_until']) < $now;
            $is_exempt = !empty($row['is_exempt_from_unpaid_block']);

            $row['level']                         = (int) $row['level'];
            $row['level_completed']               = (int) ($row['level_completed'] ?? 0);
            $row['mentor_id']                     = !empty($row['mentor_id']) ? (int) $row['mentor_id'] : null;
            $row['is_eligible']                   = (!$is_unpaid || $is_exempt);
            $row['formatted_name']                = sprintf("%s (%s Level %d)", $row['full_name'], $row['pathway'], $row['level_completed']);
            $row['recent_participation_count']    = $participation_map[$row['id']] ?? 0;
            $row['total_recent_meetings_checked'] = count($last_3_meeting_ids);
        }

        return $rows;
    }

    public static function get_member($id) {
        global $wpdb;
        $table = self::member_table();
        $row   = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, mentor.full_name as mentor_name
             FROM {$table} m
             LEFT JOIN {$table} mentor ON m.mentor_id = mentor.id
             WHERE m.id = %d",
            $id
        ), ARRAY_A);
        if ($row) {
            $row['level']     = (int) $row['level'];
            $row['mentor_id'] = !empty($row['mentor_id']) ? (int) $row['mentor_id'] : null;
        }
        return $row;
    }

    /**
     * Returns members eligible to be mentors: level >= 2 AND not Inactive/Resigned.
     * Payment check is intentionally excluded — mentoring is a guidance role, not a
     * TI-credit role. The exempt flag still allows non-Active state members to mentor.
     */
    public static function get_eligible_mentors() {
        global $wpdb;
        $table = self::member_table();

        return $wpdb->get_results(
            "SELECT id, full_name, pathway, level
             FROM {$table}
             WHERE level >= 2
               AND (
                   is_exempt_from_unpaid_block = 1
                   OR (state != 'Inactive' AND state != 'Resigned')
               )
             ORDER BY level DESC, full_name ASC",
            ARRAY_A
        );
    }

    /**
     * Members who have had zero roles in the last $weeks weeks.
     * Sorted by longest gap first.
     */
    public static function get_members_due_for_roles($weeks = null) {
        global $wpdb;
        $weeks   = (int) ($weeks ?? get_option('tmp_role_cooloff_weeks', 4));
        $since   = date('Y-m-d', strtotime("-{$weeks} weeks", current_time('timestamp')));
        $table   = self::member_table();
        $history = self::participation_history_table();
        $now     = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.full_name, m.pathway, m.level,
                    MAX(h.meeting_date) as last_role_date,
                    DATEDIFF(%s, COALESCE(MAX(h.meeting_date), m.created_at)) as days_since_role
             FROM {$table} m
             LEFT JOIN {$history} h ON h.member_id = m.id
             WHERE m.is_exempt_from_unpaid_block = 1
                OR (
                    (m.paid_until IS NULL OR m.paid_until >= %s)
                    AND m.state = 'Active'
                )
             GROUP BY m.id
             HAVING (last_role_date IS NULL OR last_role_date < %s)
             ORDER BY days_since_role DESC",
            $now, $now, $since
        ), ARRAY_A);
    }

    // -------------------------------------------------------------------------
    // Role name helpers
    // -------------------------------------------------------------------------

    public static function get_base_role_name($role_name) {
        return trim(preg_replace('/\s*\(.*?\)\s*/', '', $role_name));
    }

    public static function is_singular_role($base_role) {
        if (array_key_exists($base_role, self::get_standard_roles())) {
            return true;
        }
        return (bool) preg_match('/^(Speaker|Evaluator)\s+\d+$/i', $base_role);
    }

    // -------------------------------------------------------------------------
    // Save / delete members
    // -------------------------------------------------------------------------

    public static function save_member($data) {
        global $wpdb;
        $table = self::member_table();
        $now   = current_time('mysql');

        // Partial-update support: when updating an existing record, merge stored
        // values for any key not explicitly supplied by the caller.
        if (!empty($data['id'])) {
            $existing = self::get_member(absint($data['id']));
            if ($existing) {
                foreach ($existing as $key => $value) {
                    if (!array_key_exists($key, $data)) {
                        $data[$key] = $value;
                    }
                }
            }
        }

        // Enforce invariant: level = max(1, min(level_completed + 1, 5))
        $level_completed = max(0, min(5, absint($data['level_completed'] ?? 0)));
        $level           = max(1, min($level_completed + 1, 5));

        $record = array(
            'user_id'                    => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'customer_id'                => sanitize_text_field($data['customer_id'] ?? ''),
            'full_name'                  => sanitize_text_field($data['full_name'] ?? ''),
            'email'                      => sanitize_email($data['email'] ?? ''),
            'phone'                      => sanitize_text_field($data['phone'] ?? ''),
            'pathway'                    => sanitize_text_field($data['pathway'] ?? 'Presentation Mastery'),
            'level'                      => $level,
            'level_completed'            => $level_completed,
            'state'                      => sanitize_text_field($data['state'] ?? 'Active'),
            'paid_until'                 => !empty($data['paid_until']) ? sanitize_text_field($data['paid_until']) : null,
            'is_exempt_from_unpaid_block'=> isset($data['is_exempt_from_unpaid_block']) ? (bool) $data['is_exempt_from_unpaid_block'] : 0,
            'pathways_enrolled'          => sanitize_text_field($data['pathways_enrolled'] ?? ''),
            'current_project'            => sanitize_text_field($data['current_project'] ?? ''),
            'onboarding_status'          => sanitize_text_field($data['onboarding_status'] ?? 'Pending'),
            'orientation_date'           => !empty($data['orientation_date']) ? sanitize_text_field($data['orientation_date']) : null,
            'icebreaker_draft_date'      => !empty($data['icebreaker_draft_date']) ? sanitize_text_field($data['icebreaker_draft_date']) : null,
            'mentor_id'                  => array_key_exists('mentor_id', $data)
                                            ? (!empty($data['mentor_id']) ? absint($data['mentor_id']) : null)
                                            : null,
            'next_action'                => sanitize_text_field($data['next_action'] ?? ''),
            'officer_notes'              => sanitize_textarea_field($data['officer_notes'] ?? ''),
            'updated_at'                 => $now,
        );

        if (empty($record['full_name']) || empty($record['email'])) {
            return new WP_Error('tmp_invalid_member', 'Member name and email are required.', array('status' => 400));
        }

        if (!empty($data['id'])) {
            $old_level = isset($existing['level']) ? (int) $existing['level'] : null;
            $new_level = (int) $record['level'];
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            if ($old_level !== null && $new_level > $old_level) {
                self::record_level_up(
                    absint($data['id']),
                    $record['full_name'],
                    $record['pathway'],
                    $old_level,
                    $new_level
                );
            }
            return self::get_member(absint($data['id']));
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return self::get_member((int) $wpdb->insert_id);
    }

    public static function upsert_imported_member($data) {
        global $wpdb;
        $table      = self::member_table();
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
            // Preserve fields that CSV import must never overwrite
            $preserved = $wpdb->get_row($wpdb->prepare(
                "SELECT mentor_id, orientation_date, officer_notes FROM {$table} WHERE id = %d",
                $existing_id
            ), ARRAY_A);
            if ($preserved) {
                foreach (['mentor_id', 'orientation_date', 'officer_notes'] as $field) {
                    if (!array_key_exists($field, $data)) {
                        $data[$field] = $preserved[$field];
                    }
                }
            }
            $data['id'] = $existing_id;
        }

        return self::save_member($data);
    }

    public static function delete_member($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::member_table(), array('id' => absint($id)));
    }

    private static function record_level_up($member_id, $member_name, $pathway, $old_level, $new_level) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'tmp_level_up_history',
            array(
                'member_id'    => $member_id,
                'member_name'  => $member_name,
                'pathway'      => $pathway,
                'old_level'    => $old_level,
                'new_level'    => $new_level,
                'leveled_up_at'=> current_time('mysql'),
                'meeting_id'   => null,
            )
        );
    }

    public static function get_recent_level_ups($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'tmp_level_up_history';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT member_name, pathway, old_level, new_level, leveled_up_at
                 FROM {$table}
                 ORDER BY leveled_up_at DESC
                 LIMIT %d",
                absint($limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_meeting_summary($meeting_id = null) {
        global $wpdb;
        $history     = self::participation_history_table();
        $members     = self::member_table();
        $meetings    = self::meeting_table();
        $level_ups   = $wpdb->prefix . 'tmp_level_up_history';
        $attendance  = self::attendance_table();
        $wins        = self::win_history_table();

        if ($meeting_id) {
            $mid = absint($meeting_id);
        } else {
            $mid = (int) $wpdb->get_var(
                "SELECT id FROM {$meetings} WHERE meeting_date <= CURDATE() ORDER BY meeting_date DESC LIMIT 1"
            );
        }

        if (!$mid) return null;

        $meeting = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$meetings} WHERE id = %d", $mid),
            ARRAY_A
        );
        if (!$meeting) return null;

        $wrapped_up = !empty($meeting['wrapped_up']);

        // Attendance count: use tmp_attendance when wrapped_up, else fall back to participation_history
        if ($wrapped_up) {
            $participants = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$attendance} WHERE meeting_id = %d AND member_id IS NOT NULL",
                $mid
            ));
            $guest_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$attendance} WHERE meeting_id = %d AND member_id IS NULL",
                $mid
            ));
        } else {
            $participants = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT member_id) FROM {$history} WHERE meeting_id = %d",
                $mid
            ));
            $guest_count = 0;
        }

        $roles = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT role_name FROM {$history} WHERE meeting_id = %d ORDER BY role_name",
            $mid
        )) ?: [];

        $level_up_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT member_name, pathway, old_level, new_level, leveled_up_at
               FROM {$level_ups} WHERE meeting_id = %d ORDER BY leveled_up_at ASC",
            $mid
        ), ARRAY_A) ?: [];

        $now = current_time('Y-m-d');
        $dist_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT level_completed, COUNT(*) as cnt FROM {$members}
                  WHERE state = 'Active'
                    AND (is_exempt_from_unpaid_block = 1 OR paid_until IS NULL OR paid_until >= %s)
                  GROUP BY level_completed ORDER BY level_completed",
                $now
            ),
            ARRAY_A
        ) ?: [];
        $distribution = [];
        foreach ($dist_rows as $row) {
            $distribution[(string) $row['level_completed']] = (int) $row['cnt'];
        }

        $winners = $wpdb->get_results($wpdb->prepare(
            "SELECT category, display_name, role_name, vote_count, is_tie
               FROM {$wins} WHERE meeting_id = %d ORDER BY category",
            $mid
        ), ARRAY_A) ?: [];

        return [
            'meeting_id'         => $mid,
            'meeting_date'       => $meeting['meeting_date'],
            'theme'              => $meeting['theme'],
            'wrapped_up'         => $wrapped_up,
            'participants'       => $participants,
            'attendance_count'   => $participants,
            'guest_count'        => $guest_count,
            'roles_covered'      => $roles,
            'winners'            => $winners,
            'level_ups'          => $level_up_rows,
            'level_distribution' => $distribution,
        ];
    }

    public static function get_role_diversity_leaders($limit = 5) {
        global $wpdb;
        $history = self::participation_history_table();
        $members = self::member_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.full_name, m.pathway, m.level_completed,
                        COUNT(DISTINCT h.role_name) AS distinct_roles,
                        GROUP_CONCAT(DISTINCT h.role_name ORDER BY h.role_name SEPARATOR ', ') AS roles_played
                 FROM {$members} m
                 JOIN {$history} h ON h.member_id = m.id
                 WHERE m.state = 'Active'
                 GROUP BY m.id
                 ORDER BY distinct_roles DESC, m.full_name ASC
                 LIMIT %d",
                absint($limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function delete_meeting($id) {
        global $wpdb;
        $id = absint($id);

        // Remove votes first (they FK to nominees)
        $nominees_tbl = self::vote_nominees_table();
        $votes_tbl    = self::votes_table();
        $nominee_ids  = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$nominees_tbl} WHERE meeting_id = %d", $id
        ));
        if (!empty($nominee_ids)) {
            $safe = implode(',', array_map('intval', $nominee_ids));
            $wpdb->query("DELETE FROM {$votes_tbl} WHERE nominee_id IN ({$safe})");
        }
        $wpdb->delete($nominees_tbl,            ['meeting_id' => $id]);
        $wpdb->delete(self::request_table(),    ['meeting_id' => $id]);
        $wpdb->delete(self::assignment_table(), ['meeting_id' => $id]);
        $wpdb->delete(self::attendance_table(), ['meeting_id' => $id]);
        $wpdb->delete(self::win_history_table(), ['meeting_id' => $id]);
        // participation_history and level_up_history preserved — permanent member records

        return (bool) $wpdb->delete(self::meeting_table(), ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Rebuild agenda
    // -------------------------------------------------------------------------

    /**
     * Deletes and recreates all role_assignment rows for a meeting in the
     * canonical prescribed order, preserving existing member assignments
     * (member_id, speech_title, presentation_series, status) by base role name.
     */
    public static function rebuild_meeting_agenda($meeting_id) {
        global $wpdb;
        $meeting_id = absint($meeting_id);
        $atbl       = self::assignment_table();

        // Step 1: Snapshot member assignments only — keyed by base role.
        // Rebuild always resets durations/timing to template defaults; only who plays
        // each role is preserved. VPE can adjust durations after rebuild if needed.
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT role_name, member_id, speech_title, presentation_series, status
             FROM {$atbl} WHERE meeting_id = %d ORDER BY sort_order",
            $meeting_id
        ), ARRAY_A);

        $saved        = []; // base_role → {member_id, speech_title, presentation_series, status}
        $speaker_nums = [];   // actual slot numbers that exist in the DB
        $role_set     = [];

        foreach ($existing as $row) {
            $base = self::get_base_role_name($row['role_name']);

            if (preg_match('/^Speaker\s+(\d+)$/i', $base, $m)) {
                $speaker_nums[(int) $m[1]] = true;
            }

            if (array_key_exists($base, self::get_standard_roles())) {
                $role_set[$base] = true;
            }

            // Prefer the first row that has a member_id
            if (!isset($saved[$base]) || (!$saved[$base]['member_id'] && $row['member_id'])) {
                $saved[$base] = [
                    'member_id'           => $row['member_id'] ? absint($row['member_id']) : null,
                    'speech_title'        => $row['speech_title'] ?? null,
                    'presentation_series' => $row['presentation_series'] ?? null,
                    'status'              => $row['status'] ?? 'Planned',
                ];
            }
        }

        ksort($speaker_nums);
        $speaker_nums = array_keys($speaker_nums);  // e.g. [1, 3] if Speaker 2 was removed

        // Renumber to sequential: Speaker 3 → Speaker 2, carrying the saved member assignment
        foreach ($speaker_nums as $new_zero_idx => $old_num) {
            $new_num = $new_zero_idx + 1;
            if ($old_num !== $new_num) {
                foreach (['Speaker', 'Evaluator'] as $prefix) {
                    if (isset($saved["{$prefix} {$old_num}"])) {
                        $saved["{$prefix} {$new_num}"] = $saved["{$prefix} {$old_num}"];
                        unset($saved["{$prefix} {$old_num}"]);
                    }
                }
            }
        }
        $total_speeches = count($speaker_nums);
        $selected_roles = array_keys($role_set);

        // Step 2: Delete all existing assignment rows for this meeting
        $wpdb->delete($atbl, ['meeting_id' => $meeting_id]);

        // Step 3: Prescribed agenda — durations are always reset to these template values on rebuild.
        $agenda = [];

        // ── Opening ──────────────────────────────────────────────────────────────
        if (in_array('Sergeant at Arms', $selected_roles))
            $agenda[] = ['role' => 'Sergeant at Arms',       'note' => 'Starts meeting',              'dur' => 2];
        if (in_array('Presiding Officer', $selected_roles))
            $agenda[] = ['role' => 'Presiding Officer',      'note' => 'Address and Guest Introduction', 'dur' => 5];
        if (in_array('Toastmaster of the Day', $selected_roles)) {
            $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces the theme',        'dur' => 2];
            $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces the segments',     'dur' => 2];
        }

        // TMOD introduces each functional role, then role player explains
        $role_intro_map = [
            'Timer'             => ['explain' => 'Explains role',             'dur' => 2],
            'Ah-Counter'        => ['explain' => 'Explains role',             'dur' => 2],
            'Grammarian'        => ['explain' => 'Explains role and WOD/POD', 'dur' => 3],
            'General Evaluator' => ['explain' => 'Explains role',             'dur' => 2],
        ];
        foreach ($role_intro_map as $r => $meta) {
            if (!in_array($r, $selected_roles)) continue;
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => "Introduces {$r}", 'dur' => 1];
            $agenda[]     = ['role' => $r, 'note' => $meta['explain'], 'dur' => $meta['dur']];
        }
        if (in_array('Table Topics Evaluator', $selected_roles))
            $agenda[] = ['role' => 'Table Topics Evaluator', 'note' => 'Introduction of role',        'dur' => 2];

        // ── Educational Presentation ──────────────────────────────────────────
        if (in_array('Educational Presentation', $selected_roles)) {
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces Educational Presentation', 'dur' => 1];
            $agenda[]     = ['role' => 'Educational Presentation', 'note' => 'Presentation', 'dur' => 5];
        }

        // ── Break ─────────────────────────────────────────────────────────────
        $agenda[] = ['role' => 'Break', 'note' => 'Networking', 'dur' => 5];

        // ── Prepared Speeches ─────────────────────────────────────────────────
        for ($i = 1; $i <= $total_speeches; $i++) {
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => "Introduces Evaluator {$i} and Speaker {$i}", 'dur' => 1];
            $agenda[]     = ['role' => "Speaker $i",             'note' => 'Speech',    'dur' => 7];
            $agenda[]     = ['role' => 'Toastmaster of the Day', 'note' => 'Feedback',  'dur' => 1];
        }
        if (in_array('Toastmaster of the Day', $selected_roles))
            $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme interlude', 'dur' => 2];
        if (in_array('Timer', $selected_roles))
            $agenda[] = ['role' => 'Timer', 'note' => 'Report', 'dur' => 1];

        // ── Table Topics ──────────────────────────────────────────────────────
        if (in_array('Table Topics Master', $selected_roles)) {
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces Table Topics Master', 'dur' => 1];
            $agenda[]     = ['role' => 'Table Topics Master',    'note' => 'Table Topics Session',           'dur' => 20];
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme interlude', 'dur' => 2];
        }
        if (in_array('Table Topics Evaluator', $selected_roles))
            $agenda[] = ['role' => 'Table Topics Evaluator', 'note' => 'TT Session Evaluation', 'dur' => 3];

        // ── Evaluation Session ────────────────────────────────────────────────
        if (in_array('Toastmaster of the Day', $selected_roles))
            $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces Evaluation Session', 'dur' => 1];
        for ($i = 1; $i <= $total_speeches; $i++) {
            $agenda[]     = ['role' => "Evaluator $i", 'note' => 'Evaluation', 'dur' => 3];
        }
        if (in_array('Timer', $selected_roles))
            $agenda[] = ['role' => 'Timer', 'note' => 'Report', 'dur' => 1];

        // ── Role-player Reports ───────────────────────────────────────────────
        if (in_array('Grammarian', $selected_roles))
            $agenda[] = ['role' => 'Grammarian',       'note' => 'Report', 'dur' => 4];
        if (in_array('Ah-Counter', $selected_roles))
            $agenda[] = ['role' => 'Ah-Counter',       'note' => 'Report', 'dur' => 3];
        if (in_array('Active Listener', $selected_roles))
            $agenda[] = ['role' => 'Active Listener',  'note' => 'Report', 'dur' => 3];
        if (in_array('General Evaluator', $selected_roles))
            $agenda[] = ['role' => 'General Evaluator', 'note' => 'Final Report', 'dur' => 9];

        // ── Theme Closure + Conclusion ────────────────────────────────────────
        if (in_array('Toastmaster of the Day', $selected_roles))
            $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme Closure', 'dur' => 2];
        if (in_array('Presiding Officer', $selected_roles)) {
            $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Address and Guest Feedback',        'dur' => 5];
            $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Guest Feedback and Announcements',  'dur' => 5];
            $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Concludes the meeting',             'dur' => 1];
        }

        // Step 4: Insert rows in prescribed order, re-applying saved assignments
        $order = 10;
        foreach ($agenda as $item) {
            $full_name = sanitize_text_field($item['role'] . " ({$item['note']})");
            $base      = self::get_base_role_name($full_name);
            $s         = $saved[$base] ?? null;
            $dur       = $item['dur'];
            $timer_dur = $item['timer_dur'] ?? null;
            [$tg, $ty, $tr] = self::get_timing_for_role($full_name, $dur, $timer_dur);

            self::save_assignment([
                'meeting_id'          => $meeting_id,
                'role_name'           => $full_name,
                'duration'            => $dur,
                'timer_duration'      => $timer_dur,
                'status'              => ($s && $s['member_id']) ? ($s['status'] ?: 'Confirmed') : 'Planned',
                'sort_order'          => $order,
                'time_green'          => $tg,
                'time_yellow'         => $ty,
                'time_red'            => $tr,
                'member_id'           => $s['member_id'] ?? null,
                'speech_title'        => $s['speech_title'] ?? null,
                'presentation_series' => $s['presentation_series'] ?? null,
            ]);
            $order += 10;
        }

        return ['rebuilt' => count($agenda), 'speech_slots' => $total_speeches];
    }

    // -------------------------------------------------------------------------
    // Mentor / mentee
    // -------------------------------------------------------------------------

    public static function get_mentees_for_current_user() {
        $me = self::current_member();
        if (!$me) {
            return [];
        }

        global $wpdb;
        $table   = self::member_table();
        $history = self::participation_history_table();
        $meetings = self::meeting_table();

        $last_3_ids = $wpdb->get_col(
            "SELECT id FROM {$meetings} WHERE meeting_date <= CURRENT_DATE ORDER BY meeting_date DESC LIMIT 3"
        );
        $participation_map = [];
        if (!empty($last_3_ids)) {
            $ids_csv = implode(',', array_map('intval', $last_3_ids));
            $results = $wpdb->get_results(
                "SELECT member_id, COUNT(DISTINCT meeting_id) as cnt FROM {$history} WHERE meeting_id IN ($ids_csv) GROUP BY member_id",
                ARRAY_A
            );
            foreach ($results as $r) {
                $participation_map[$r['member_id']] = (int) $r['cnt'];
            }
        }

        $mentees = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE mentor_id = %d ORDER BY full_name ASC",
            (int) $me['id']
        ), ARRAY_A);

        foreach ($mentees as &$m) {
            $m['recent_participation_count']    = $participation_map[$m['id']] ?? 0;
            $m['total_recent_meetings_checked'] = count($last_3_ids);
            $m['is_at_risk']                    = $m['recent_participation_count'] == 0 && count($last_3_ids) > 0;
            $m['milestones']                    = self::calculate_milestones($m);
            $m['level_gaps']                    = self::get_member_level_gaps($m['id'], (int) $m['level']);
            $m['mentorship_stage']              = self::compute_mentorship_stage((int) $m['id'], $m);
            $m['next_action']                   = self::compute_next_action((int) $m['id'], $m);
            $m['mentor_next_action']            = self::compute_mentor_next_action($m['full_name'], $m['mentorship_stage']);
        }

        return $mentees;
    }

    private static function calculate_milestones($member) {
        global $wpdb;
        $history_table = self::participation_history_table();

        $first_role  = $wpdb->get_var($wpdb->prepare("SELECT MIN(meeting_date) FROM {$history_table} WHERE member_id = %d", $member['id']));
        $ice_breaker = $wpdb->get_var($wpdb->prepare(
            "SELECT meeting_date FROM {$history_table} WHERE member_id = %d AND role_name LIKE 'Speaker%%' AND level_at_completion = 1 LIMIT 1",
            $member['id']
        ));

        return [
            'joined'               => $member['created_at'],
            'orientation'          => $member['orientation_date'],
            'first_role'           => $first_role,
            'icebreaker_draft'     => $member['icebreaker_draft_date'],
            'icebreaker_delivered' => $ice_breaker,
            'level1_completed'     => ($member['level'] > 1) ? 'Completed' : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Club KPIs
    // -------------------------------------------------------------------------

    public static function get_club_kpis() {
        global $wpdb;
        $members_table = self::member_table();
        $history_table = self::participation_history_table();

        $avg_first_speech = $wpdb->get_var("
            SELECT AVG(DATEDIFF(h.meeting_date, m.created_at))
            FROM {$members_table} m
            JOIN (SELECT member_id, MIN(meeting_date) as meeting_date FROM {$history_table} WHERE role_name LIKE 'Speaker%%' GROUP BY member_id) h
            ON m.id = h.member_id
        ");

        $active_90 = $wpdb->get_var("SELECT COUNT(DISTINCT member_id) FROM {$history_table} WHERE meeting_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $total     = $wpdb->get_var("SELECT COUNT(*) FROM {$members_table}");

        return [
            'avg_days_to_speech' => round($avg_first_speech ?? 0, 1),
            'retention_rate'     => $total > 0 ? round(($active_90 / $total) * 100, 1) . '%' : '0%',
        ];
    }

    // -------------------------------------------------------------------------
    // Meetings
    // -------------------------------------------------------------------------

    public static function get_meeting($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::meeting_table() . " WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    public static function meetings() {
        global $wpdb;
        $meetings    = self::meeting_table();
        $assignments = self::assignment_table();
        $members     = self::member_table();
        $requests    = self::request_table();

        $rows = $wpdb->get_results(
            "SELECT * FROM {$meetings} ORDER BY meeting_date DESC, id DESC LIMIT 25",
            ARRAY_A
        );
        if (empty($rows)) {
            return [];
        }

        $meeting_ids  = array_column($rows, 'id');
        $all_requests = $wpdb->get_results(
            "SELECT r.*, a.role_name, m.full_name as member_name
             FROM {$requests} r
             JOIN {$assignments} a ON r.assignment_id = a.id
             JOIN {$members} m ON r.member_id = m.id
             WHERE r.meeting_id IN (" . implode(',', array_map('intval', $meeting_ids)) . ")
             ORDER BY r.priority ASC, r.created_at ASC",
            ARRAY_A
        ) ?: [];

        foreach ($rows as &$meeting) {
            $meeting_reqs = array_filter($all_requests, fn($r) => (int) $r['meeting_id'] === (int) $meeting['id']);

            $meeting['assignments'] = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, m.full_name AS member_name
                 FROM {$assignments} a
                 LEFT JOIN {$members} m ON m.id = a.member_id
                 WHERE a.meeting_id = %d
                 ORDER BY a.sort_order ASC, a.id ASC",
                $meeting['id']
            ), ARRAY_A) ?: [];

            foreach ($meeting['assignments'] as &$assignment) {
                $base_target    = self::get_base_role_name($assignment['role_name']);
                $generic_target = trim(preg_replace('/\s+\d+$/', '', $base_target));

                $matches = array_filter($meeting_reqs, function ($r) use ($generic_target) {
                    $base_req    = self::get_base_role_name($r['role_name']);
                    $generic_req = trim(preg_replace('/\s+\d+$/', '', $base_req));
                    return $generic_req === $generic_target;
                });

                $assignment['request_count']   = count($matches);
                $assignment['first_requester'] = !empty($matches) ? reset($matches)['member_name'] : null;

                if (!empty($assignment['member_id'])) {
                    $assignment['suitability'] = self::check_suitability($assignment['role_name'], $assignment['member_id']);
                }

                // Fallback: compute timing for rows created before timing columns existed
                if ($assignment['time_green'] === null) {
                    [$tg, $ty, $tr] = self::get_timing_for_role($assignment['role_name'], (int) ($assignment['duration'] ?? 0), $assignment['timer_duration'] ?? null);
                    $assignment['time_green']  = $tg;
                    $assignment['time_yellow'] = $ty;
                    $assignment['time_red']    = $tr;
                }
            }
        }

        return $rows;
    }

    public static function get_published_agenda() {
        global $wpdb;
        $meetings    = self::meeting_table();
        $assignments = self::assignment_table();
        $members     = self::member_table();

        $meeting = $wpdb->get_row(
            "SELECT * FROM {$meetings} WHERE is_published = 1 ORDER BY meeting_date ASC LIMIT 1",
            ARRAY_A
        );
        if (!$meeting) return null;

        $meeting['assignments'] = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, m.full_name AS member_name
             FROM {$assignments} a
             LEFT JOIN {$members} m ON m.id = a.member_id
             WHERE a.meeting_id = %d
             ORDER BY a.sort_order ASC, a.id ASC",
            $meeting['id']
        ), ARRAY_A) ?: [];

        foreach ($meeting['assignments'] as &$assignment) {
            if ($assignment['time_green'] === null) {
                [$tg, $ty, $tr] = self::get_timing_for_role($assignment['role_name'], (int) ($assignment['duration'] ?? 0), $assignment['timer_duration'] ?? null);
                $assignment['time_green']  = $tg;
                $assignment['time_yellow'] = $ty;
                $assignment['time_red']    = $tr;
            }
        }

        return $meeting;
    }

    // -------------------------------------------------------------------------
    // Suitability check
    // -------------------------------------------------------------------------

    /**
     * @param string $role_name
     * @param int    $member_id
     * @param array  $participation_at_level  [role_name => count] for the member's current level (optional)
     */
    public static function check_suitability($role_name, $member_id, $participation_at_level = null) {
        $member = self::get_member($member_id);
        if (!$member) {
            return ['suitable' => false, 'reason' => 'No member'];
        }

        $now       = time();
        $is_unpaid = !empty($member['paid_until']) && strtotime($member['paid_until']) < $now;
        $is_exempt = !empty($member['is_exempt_from_unpaid_block']);
        if ($is_unpaid && !$is_exempt) {
            return ['suitable' => false, 'reason' => 'Unpaid Member'];
        }

        $role  = strtolower($role_name);
        $level_completed = (int) ($member['level_completed'] ?? 0);
        $level = (int) $member['level'];

        // Dynamic level gate — patterns ordered so longer/more-specific match first
        $gate_levels = self::get_current_gate_levels();
        foreach ($gate_levels as $pattern => $min_level) {
            if (strpos($role, $pattern) !== false) {
                $gate = (int) $min_level;
                return $level_completed >= $gate
                    ? ['suitable' => true,  'reason' => "L{$gate}+"]
                    : ['suitable' => false, 'reason' => "Needs L{$gate}+"];
            }
        }

        // L1 ordering: Ice Breaker (Speaker at L1) only after Table Topics Speaker at L1
        if ($level_completed >= 1 && preg_match('/^speaker(\s+\d+)?$/i', trim($role_name))) {
            $counts = $participation_at_level ?? self::get_member_participation_counts_for_member($member_id)[1] ?? [];
            $tts_done = isset($counts['Table Topics Speaker']) ? (int) $counts['Table Topics Speaker'] : 0;
            if ($tts_done === 0) {
                return ['suitable' => false, 'reason' => 'Must do TT Speaker before Ice Breaker'];
            }
        }

        return ['suitable' => true, 'reason' => 'Suitable'];
    }

    // -------------------------------------------------------------------------
    // Recommendations
    // -------------------------------------------------------------------------

    public static function get_recommendations($member) {
        $level = (int) ($member['level'] ?? 1);
        $recs  = [];

        if ($level <= 1) {
            $recs[] = ['title' => 'Table Topics Speaker', 'type' => 'Role',   'note' => 'Required before your Ice Breaker speech at Level 1.'];
            $recs[] = ['title' => 'Ice Breaker',          'type' => 'Speech', 'note' => 'Your first 4–6 minute speech — after Table Topics Speaker.'];
            $recs[] = ['title' => 'Evaluator',            'type' => 'Role',   'note' => 'Required at Level 1.'];
        } elseif ($level === 2) {
            $recs[] = ['title' => 'Grammarian',           'type' => 'Role',   'note' => 'Required at Level 2.'];
            $recs[] = ['title' => 'Table Topics Master',  'type' => 'Role',   'note' => 'Required at Level 2.'];
        } elseif ($level === 3) {
            $recs[] = ['title' => 'Toastmaster of the Day', 'type' => 'Role', 'note' => 'Required at Level 3.'];
            $recs[] = ['title' => 'Educational Presentation (Successful Club Series)', 'type' => 'Presentation', 'note' => 'Required at Level 3.'];
        } elseif ($level === 4) {
            $recs[] = ['title' => 'General Evaluator',   'type' => 'Role',   'note' => 'Required at Level 4.'];
            $recs[] = ['title' => 'Educational Presentation (Better Speaker Series)', 'type' => 'Presentation', 'note' => 'Required at Level 4.'];
        } else {
            $recs[] = ['title' => "Level {$level} Project Speech", 'type' => 'Speech', 'note' => 'Focus on your path-specific project.'];
        }

        return $recs;
    }

    // -------------------------------------------------------------------------
    // Mentor program lifecycle
    // Stages: no_mentor → assigned → orientation_complete → icebreaker_delivered
    //         → level1_complete → closed
    // -------------------------------------------------------------------------

    public static function compute_mentorship_stage($member_id, $member) {
        $level = (int) ($member['level'] ?? 1);

        // Closed: Level 1 in DB means Level 1 is completed — mentor no longer needed
        if ($level >= 1) {
            return 'closed';
        }

        // No mentor assigned yet
        if (empty($member['mentor_id'])) {
            return 'no_mentor';
        }

        // Ice Breaker check (Speaker role at L1 in participation history)
        $counts_by_level = self::get_member_participation_counts_for_member($member_id);
        $l1_counts       = $counts_by_level[1] ?? [];
        $ib_done         = false;
        foreach ($l1_counts as $role => $cnt) {
            if (preg_match('/^speaker(\s+\d+)?$/i', trim($role)) && $cnt > 0) {
                $ib_done = true;
                break;
            }
        }

        if ($ib_done) {
            // Check whether all L1 gaps are met (level1_complete vs still working)
            $gaps  = self::get_member_level_gaps($member_id, 1);
            $unmet = array_filter($gaps, fn($g) => !$g['met']);
            return empty($unmet) ? 'level1_complete' : 'icebreaker_delivered';
        }

        if (!empty($member['orientation_date'])) {
            return 'orientation_complete';
        }

        return 'assigned';
    }

    /**
     * Returns the action the MENTOR should take for a specific mentee at the given stage.
     */
    public static function compute_mentor_next_action($mentee_name, $stage) {
        $map = [
            'no_mentor'             => null,
            'assigned'              => "Schedule orientation with {$mentee_name}.",
            'orientation_complete'  => "Help {$mentee_name} request their first role.",
            'icebreaker_delivered'  => "Guide {$mentee_name} through remaining Level 1 requirements.",
            'level1_complete'       => "Help {$mentee_name} submit Level 1 completion on the TI portal.",
            'closed'                => "Mentorship with {$mentee_name} is complete.",
        ];
        return $map[$stage] ?? "Check in with {$mentee_name}.";
    }

    // -------------------------------------------------------------------------
    // Computed next action (mentee / member perspective)
    // L1 path is driven by mentorship stage; L2+ by level gaps then activity.
    // -------------------------------------------------------------------------

    public static function compute_next_action($member_id, $member) {
        $level    = (int) ($member['level']             ?? 1);
        $pathway  =       ($member['pathway']           ?? '');
        $enrolled =       ($member['pathways_enrolled'] ?? '');

        // 1. Pathway not registered
        if ($pathway === 'No pathway registered' || !$enrolled || strtolower($enrolled) === 'no') {
            return 'Register for a Pathway on the TI portal.';
        }

        // 2–5. L1 path driven entirely by mentorship stage (level 0 = enrolled, pre-L1)
        if ($level <= 1) {
            $stage = self::compute_mentorship_stage($member_id, $member);
            switch ($stage) {
                case 'no_mentor':
                    return 'Ask your VP Education to assign you a mentor.';
                case 'assigned':
                    return 'Schedule your orientation meeting with your mentor.';
                case 'orientation_complete':
                    return 'Request a Table Topics Speaker role at your next meeting.';
                case 'icebreaker_delivered':
                    // Fall through to gap checks — IB done but L1 reqs remain
                    break;
                case 'level1_complete':
                    return 'Submit Level 1 completion on the TI portal.';
            }
        }

        // 6 & 7. Level gaps (all levels)
        $gaps  = self::get_member_level_gaps($member_id, $level);
        $unmet = array_filter($gaps, fn($g) => !$g['met']);
        if (!empty($unmet)) {
            $first = reset($unmet);
            return "Level {$level}: complete {$first['label']}.";
        }
        if (!empty($gaps)) {
            return "All Level {$level} requirements met — submit completion on the TI portal.";
        }

        // 8. No recent role
        global $wpdb;
        $history       = self::participation_history_table();
        $cooloff_weeks = (int) get_option('tmp_role_cooloff_weeks', 4);
        $since         = date('Y-m-d', strtotime("-{$cooloff_weeks} weeks", current_time('timestamp')));
        $last_role     = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(meeting_date) FROM {$history} WHERE member_id = %d",
            $member_id
        ));
        if (!$last_role || $last_role < $since) {
            $weeks_ago = $last_role
                ? (int) floor((current_time('timestamp') - strtotime($last_role)) / (7 * 86400))
                : null;
            $suffix = $weeks_ago !== null ? " — {$weeks_ago} weeks since your last role" : '';
            return "Request a role for an upcoming meeting{$suffix}.";
        }

        // 9. Default
        return "Continue {$pathway} Level {$level}.";
    }

    // -------------------------------------------------------------------------
    // Open slots for member role requests
    // -------------------------------------------------------------------------

    public static function get_open_slots() {
        global $wpdb;
        $meetings    = self::meeting_table();
        $assignments = self::assignment_table();
        $history     = self::participation_history_table();
        $today = current_time('Y-m-d');
        $now   = current_time('mysql');

        $open_slots = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id as meeting_id, m.meeting_date, m.theme, m.requests_close_at,
                    a.id as assignment_id, a.role_name,
                    (SELECT COUNT(*) FROM " . self::request_table() . " r WHERE r.assignment_id = a.id) as current_requests
             FROM {$meetings} m
             JOIN {$assignments} a ON m.id = a.meeting_id
             WHERE m.meeting_date >= %s
               AND (m.requests_close_at IS NULL OR m.requests_close_at >= %s)
               AND (a.member_id IS NULL OR a.member_id = 0 OR a.member_id = '')
               AND a.role_name NOT LIKE 'Break%%'
               AND a.role_name NOT LIKE 'Table Topics Speaker%%'
             ORDER BY m.meeting_date ASC LIMIT 50",
            $today,
            $now
        ), ARRAY_A);

        $member       = self::current_member();
        $member_level           = $member ? (int) $member['level'] : 1;
        $member_level_completed = $member ? (int) $member['level_completed'] : 0;
        $member_id              = $member ? (int) $member['id']    : 0;

        $participation = $member ? self::get_member_participation_counts_for_member($member_id) : [];
        $level_counts  = $participation[$member_level] ?? [];

        // Build per-role cooloff map for this member
        $cooloff_weeks  = (int) get_option('tmp_role_cooloff_weeks', 4);
        $cooloff_since  = date('Y-m-d', strtotime("-{$cooloff_weeks} weeks", current_time('timestamp')));
        $cooloff_info   = [];

        if ($member_id) {
            $recent = $wpdb->get_results($wpdb->prepare(
                "SELECT role_name, MAX(meeting_date) as last_date
                 FROM {$history}
                 WHERE member_id = %d AND meeting_date >= %s
                 GROUP BY role_name",
                $member_id, $cooloff_since
            ), ARRAY_A);
            foreach ($recent as $r) {
                $eligible_ts = strtotime($r['last_date']) + ($cooloff_weeks * 7 * 86400);
                $cooloff_info[$r['role_name']] = [
                    'in_cooloff'     => true,
                    'last_performed' => $r['last_date'],
                    'eligible_from'  => date('Y-m-d', $eligible_ts),
                ];
            }
        }

        // Attach per-slot flags
        foreach ($open_slots as &$slot) {
            $base_role = self::get_base_role_name($slot['role_name']);

            // Check level requirement from gate settings
            $min_level = 0; // Default: no requirement (use settings only)
            $gate_levels = self::get_current_gate_levels();
            foreach ($gate_levels as $pattern => $level) {
                if (stripos($base_role, $pattern) !== false) {
                    $min_level = (int) $level;
                    break;
                }
            }
            $slot['qualified']   = $member_level_completed >= $min_level;
            $slot['requirement'] = $min_level > 0 ? "Level {$min_level}+ completed required" : "";

            // Cooloff only for Speaker / TMOD / GE — not for Timer, Grammarian, etc.
            $slot['cooloff'] = self::is_cooloff_role($base_role) ? ($cooloff_info[$base_role] ?? null) : null;

            // is_goal: in member's current-level unmet requirements
            $needed_roles = [];
            foreach (self::get_level_requirements()[$member_level] ?? [] as $req) {
                foreach ($req['roles'] ?? [] as $r) {
                    $needed_roles[] = $r;
                }
            }
            $slot['is_goal'] = in_array($base_role, $needed_roles, true) && !isset($level_counts[$base_role]);
        }

        return [
            'slots'            => $open_slots,
            'member_level'     => $member_level,
            'member_participation' => $participation,
        ];
    }

    // -------------------------------------------------------------------------
    // Request Scoring & Eligibility (Post-Deadline Approval)
    // -------------------------------------------------------------------------

    /**
     * Score a single request for ranking during VPE approval.
     * Higher score = higher priority for assignment.
     *
     * Scoring:
     * - Priority: P1=75, P2=50, P3=25
     * - Level: L0=0, L5=30
     * - Goal role: +50
     * - Fairness (days since last role): 0-20
     * - Cooloff penalty: -100
     */
    public static function score_request($request, $member_data) {
        $score = 0;

        // Priority: P1=75, P2=50, P3=25
        $priority = (int) ($request['priority'] ?? 3);
        $score += (4 - $priority) * 25;

        // Member level: L0=0 ... L5=30
        $member_level = (int) ($member_data['level'] ?? 1);
        $score += $member_level * 6;

        // Is goal role for member's level?
        $role_name = $request['role_name'] ?? '';
        $member_id = (int) ($member_data['id'] ?? 0);
        if (self::is_goal_role_for_level($member_id, $role_name, $member_level)) {
            $score += 50;
        }

        // Fairness: days since last role (0-20 points)
        $days_since = self::get_days_since_last_role($member_id);
        $score += min(20, (int) floor($days_since / 7));

        // Cooloff penalty: -100 if in cooloff
        $base_role = self::get_base_role_name($role_name);
        if (self::is_in_cooloff_now($member_id, $base_role)) {
            $score -= 100;
        }

        return max(0, $score); // Ensure non-negative
    }

    /**
     * Check if role is a goal (needed for member's level advancement)
     */
    private static function is_goal_role_for_level($member_id, $role_name, $level) {
        $base_role = self::get_base_role_name($role_name);
        $level_reqs = self::get_level_requirements();
        $level_req = $level_reqs[$level] ?? [];

        foreach ($level_req as $req) {
            if ($req['type'] === 'role' || $req['type'] === 'role_or') {
                foreach ($req['roles'] ?? [] as $needed_role) {
                    if (strtolower($base_role) === strtolower($needed_role)) {
                        // Check if member already completed this requirement
                        $counts = self::get_member_participation_counts_for_member($member_id);
                        $count = $counts[$level][$base_role] ?? 0;
                        if ($count < ($req['min'] ?? 1)) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get days since member's last role completion
     */
    private static function get_days_since_last_role($member_id) {
        global $wpdb;
        $history = self::participation_history_table();
        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(meeting_date) FROM {$history} WHERE member_id = %d",
            $member_id
        ));

        if (!$last_date) {
            return PHP_INT_MAX; // Never had a role
        }

        $days = floor((current_time('timestamp') - strtotime($last_date)) / 86400);
        return max(0, $days);
    }

    /**
     * Check if member is currently in cooloff for a role
     */
    private static function is_in_cooloff_now($member_id, $base_role) {
        if (!self::is_cooloff_role($base_role)) {
            return false;
        }

        global $wpdb;
        $history = self::participation_history_table();
        $cooloff_weeks = (int) get_option('tmp_role_cooloff_weeks', 4);

        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(meeting_date) FROM {$history}
             WHERE member_id = %d AND role_name = %s",
            $member_id,
            $base_role
        ));

        if (!$last_date) {
            return false;
        }

        $eligible_ts = strtotime($last_date) + ($cooloff_weeks * 7 * 86400);
        return current_time('timestamp') < $eligible_ts;
    }

    /**
     * Generate human-readable reason why request was not selected
     */
    public static function get_reason_for_not_selected($request, $winning_request, $all_requests_for_slot) {
        $base_role = self::get_base_role_name($request['role_name'] ?? '');
        $member = self::get_member($request['member_id'] ?? 0);
        $winning_member = self::get_member($winning_request['member_id'] ?? 0);

        // Reason 1: Higher priority selected
        if ($winning_request['priority'] < $request['priority']) {
            $winner_name = $winning_member['full_name'] ?? 'Another member';
            return "{$winner_name}'s P{$winning_request['priority']} priority ranked higher than your P{$request['priority']}.";
        }

        // Reason 2: Level requirement not met
        $gate_levels = self::get_current_gate_levels();
        foreach ($gate_levels as $pattern => $min_level) {
            if (strpos(strtolower($base_role), $pattern) !== false) {
                $member_level = (int) ($member['level'] ?? 1);
                if ($member_level < $min_level) {
                    $winner_name = $winning_member['full_name'] ?? 'Another member';
                    return "You are Level {$member_level}, but {$base_role} requires Level {$min_level}+. {$winner_name} was selected.";
                }
                break;
            }
        }

        // Reason 3: In cooloff
        if (self::is_in_cooloff_now($request['member_id'] ?? 0, $base_role)) {
            $eligible_date = self::get_cooloff_eligible_date($request['member_id'] ?? 0, $base_role);
            return "You are in cooloff for {$base_role} until {$eligible_date}. Available again after that date.";
        }

        // Reason 4: Multiple requests, other priorities better
        if (count($all_requests_for_slot) > 1) {
            return "Multiple requests received. Other members' priority or qualifications ranked higher.";
        }

        return "Your request was not selected for this role.";
    }

    /**
     * Get date when member becomes eligible from cooloff
     */
    private static function get_cooloff_eligible_date($member_id, $base_role) {
        global $wpdb;
        $history = self::participation_history_table();
        $cooloff_weeks = (int) get_option('tmp_role_cooloff_weeks', 4);

        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(meeting_date) FROM {$history}
             WHERE member_id = %d AND role_name = %s",
            $member_id,
            $base_role
        ));

        if (!$last_date) {
            return date('Y-m-d');
        }

        $eligible_ts = strtotime($last_date) + ($cooloff_weeks * 7 * 86400);
        return date('Y-m-d', $eligible_ts);
    }

    /**
     * Approve all recommended requests for a meeting (VPE bulk approval)
     * Returns: { approved: count, failed: [{ member, role, reason }] }
     */
    public static function approve_all_recommended($meeting_id = null) {
        $approved_count = 0;
        $failed = [];

        // Continuously re-fetch pending requests after each approval
        // This ensures cascading rejections are reflected immediately
        while (true) {
            $pending = self::get_all_pending_requests();
            if (empty($pending)) {
                break;
            }

            $found_any = false;

            foreach ($pending as $meeting) {
                // If specific meeting, skip others
                if ($meeting_id && $meeting['meetingId'] !== $meeting_id) {
                    continue;
                }

                // For each role, approve the recommended request (which cascades to reject other member requests)
                foreach ($meeting['roles'] as $role) {
                    $recommended = null;
                    foreach ($role['requests'] as $req) {
                        if ($req['isRecommended']) {
                            $recommended = $req;
                            break;
                        }
                    }

                    if (!$recommended) {
                        continue;
                    }

                    // Approve this request (cascade-reject member's other requests automatically)
                    $result = self::approve_request_and_cascade_reject(
                        $recommended['requestId'],
                        $recommended['memberId'],
                        $meeting['meetingId'],
                        $role['roleName']
                    );

                    if (is_wp_error($result)) {
                        $failed[] = [
                            'member' => $recommended['memberName'],
                            'role' => $role['roleName'],
                            'reason' => $result->get_error_message()
                        ];
                        continue;
                    }

                    $approved_count++;
                    $found_any = true;
                    break; // Exit role loop to re-fetch fresh pending list
                }

                if ($found_any) {
                    break; // Exit meeting loop to re-fetch fresh pending list
                }
            }

            // If no recommendations found in this iteration, we're done
            if (!$found_any) {
                break;
            }
        }

        return [
            'approved' => $approved_count,
            'failed' => $failed,
            'success' => count($failed) === 0
        ];
    }

    /**
     * VPE approves a specific request and cascade-rejects member's other requests for that meeting
     * Ensures one assignment per member per meeting
     */
    public static function approve_request_and_cascade_reject($request_id, $member_id, $meeting_id, $role_name) {
        global $wpdb;
        $requests = self::request_table();

        // Guard: member cannot hold two roles at the same meeting
        $already_approved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$requests}
             WHERE member_id = %d AND meeting_id = %d AND status = 'Approved'",
            $member_id,
            $meeting_id
        ));
        if ($already_approved > 0) {
            return new WP_Error('tmp_already_approved', 'Member already has an approved role at this meeting', ['status' => 400]);
        }

        // Fetch the request (get assignment_id for slot-exact matching)
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT id, assignment_id FROM {$requests}
             WHERE id = %d AND member_id = %d AND status = 'Pending'",
            $request_id,
            $member_id
        ), ARRAY_A);

        if (!$request) {
            return new WP_Error('tmp_not_found', 'Request not found or already processed', ['status' => 404]);
        }

        $assignment_id = (int) $request['assignment_id'];

        // Re-validate eligibility before approving
        $validation = self::validate_request_eligibility($member_id, $assignment_id, $role_name);
        if (!$validation['eligible']) {
            return new WP_Error('tmp_ineligible', $validation['reason'], ['status' => 400]);
        }

        // Update only the specific assignment slot — bypass save_assignment's singular-role
        // propagation which was designed for the manual VPE workflow and would incorrectly
        // overwrite other variant slots (e.g. "Speaker 1 (Intro)") when one is confirmed.
        $now_assign = current_time('mysql');
        $wpdb->update(
            self::assignment_table(),
            ['member_id' => $member_id, 'status' => 'Confirmed', 'updated_at' => $now_assign],
            ['id' => $assignment_id],
            ['%d', '%s', '%s'],
            ['%d']
        );
        if ($wpdb->last_error) {
            return new WP_Error('tmp_db_error', 'Failed to update assignment: ' . $wpdb->last_error, ['status' => 500]);
        }

        $base_role = self::get_base_role_name($role_name);
        $now = current_time('mysql');

        // Mark this request as Approved
        $wpdb->update(
            $requests,
            ['status' => 'Approved', 'updated_at' => $now],
            ['id' => $request_id],
            ['%s', '%s'], ['%d']
        );

        // STEP 1: Reject member's OTHER pending requests at this meeting
        // Uses r.meeting_id directly — no join needed, avoids any mismatch
        $wpdb->query($wpdb->prepare(
            "UPDATE {$requests}
             SET status = 'NotSelected',
                 reason = %s,
                 updated_at = %s
             WHERE member_id = %d
               AND meeting_id = %d
               AND id != %d
               AND status = 'Pending'",
            "You were assigned {$base_role} at this meeting",
            $now,
            $member_id,
            $meeting_id,
            $request_id
        ));

        // STEP 2: Reject OTHER members' requests for this exact assignment slot only
        // Scoped to assignment_id so unrelated roles are never touched
        $wpdb->query($wpdb->prepare(
            "UPDATE {$requests}
             SET status = 'NotSelected',
                 reason = %s,
                 updated_at = %s
             WHERE assignment_id = %d
               AND member_id != %d
               AND status = 'Pending'",
            "{$base_role} has been assigned to another member",
            $now,
            $assignment_id,
            $member_id
        ));

        return [
            'success'      => true,
            'message'      => 'Request approved',
            'approved_role' => $base_role,
        ];
    }

    /**
     * Validate if request can be approved (re-validate at approval time)
     */
    private static function validate_request_eligibility($member_id, $assignment_id, $role_name) {
        $member = self::get_member($member_id);
        if (!$member) {
            return ['eligible' => false, 'reason' => 'Member not found'];
        }

        $member_level = (int) $member['level'];
        $base_role = self::get_base_role_name($role_name);

        // Check level gate
        $gate_levels = self::get_current_gate_levels();
        foreach ($gate_levels as $pattern => $min_level) {
            if (strpos(strtolower($base_role), $pattern) !== false) {
                $min = (int) $min_level;
                if ($member_level < $min) {
                    return [
                        'eligible' => false,
                        'reason' => "Member is Level {$member_level}, requires Level {$min}+"
                    ];
                }
                break;
            }
        }

        // Check cooloff (re-validate)
        if (self::is_in_cooloff_now($member_id, $base_role)) {
            $eligible_date = self::get_cooloff_eligible_date($member_id, $base_role);
            return [
                'eligible' => false,
                'reason' => "Member in cooloff until {$eligible_date}"
            ];
        }

        // Check payment status
        $now = time();
        $is_unpaid = !empty($member['paid_until']) && strtotime($member['paid_until']) < $now;
        $is_exempt = !empty($member['is_exempt_from_unpaid_block']);

        if ($is_unpaid && !$is_exempt) {
            return [
                'eligible' => false,
                'reason' => 'Member payment overdue'
            ];
        }

        return ['eligible' => true];
    }

    // -------------------------------------------------------------------------
    // Suggestions engine
    // -------------------------------------------------------------------------

    public static function get_suggestions($meeting_id) {
        global $wpdb;
        $assignments_table = self::assignment_table();
        $history_table     = self::participation_history_table();

        $trace = [];
        $trace[] = "Suggestion engine v3 — meeting ID: $meeting_id";

        $all_assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role_name, member_id FROM {$assignments_table} WHERE meeting_id = %d",
            $meeting_id
        ), ARRAY_A);

        $trace[] = "Total roles: " . count($all_assignments);

        $meeting_details = $wpdb->get_row($wpdb->prepare(
            "SELECT requests_close_at FROM " . self::meeting_table() . " WHERE id = %d",
            $meeting_id
        ), ARRAY_A);

        $requests_deadline_passed = false;
        if ($meeting_details && !empty($meeting_details['requests_close_at'])) {
            $current_time             = current_time('mysql');
            $requests_deadline_passed = strtotime($current_time) > strtotime($meeting_details['requests_close_at']);
            $trace[] = "Deadline: {$meeting_details['requests_close_at']} — passed: " . ($requests_deadline_passed ? 'Yes' : 'No');
        } else {
            $trace[] = "No requests deadline set.";
        }

        $singular_role_map = [];
        $assigned_ids      = [];

        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, a.role_name
             FROM " . self::request_table() . " r
             JOIN {$assignments_table} a ON r.assignment_id = a.id
             WHERE r.meeting_id = %d
             ORDER BY r.priority ASC, r.created_at ASC",
            $meeting_id
        ), ARRAY_A);

        foreach ($all_assignments as $asgn) {
            $m_id = (int) $asgn['member_id'];
            if ($m_id > 0) {
                $assigned_ids[] = $m_id;
                $base = self::get_base_role_name($asgn['role_name']);
                if (self::is_singular_role($base)) {
                    $singular_role_map[$base] = $m_id;
                }
            }
        }
        $assigned_ids = array_unique($assigned_ids);

        $slots_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role_name FROM {$assignments_table} WHERE meeting_id = %d AND (member_id IS NULL OR member_id = 0 OR member_id = '')",
            $meeting_id
        ), ARRAY_A);

        $slots = array_filter($slots_raw, function ($s) {
            $lower = strtolower($s['role_name']);
            return strpos($lower, 'presiding officer') === false
                && strpos($s['role_name'], 'Break') !== 0;
        });

        // Non-speaker slots processed first so the +40 gap bonus attracts members
        // toward role requirements before speaker (Ice Breaker) slots are considered.
        usort($slots, function ($a, $b) {
            $a_spk = (bool) preg_match('/^speaker(\s+\d+)?$/i', self::get_base_role_name($a['role_name']));
            $b_spk = (bool) preg_match('/^speaker(\s+\d+)?$/i', self::get_base_role_name($b['role_name']));
            return $a_spk - $b_spk;
        });

        $open_count = count($slots);
        $trace[] = "Open slots: $open_count";

        if ($open_count === 0) {
            $trace[] = count($all_assignments) === 0
                ? "Add roles to this meeting first."
                : "All roles already assigned.";
            return ['suggestions' => [], 'trace' => $trace];
        }

        $all_members = self::members();
        $members     = array_filter($all_members, fn($m) => !empty($m['is_eligible']));
        $trace[] = count($members) . " eligible members.";

        $suggestions = [];

        // Pre-compute participation counts for all members [member_id => [level => [role => count]]]
        $all_counts = self::get_member_participation_counts();

        // Pre-compute: last role date per member (for recency scoring)
        $last_role_rows = $wpdb->get_results(
            "SELECT member_id, MAX(meeting_date) as last_date FROM {$history_table} GROUP BY member_id",
            ARRAY_A
        );
        $last_role_map = [];
        foreach ($last_role_rows as $r) {
            $last_role_map[(int) $r['member_id']] = $r['last_date'];
        }

        // Pre-compute: cooloff map [member_id][base_role] = last_date (if within cooloff window)
        $cooloff_weeks    = (int) get_option('tmp_role_cooloff_weeks', 4);
        $cooloff_boundary = date('Y-m-d', strtotime("-{$cooloff_weeks} weeks", current_time('timestamp')));
        $recent_roles     = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, role_name, MAX(meeting_date) as last_date
             FROM {$history_table}
             WHERE meeting_date >= %s
             GROUP BY member_id, role_name",
            $cooloff_boundary
        ), ARRAY_A);
        $cooloff_map = [];
        foreach ($recent_roles as $r) {
            $cooloff_map[(int) $r['member_id']][$r['role_name']] = $r['last_date'];
        }

        // Pre-compute: presentation series counts per member per level
        $all_pres = $wpdb->get_results(
            "SELECT member_id, level_at_completion, presentation_series, COUNT(*) as cnt
             FROM {$history_table}
             WHERE presentation_series IS NOT NULL AND presentation_series != ''
             GROUP BY member_id, level_at_completion, presentation_series",
            ARRAY_A
        );
        $pres_map = [];
        foreach ($all_pres as $r) {
            $pres_map[(int) $r['member_id']][(int) $r['level_at_completion']][$r['presentation_series']] = (int) $r['cnt'];
        }

        // ── Phase 1: Satisfy member requests (deadline must have passed) ──────
        if ($requests_deadline_passed) {
            for ($p = 1; $p <= 3; $p++) {
                foreach ($slots as $slot) {
                    if (isset($suggestions[$slot['id']])) {
                        continue;
                    }

                    $base_target    = self::get_base_role_name($slot['role_name']);
                    $generic_target = trim(preg_replace('/\s+\d+$/', '', $base_target));

                    $possible = array_filter($requests, function ($r) use ($p, $generic_target) {
                        $base_req    = self::get_base_role_name($r['role_name']);
                        $generic_req = trim(preg_replace('/\s+\d+$/', '', $base_req));
                        return (int) $r['priority'] === $p && $generic_req === $generic_target;
                    });

                    foreach ($possible as $req) {
                        $m_id = (int) $req['member_id'];
                        if (in_array($m_id, $assigned_ids)) {
                            continue;
                        }
                        $m_data = array_values(array_filter($members, fn($m) => (int) $m['id'] === $m_id))[0] ?? null;
                        if ($m_data) {
                            $suggestions[$slot['id']] = [
                                'id'                   => (int) $slot['id'],
                                'role_name'            => $slot['role_name'],
                                'suggested_member_id'  => $m_id,
                                'suggested_member_name'=> $m_data['full_name'],
                            ];
                            $assigned_ids[] = $m_id;
                            $trace[] = "P{$p} request: {$m_data['full_name']} → {$slot['role_name']}";
                            $base = self::get_base_role_name($slot['role_name']);
                            if (self::is_singular_role($base)) {
                                $singular_role_map[$base] = $m_id;
                            }
                            break;
                        }
                    }
                }
            }
        } else {
            $trace[] = "Phase 1 skipped — deadline not passed.";
        }

        // ── Phase 2: Score-based intelligent assignment ────────────────────────
        if ($requests_deadline_passed) {
            foreach ($slots as $slot) {
                if (isset($suggestions[$slot['id']])) {
                    continue;
                }

                $role_label = $slot['role_name'];
                $base_role  = self::get_base_role_name($role_label);

                // Singular role: reuse same member already assigned in another segment
                if (self::is_singular_role($base_role) && isset($singular_role_map[$base_role])) {
                    $m_id   = $singular_role_map[$base_role];
                    $m_data = array_values(array_filter($members, fn($m) => (int) $m['id'] === $m_id))[0] ?? null;
                    if ($m_data) {
                        $suggestions[$slot['id']] = array_merge($slot, [
                            'suggested_member_id'  => $m_id,
                            'suggested_member_name'=> $m_data['full_name'],
                        ]);
                        $trace[] = "Re-using {$m_data['full_name']} for singular segment: $role_label";
                        continue;
                    }
                }

                // Hard level gate (skip unsuitable entirely) — dynamic, from WP option
                $role_lower  = strtolower($base_role);
                $gate_level  = 0;
                $gate_levels = self::get_current_gate_levels();
                foreach ($gate_levels as $pattern => $min_level) {
                    if (strpos($role_lower, $pattern) !== false) {
                        $gate_level = (int) $min_level;
                        break;
                    }
                }

                $best_score  = -1;
                $best_member = null;
                $best_reason = '';

                foreach ($members as $member) {
                    $m_id  = (int) $member['id'];
                    $level = (int) $member['level'];

                    if (in_array($m_id, $assigned_ids)) {
                        continue;
                    }
                    if ($level < $gate_level) {
                        continue;
                    }

                    // Cooloff check — only for Speaker / TMOD / GE
                    if (self::is_cooloff_role($base_role) && isset($cooloff_map[$m_id][$base_role])) {
                        $trace[] = "Cooloff skip: {$member['full_name']} for $base_role (last: {$cooloff_map[$m_id][$base_role]})";
                        continue;
                    }

                    // L1 ordering: Ice Breaker requires prior Table Topics Speaker
                    if ($level <= 1 && preg_match('/^speaker(\s+\d+)?$/i', $base_role)) {
                        $l1_counts = $all_counts[$m_id][1] ?? [];
                        if (($l1_counts['Table Topics Speaker'] ?? 0) === 0) {
                            $trace[] = "L1 ordering skip: {$member['full_name']} needs TT Speaker before Ice Breaker";
                            continue;
                        }
                    }

                    $score  = 0;
                    $reason = '';

                    // +40 if this role fills an unmet TI requirement for their level
                    $level_counts = $all_counts[$m_id][$level] ?? [];
                    $pres_counts  = $pres_map[$m_id][$level] ?? [];
                    $gaps = self::get_member_level_gaps($m_id, $level, $level_counts, $pres_counts);
                    foreach ($gaps as $gap) {
                        if ($gap['met']) {
                            continue;
                        }
                        foreach ($gap['roles'] ?? [] as $needed) {
                            if (strtolower($needed) === strtolower($base_role)) {
                                $score  += 40;
                                $reason  = "Needs {$gap['label']} (L{$level})";
                                break 2;
                            }
                        }
                        if ($gap['type'] === 'presentation' && strtolower($base_role) === 'educational presentation') {
                            $score  += 40;
                            $reason  = "Needs {$gap['label']} (L{$level})";
                            break;
                        }
                    }

                    // −20 penalty: Ice Breaker slot but member has unmet non-speech role requirements
                    if (preg_match('/^speaker(\s+\d+)?$/i', $base_role)) {
                        $has_unmet_non_speaker = false;
                        foreach ($gaps as $gap) {
                            if ($gap['met']) {
                                continue;
                            }
                            if ($gap['type'] === 'presentation') {
                                $has_unmet_non_speaker = true;
                                break;
                            }
                            foreach ($gap['roles'] ?? [] as $req_role) {
                                if (!preg_match('/^(speaker|table topics speaker)$/i', trim($req_role))) {
                                    $has_unmet_non_speaker = true;
                                    break 2;
                                }
                            }
                        }
                        if ($has_unmet_non_speaker) {
                            $score -= 20;
                        }
                    }

                    // +0–20 based on weeks since their last role (rewards members who've been inactive)
                    $last_date = $last_role_map[$m_id] ?? null;
                    if ($last_date) {
                        $weeks_since = (current_time('timestamp') - strtotime($last_date)) / (7 * 86400);
                        $score += min(20, (int) ($weeks_since * 5));
                    } else {
                        $score += 20; // never had a role
                    }

                    // +0–10 based on recent meeting absence (fairness)
                    $recent_count = $member['recent_participation_count'] ?? 0;
                    $total_recent = max(1, $member['total_recent_meetings_checked'] ?? 3);
                    $score += (int) (10 * (1 - ($recent_count / $total_recent)));

                    if ($score > $best_score) {
                        $best_score  = $score;
                        $best_member = $member;
                        $best_reason = $reason ?: "Score {$score} (L{$level})";
                    }
                }

                if ($best_member) {
                    $suggestions[$slot['id']] = array_merge($slot, [
                        'suggested_member_id'  => (int) $best_member['id'],
                        'suggested_member_name'=> $best_member['full_name'],
                        'progression_note'     => $best_reason,
                    ]);
                    $assigned_ids[] = (int) $best_member['id'];
                    if (self::is_singular_role($base_role)) {
                        $singular_role_map[$base_role] = (int) $best_member['id'];
                    }
                    $trace[] = "Score match: {$best_member['full_name']} ({$best_score}pts) → $role_label ({$best_reason})";
                } else {
                    $trace[] = "No candidate for: $role_label";
                }
            }
        }

        return ['suggestions' => array_values($suggestions), 'trace' => $trace];
    }

    // -------------------------------------------------------------------------
    // Save meeting
    // -------------------------------------------------------------------------

    public static function save_meeting($data) {
        global $wpdb;
        $table = self::meeting_table();
        $now   = current_time('mysql');

        $record = array(
            'meeting_date'     => sanitize_text_field($data['meeting_date'] ?? ''),
            'start_time'       => sanitize_text_field($data['start_time'] ?? '18:30'),
            'total_duration'   => absint($data['total_duration'] ?? 120),
            'requests_close_at'=> !empty($data['requests_close_at']) ? str_replace('T', ' ', sanitize_text_field($data['requests_close_at'])) : null,
            'theme'            => sanitize_text_field($data['theme'] ?? ''),
            'venue'            => sanitize_text_field($data['venue'] ?? ''),
            'agenda_notes'     => sanitize_textarea_field($data['agenda_notes'] ?? ''),
            'updated_at'       => $now,
        );

        if (empty($record['meeting_date']) || empty($record['theme'])) {
            return new WP_Error('tmp_invalid_meeting', 'Meeting date and theme are required.', array('status' => 400));
        }

        if (!empty($data['id'])) {
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            $id = absint($data['id']);

            // Add any missing role slots the user checked in edit mode
            $selected_roles = is_array($data['roles'] ?? null) ? array_map('sanitize_text_field', $data['roles']) : [];
            $new_slot_count = isset($data['speech_slots']) ? absint($data['speech_slots']) : 0;

            if (!empty($selected_roles) || $new_slot_count > 0) {
                $atbl = self::assignment_table();
                $existing_names = $wpdb->get_col($wpdb->prepare(
                    "SELECT role_name FROM {$atbl} WHERE meeting_id = %d ORDER BY sort_order", $id
                ));
                $existing_bases = array_map([self::class, 'get_base_role_name'], $existing_names);

                $max_order = (int) ($wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(sort_order) FROM {$atbl} WHERE meeting_id = %d", $id
                )) ?? 0);
                $order = $max_order + 10;

                foreach ($selected_roles as $role) {
                    if (!in_array($role, $existing_bases, true)) {
                        [$tg, $ty, $tr] = self::get_timing_for_role($role, 5);
                        self::save_assignment([
                            'meeting_id' => $id, 'role_name' => $role,
                            'duration'   => 5,   'status'    => 'Planned',
                            'sort_order' => $order,
                            'time_green' => $tg, 'time_yellow' => $ty, 'time_red' => $tr,
                        ]);
                        $order += 10;
                    }
                }

                if ($new_slot_count > 0) {
                    $current_speakers = count(array_filter($existing_bases, fn($b) => (bool) preg_match('/^Speaker\s+\d+$/i', $b)));
                    for ($i = $current_speakers + 1; $i <= $new_slot_count; $i++) {
                        [$tg, $ty, $tr] = self::get_timing_for_role("Evaluator $i (Introduces speaker)", 2);
                        self::save_assignment(['meeting_id' => $id, 'role_name' => "Evaluator $i (Introduces speaker)", 'duration' => 2, 'status' => 'Planned', 'sort_order' => $order, 'time_green' => $tg, 'time_yellow' => $ty, 'time_red' => $tr]);
                        $order += 10;
                        [$tg, $ty, $tr] = self::get_timing_for_role("Speaker $i (Speech)", 8, 7);
                        self::save_assignment(['meeting_id' => $id, 'role_name' => "Speaker $i (Speech)", 'duration' => 8, 'timer_duration' => 7, 'status' => 'Planned', 'sort_order' => $order, 'time_green' => $tg, 'time_yellow' => $ty, 'time_red' => $tr]);
                        $order += 10;
                        [$tg, $ty, $tr] = self::get_timing_for_role("Evaluator $i (Evaluation)", 3);
                        self::save_assignment(['meeting_id' => $id, 'role_name' => "Evaluator $i (Evaluation)", 'duration' => 3, 'status' => 'Planned', 'sort_order' => $order, 'time_green' => $tg, 'time_yellow' => $ty, 'time_red' => $tr]);
                        $order += 10;
                    }
                }
            }
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($table, $record);
            $id = (int) $wpdb->insert_id;

            $selected_roles = $data['roles'] ?? [];
            $agenda = [];

            // ── Opening ──────────────────────────────────────────────────────────
            if (in_array('Sergeant at Arms', $selected_roles))
                $agenda[] = ['role' => 'Sergeant at Arms',       'note' => 'Starts meeting',               'dur' => 2];
            if (in_array('Presiding Officer', $selected_roles))
                $agenda[] = ['role' => 'Presiding Officer',      'note' => 'Address and Guest Introduction', 'dur' => 5];
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces the theme',         'dur' => 2];
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces the segments',      'dur' => 2];
            }

            // TMOD introduces each functional role, then role player explains
            $role_intro_map = [
                'Timer'             => ['explain' => 'Explains role',             'dur' => 2],
                'Ah-Counter'        => ['explain' => 'Explains role',             'dur' => 2],
                'Grammarian'        => ['explain' => 'Explains role and WOD/POD', 'dur' => 3],
                'General Evaluator' => ['explain' => 'Explains role',             'dur' => 2],
            ];
            foreach ($role_intro_map as $r => $meta) {
                if (!in_array($r, $selected_roles)) continue;
                if (in_array('Toastmaster of the Day', $selected_roles))
                    $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => "Introduces {$r}", 'dur' => 1];
                $agenda[]     = ['role' => $r, 'note' => $meta['explain'], 'dur' => $meta['dur']];
            }
            if (in_array('Table Topics Evaluator', $selected_roles))
                $agenda[] = ['role' => 'Table Topics Evaluator', 'note' => 'Introduction of role', 'dur' => 2];

            // ── Break ─────────────────────────────────────────────────────────
            $agenda[] = ['role' => 'Break', 'note' => 'Networking', 'dur' => 5];

            // ── Prepared Speeches ─────────────────────────────────────────────
            $speech_slots = absint($data['speech_slots'] ?? 0);
            for ($i = 1; $i <= $speech_slots; $i++) {
                if (in_array('Toastmaster of the Day', $selected_roles))
                    $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => "Introduces Evaluator {$i} and Speaker {$i}", 'dur' => 1];
                $agenda[]     = ['role' => "Speaker $i",             'note' => 'Speech',   'dur' => 7];
                $agenda[]     = ['role' => 'Toastmaster of the Day', 'note' => 'Feedback', 'dur' => 1];
            }
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme interlude', 'dur' => 2];
            if (in_array('Timer', $selected_roles))
                $agenda[] = ['role' => 'Timer', 'note' => 'Report', 'dur' => 1];

            // ── Table Topics ──────────────────────────────────────────────────
            if (in_array('Table Topics Master', $selected_roles)) {
                if (in_array('Toastmaster of the Day', $selected_roles))
                    $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces Table Topics Master', 'dur' => 1];
                $agenda[]     = ['role' => 'Table Topics Master',    'note' => 'Table Topics Session',           'dur' => 20];
                if (in_array('Toastmaster of the Day', $selected_roles))
                    $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme interlude', 'dur' => 2];
            }
            if (in_array('Table Topics Evaluator', $selected_roles))
                $agenda[] = ['role' => 'Table Topics Evaluator', 'note' => 'TT Session Evaluation', 'dur' => 3];

            // ── Evaluation Session ────────────────────────────────────────────
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Introduces Evaluation Session', 'dur' => 1];
            for ($i = 1; $i <= $speech_slots; $i++) {
                $agenda[]     = ['role' => "Evaluator $i", 'note' => 'Evaluation', 'dur' => 3];
            }
            if (in_array('Timer', $selected_roles))
                $agenda[] = ['role' => 'Timer', 'note' => 'Report', 'dur' => 1];

            // ── Role-player Reports ───────────────────────────────────────────
            if (in_array('Grammarian', $selected_roles))
                $agenda[] = ['role' => 'Grammarian',       'note' => 'Report', 'dur' => 4];
            if (in_array('Ah-Counter', $selected_roles))
                $agenda[] = ['role' => 'Ah-Counter',       'note' => 'Report', 'dur' => 3];
            if (in_array('Active Listener', $selected_roles))
                $agenda[] = ['role' => 'Active Listener',  'note' => 'Report', 'dur' => 3];
            if (in_array('General Evaluator', $selected_roles))
                $agenda[] = ['role' => 'General Evaluator', 'note' => 'Final Report', 'dur' => 9];

            // ── Theme Closure + Conclusion ────────────────────────────────────
            if (in_array('Toastmaster of the Day', $selected_roles))
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Theme Closure', 'dur' => 2];
            if (in_array('Presiding Officer', $selected_roles)) {
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Address and Guest Feedback',       'dur' => 5];
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Guest Feedback and Announcements', 'dur' => 5];
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Concludes the meeting',            'dur' => 1];
            }

            $order = 10;
            foreach ($agenda as $item) {
                $full_role_name = sanitize_text_field($item['role'] . ($item['note'] ? " ({$item['note']})" : ""));
                $timer_dur      = $item['timer_dur'] ?? null;
                [$tg, $ty, $tr] = self::get_timing_for_role($full_role_name, $item['dur'], $timer_dur);
                self::save_assignment([
                    'meeting_id'     => $id,
                    'role_name'      => $full_role_name,
                    'duration'       => $item['dur'],
                    'timer_duration' => $timer_dur,
                    'status'         => 'Planned',
                    'sort_order'     => $order,
                    'time_green'     => $tg,
                    'time_yellow'    => $ty,
                    'time_red'       => $tr,
                ]);
                $order += 10;
            }
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    // -------------------------------------------------------------------------
    // Timer guidance defaults
    // -------------------------------------------------------------------------

    public static function get_timing_defaults() {
        $saved = get_option('tmp_timing_rules', null);
        if ($saved) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded) && !empty($decoded)) return $decoded;
        }
        return [
            ['key' => 'saa',            'label' => 'SAA / Sergeant at Arms',                   'green' => 60,  'yellow' => 90,  'red' => 120],
            ['key' => 'intro',          'label' => 'Role Introductions (short intro slots)',    'green' => 60,  'yellow' => 90,  'red' => 120],
            ['key' => 'tmod_intro',     'label' => 'TMOD – Intro of Theme',                    'green' => 120, 'yellow' => 180, 'red' => 240],
            ['key' => 'ttm',            'label' => 'Table Topics Master',                       'green' => 600, 'yellow' => 720, 'red' => 840],
            ['key' => 'tt_evaluator',   'label' => 'Table Topics Evaluator',                    'green' => 120, 'yellow' => 180, 'red' => 240],
            ['key' => 'presiding',      'label' => 'Presiding Officer',                         'green' => 180, 'yellow' => 240, 'red' => 300],
            ['key' => 'speaker',        'label' => 'Prepared Speech',                           'green' => 300, 'yellow' => 360, 'red' => 420],
            ['key' => 'evaluator_eval', 'label' => 'Evaluator (Evaluation speech)',             'green' => 180, 'yellow' => 240, 'red' => 300],
            ['key' => 'timer_report',   'label' => 'Timer Report',                              'green' => 60,  'yellow' => 90,  'red' => 120],
            ['key' => 'ah_counter',     'label' => 'Ah-Counter Report',                         'green' => 60,  'yellow' => 120, 'red' => 180],
            ['key' => 'grammarian',     'label' => 'Grammarian Report',                         'green' => 60,  'yellow' => 120, 'red' => 180],
            ['key' => 'gen_evaluator',  'label' => 'General Evaluator',                         'green' => 180, 'yellow' => 240, 'red' => 300],
        ];
    }

    /**
     * Formula-based timer defaults.
     * $timer_duration overrides $duration as the effective timed duration (e.g. 7-min speech in an 8-min agenda slot).
     *
     * Special cases (role-name driven, not formula):
     *   - Timer (Report) → 0:30 / 0:45 / 1:00  (TI sub-minute reporting standard)
     *   - Table Topics Master → 10:00 / 15:00 / 20:00  (TI-mandated session limits)
     *
     * Formula for everything else:
     *   X ≤ 3 min  → green = (X−1) min, so 3-min slots give 2:00 / 2:30 / 3:00
     *   X ≥ 4 min  → green = (X−2) min, so 5-min slots give 3:00 / 4:00 / 5:00
     *   yellow = midpoint of green and red in both cases
     *   X ≤ 1 min or Break → null (no timer)
     */
    public static function get_timing_for_role($role_name, $duration = 0, $timer_duration = null) {
        $lower     = strtolower($role_name);
        $effective = (int) ($timer_duration ?? $duration);

        if (str_contains($lower, 'break')) return [null, null, null];

        // Timer Report: sub-minute TI standard regardless of slot duration
        if (preg_match('/\btimer\b/i', $role_name) && str_contains($lower, 'report')) return [30, 45, 60];

        // Table Topics Master session: TI-mandated 10/15/20
        if (str_contains($lower, 'table topics master')) return [600, 900, 1200];

        if ($effective <= 1) return [null, null, null];

        $green  = ($effective <= 3) ? ($effective - 1) * 60 : max(($effective - 2) * 60, 60);
        $red    = $effective * 60;
        $yellow = intdiv($green + $red, 2);
        return [$green, $yellow, $red];
    }

    // -------------------------------------------------------------------------
    // Reorder agenda items
    // -------------------------------------------------------------------------

    public static function reorder_agenda($meeting_id, $ordered_ids) {
        global $wpdb;
        $table = self::assignment_table();
        $now   = current_time('mysql');
        $mid   = absint($meeting_id);
        foreach (array_values($ordered_ids) as $pos => $raw_id) {
            $id = absint($raw_id);
            if (!$id) continue;
            $wpdb->update($table, ['sort_order' => $pos * 10, 'updated_at' => $now], ['id' => $id, 'meeting_id' => $mid]);
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Save assignment
    // -------------------------------------------------------------------------

    public static function save_assignment($data) {
        global $wpdb;
        $table = self::assignment_table();
        $now   = current_time('mysql');

        $record = [];
        if (isset($data['meeting_id']))         $record['meeting_id']         = absint($data['meeting_id']);
        if (isset($data['member_id']))           $record['member_id']          = !empty($data['member_id']) ? absint($data['member_id']) : null;
        if (isset($data['role_name']))           $record['role_name']          = sanitize_text_field($data['role_name']);
        if (isset($data['speech_title']))        $record['speech_title']       = sanitize_text_field($data['speech_title']);
        if (isset($data['duration']))            $record['duration']           = absint($data['duration']);
        if (isset($data['status']))              $record['status']             = sanitize_text_field($data['status']);
        if (isset($data['sort_order']))          $record['sort_order']         = absint($data['sort_order']);
        if (isset($data['presentation_series'])) $record['presentation_series']= sanitize_text_field($data['presentation_series']);
        if (isset($data['cooloff_override']))    $record['cooloff_override']   = (int) (bool) $data['cooloff_override'];
        if (isset($data['override_reason']))     $record['override_reason']    = sanitize_text_field($data['override_reason']);

        // Timer guidance — accept seconds (int) or "M:SS" string
        $parse_time = function($v) {
            if ($v === null || $v === '') return null;
            if (is_numeric($v) && (int) $v >= 0) return (int) $v;
            if (is_string($v) && str_contains($v, ':')) {
                [$m, $s] = array_map('intval', explode(':', $v, 2));
                return $m * 60 + $s;
            }
            return null;
        };
        if (array_key_exists('time_green',  $data)) $record['time_green']  = $parse_time($data['time_green']);
        if (array_key_exists('time_yellow', $data)) $record['time_yellow'] = $parse_time($data['time_yellow']);
        if (array_key_exists('time_red',    $data)) $record['time_red']    = $parse_time($data['time_red']);
        if (array_key_exists('timer_duration', $data)) {
            $record['timer_duration'] = ($data['timer_duration'] !== null && $data['timer_duration'] !== '') ? absint($data['timer_duration']) : null;
        }

        $record['updated_at'] = $now;

        if (!empty($data['id'])) {
            $old = $wpdb->get_row($wpdb->prepare(
                "SELECT status, member_id, role_name, meeting_id, presentation_series, duration, timer_duration FROM {$table} WHERE id = %d",
                $data['id']
            ), ARRAY_A);

            $new_status    = $data['status'] ?? ($old['status'] ?? '');
            $is_final      = $new_status === 'Completed'; // Only record when meeting is truly completed
            $was_not_final = $old && $old['status'] !== 'Completed';

            if ($was_not_final && $is_final && !empty($data['member_id'])) {
                $role_name   = $old['role_name'] ?? ($record['role_name'] ?? '');
                $base_for_history = self::get_base_role_name($role_name);

                // Break is a timeline placeholder, not a real role — never record in history
                if (strtolower($base_for_history) !== 'break') {
                    self::notify_assignment_status(absint($data['id']), absint($data['member_id']));
                }

                $meeting_id  = $old['meeting_id'] ?? ($record['meeting_id'] ?? 0);
                $meeting_date = $wpdb->get_var($wpdb->prepare(
                    "SELECT meeting_date FROM " . self::meeting_table() . " WHERE id = %d",
                    $meeting_id
                ));
                $series = $data['presentation_series'] ?? $old['presentation_series'] ?? null;

                if (strtolower($base_for_history) === 'break') {
                    // Skip history recording for Break rows
                } else {
                $wpdb->insert(self::participation_history_table(), [
                    'member_id'           => absint($data['member_id']),
                    'meeting_id'          => absint($meeting_id),
                    'assignment_id'       => absint($data['id']),
                    'role_name'           => $base_for_history,
                    'meeting_date'        => $meeting_date,
                    'level_at_completion' => max(1, (int) self::get_member(absint($data['member_id']))['level']),
                    'presentation_series' => $series ? sanitize_text_field($series) : null,
                    'created_at'          => $now,
                ]);
                } // end else (not break)
            }

            // Auto-recompute timing when duration or timer_duration changes but no explicit timer values given
            $timing_explicit = array_key_exists('time_green', $data) || array_key_exists('time_yellow', $data) || array_key_exists('time_red', $data);
            $timing_inputs_changed = isset($data['duration']) || array_key_exists('timer_duration', $data);
            if ($timing_inputs_changed && !$timing_explicit && $old) {
                $eff_dur       = isset($record['duration'])                          ? (int) $record['duration']    : (int) ($old['duration'] ?? 0);
                $eff_timer_dur = array_key_exists('timer_duration', $record)         ? $record['timer_duration']    : ($old['timer_duration'] ?? null);
                $eff_role      = $record['role_name']                                ?? ($old['role_name'] ?? '');
                [$tg, $ty, $tr]         = self::get_timing_for_role($eff_role, $eff_dur, $eff_timer_dur);
                $record['time_green']   = $tg;
                $record['time_yellow']  = $ty;
                $record['time_red']     = $tr;
            }

            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            $saved = array('id' => absint($data['id'])) + $record;

            $meeting_id_for_singular = $record['meeting_id'] ?? ($old['meeting_id'] ?? 0);
            if ($meeting_id_for_singular) {
                $full_role = $wpdb->get_var($wpdb->prepare("SELECT role_name FROM {$table} WHERE id = %d", $data['id']));
                $base      = self::get_base_role_name($full_role);

                // Only sync member_id/status across sub-slots when a member assignment
                // was explicitly included in the payload — not for duration-only updates.
                if (self::is_singular_role($base) && isset($data['member_id'])) {
                    $new_member_id = !empty($record['member_id']) ? absint($record['member_id']) : 0;
                    $new_status_v  = sanitize_text_field($record['status'] ?? 'Confirmed');

                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET member_id = IF(%d = 0, NULL, %d), status = %s
                         WHERE meeting_id = %d AND (role_name = %s OR role_name LIKE %s)",
                        $new_member_id,
                        $new_member_id,
                        $new_status_v,
                        $meeting_id_for_singular,
                        $base,
                        $wpdb->esc_like($base) . ' (%'
                    ));
                }
            }

            return $saved;
        }

        if (empty($record['meeting_id']) || empty($record['role_name'])) {
            return new WP_Error('tmp_invalid_assignment', 'Meeting and role are required.', array('status' => 400));
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return array('id' => (int) $wpdb->insert_id) + $record;
    }

    public static function delete_assignment($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::assignment_table(), array('id' => absint($id)));
    }

    // -------------------------------------------------------------------------
    // Participation history
    // -------------------------------------------------------------------------

    /**
     * Returns integer completion counts per member, per level, per role.
     * [member_id => [level => [role_name => count]]]
     */
    public static function get_member_participation_counts() {
        global $wpdb;
        $history_table = self::participation_history_table();

        $results = $wpdb->get_results(
            "SELECT member_id, role_name, level_at_completion, COUNT(*) as cnt
             FROM {$history_table}
             GROUP BY member_id, level_at_completion, role_name",
            ARRAY_A
        );

        $counts = [];
        foreach ($results as $row) {
            $m_id  = (int) $row['member_id'];
            $level = (int) $row['level_at_completion'];
            if (!isset($counts[$m_id])) $counts[$m_id] = [];
            if (!isset($counts[$m_id][$level])) $counts[$m_id][$level] = [];
            $counts[$m_id][$level][$row['role_name']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Detailed history for a single member grouped by level.
     */
    public static function get_member_participation_history($member_id) {
        global $wpdb;
        $history_table = self::participation_history_table();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT role_name, COUNT(*) as count, MAX(meeting_date) as last_completed_date, level_at_completion, presentation_series
             FROM {$history_table}
             WHERE member_id = %d
             GROUP BY level_at_completion, role_name
             ORDER BY level_at_completion DESC, last_completed_date DESC",
            $member_id
        ), ARRAY_A);

        $history = [];
        foreach ($results as $row) {
            $level = $row['level_at_completion'];
            if (!isset($history[$level])) $history[$level] = [];
            $history[$level][] = [
                'role_name'           => $row['role_name'],
                'count'               => (int) $row['count'],
                'last_completed_date' => $row['last_completed_date'],
                'presentation_series' => $row['presentation_series'],
            ];
        }

        return $history;
    }

    /**
     * Integer role counts for a single member grouped by level.
     * [level => [role_name => count]]
     */
    public static function get_member_participation_counts_for_member($member_id) {
        global $wpdb;
        $history_table = self::participation_history_table();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT role_name, level_at_completion, COUNT(*) as cnt
             FROM {$history_table}
             WHERE member_id = %d
             GROUP BY level_at_completion, role_name",
            $member_id
        ), ARRAY_A);

        $counts = [];
        foreach ($results as $row) {
            $level = (int) $row['level_at_completion'];
            if (!isset($counts[$level])) $counts[$level] = [];
            $counts[$level][$row['role_name']] = (int) $row['cnt'];
        }

        return $counts;
    }

    // -------------------------------------------------------------------------
    // Role requests
    // -------------------------------------------------------------------------

    public static function save_requests($data) {
        global $wpdb;
        $table      = self::request_table();
        $meeting_id = absint($data['meeting_id'] ?? 0);
        $member_id  = absint($data['member_id']  ?? 0);
        $priorities = $data['priorities'] ?? [];

        if (!$meeting_id || !$member_id) {
            return new WP_Error('tmp_missing_data', 'Missing Meeting or Member ID.', ['status' => 400]);
        }

        // Get member level
        $member = self::get_member($member_id);
        if (!$member) {
            return new WP_Error('tmp_member_not_found', 'Member not found.', ['status' => 404]);
        }
        $member_level = (int) $member['level'];

        // Get gate levels
        $gate_levels = self::get_current_gate_levels();

        // Validate all requested roles
        $assignments = self::assignment_table();
        foreach ($priorities as $index => $assignment_id) {
            if (empty($assignment_id)) continue;

            $role = $wpdb->get_row($wpdb->prepare(
                "SELECT role_name FROM {$assignments} WHERE id = %d AND meeting_id = %d",
                $assignment_id, $meeting_id
            ), ARRAY_A);

            if (!$role) continue;

            // Check level requirement
            $base_role = self::get_base_role_name($role['role_name']);
            $min_level = 0;
            foreach ($gate_levels as $pattern => $level) {
                if (stripos($base_role, $pattern) !== false) {
                    $min_level = (int) $level;
                    break;
                }
            }

            if ($member_level < $min_level) {
                return new WP_Error(
                    'tmp_level_requirement',
                    "You are Level {$member_level}, but {$base_role} requires Level {$min_level}+.",
                    ['status' => 403]
                );
            }
        }

        $old_asgn_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT assignment_id FROM $table WHERE meeting_id = %d AND member_id = %d",
            $meeting_id, $member_id
        ));

        $wpdb->delete($table, ['meeting_id' => $meeting_id, 'member_id' => $member_id]);

        foreach ($priorities as $index => $assignment_id) {
            if (empty($assignment_id)) continue;
            $wpdb->insert($table, [
                'meeting_id'   => $meeting_id,
                'member_id'    => $member_id,
                'assignment_id'=> absint($assignment_id),
                'priority'     => $index + 1,
                'created_at'   => current_time('mysql'),
            ]);
            $wpdb->update(self::assignment_table(), ['status' => 'Requested'], ['id' => absint($assignment_id), 'member_id' => null]);
        }

        $check_ids = array_unique(array_merge($old_asgn_ids, $priorities));
        foreach ($check_ids as $asgn_id) {
            if (!$asgn_id) continue;
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE assignment_id = %d", $asgn_id));
            if ($count == 0) {
                $wpdb->update(self::assignment_table(), ['status' => 'Planned'], ['id' => absint($asgn_id), 'member_id' => null]);
            }
        }

        self::notify_vpe_of_request($meeting_id, $member_id, $priorities);
        return true;
    }

    private static function notify_vpe_of_request($meeting_id, $member_id, $priorities) {
        $vpes   = get_users(['role' => 'tm_vp_education']);
        $emails = !empty($vpes) ? wp_list_pluck($vpes, 'user_email') : [get_option('admin_email')];

        $member  = self::get_member($member_id);
        global $wpdb;
        $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::meeting_table() . " WHERE id = %d", $meeting_id), ARRAY_A);

        if (!$member || !$meeting) return;

        $role_details     = [];
        $assignments_table = self::assignment_table();

        foreach ($priorities as $index => $asgn_id) {
            if (empty($asgn_id)) continue;
            $role_name = $wpdb->get_var($wpdb->prepare("SELECT role_name FROM {$assignments_table} WHERE id = %d", $asgn_id));
            if ($role_name) {
                $role_details[] = sprintf("Priority %d: %s", $index + 1, self::get_base_role_name($role_name));
            }
        }

        if (empty($role_details)) return;

        $subject = "[New Request] Role Request from " . $member['full_name'];
        $message = sprintf(
            "Hi VP Education,\n\n%s has submitted new prioritized role requests for the meeting on %s (%s):\n\n%s\n\nPlease log in to the dashboard to review and approve these requests.\n\nRegards,\nClub Portal",
            $member['full_name'],
            $meeting['meeting_date'],
            $meeting['theme'],
            implode("\n", $role_details)
        );

        wp_mail($emails, $subject, $message);
    }

    public static function delete_request($id, $member_id) {
        global $wpdb;
        $table            = self::request_table();
        $assignments_table = self::assignment_table();

        $asgn_id = $wpdb->get_var($wpdb->prepare("SELECT assignment_id FROM $table WHERE id = %d", $id));

        $deleted = (bool) $wpdb->delete($table, ['id' => absint($id), 'member_id' => absint($member_id)]);

        if ($deleted && $asgn_id) {
            $asgn_id     = absint($asgn_id);
            $current_asgn = $wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$assignments_table} WHERE id = %d", $asgn_id), ARRAY_A);
            $is_assigned  = $current_asgn && (int) $current_asgn['member_id'] === (int) $member_id;
            $count        = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE assignment_id = %d", $asgn_id));

            if ($is_assigned) {
                $status = ($count > 0) ? 'Requested' : 'Planned';
                $wpdb->update($assignments_table, ['status' => $status, 'member_id' => null], ['id' => $asgn_id]);
            } elseif ($count === 0 && empty($current_asgn['member_id'])) {
                $wpdb->update($assignments_table, ['status' => 'Planned'], ['id' => $asgn_id]);
            }
        }

        return $deleted;
    }

    public static function get_member_requests($member_id) {
        global $wpdb;
        $requests   = self::request_table();
        $meetings   = self::meeting_table();
        $assignments = self::assignment_table();
        $now = current_time('Y-m-d H:i:s');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, m.meeting_date, m.theme, m.requests_close_at, a.role_name, a.member_id as assigned_id, a.status as assignment_status
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND (m.requests_close_at IS NULL OR m.requests_close_at > %s)
             ORDER BY m.meeting_date ASC, r.priority ASC",
            $member_id, $now
        ), ARRAY_A);
    }

    public static function get_all_pending_requests() {
        global $wpdb;
        $requests   = self::request_table();
        $meetings   = self::meeting_table();
        $assignments = self::assignment_table();
        $members    = self::member_table();
        $today = current_time('Y-m-d');

        $raw_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.meeting_date, m.theme, m.id as meeting_id,
                    COALESCE(a.role_name, 'Unknown Role') as role_name,
                    mem.full_name as member_name, mem.level as member_level, mem.pathway
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             LEFT JOIN {$assignments} a ON r.assignment_id = a.id
             JOIN {$members} mem ON r.member_id = mem.id
             WHERE m.meeting_date >= %s
               AND r.status = 'Pending'
             ORDER BY m.meeting_date ASC, role_name ASC, r.priority ASC",
            $today
        ), ARRAY_A);

        // Organize into tree structure: meeting → role (base name) → requests with scoring
        $meetings_map = [];

        foreach ($raw_requests as $req) {
            $meeting_key = $req['meeting_id'];
            $role_base = self::get_base_role_name($req['role_name']); // Normalize role name
            $slot_key = $role_base;

            if (!isset($meetings_map[$meeting_key])) {
                $meetings_map[$meeting_key] = [
                    'meetingId' => (int) $meeting_key,
                    'meetingDate' => $req['meeting_date'],
                    'theme' => $req['theme'],
                    'roles' => []
                ];
            }

            if (!isset($meetings_map[$meeting_key]['roles'][$slot_key])) {
                $meetings_map[$meeting_key]['roles'][$slot_key] = [
                    'roleName' => $role_base,
                    'requests' => []
                ];
            }

            // Score this request
            $member_data = [
                'id' => $req['member_id'],
                'level' => $req['member_level']
            ];
            $score = self::score_request($req, $member_data);

            $req['score'] = $score;
            $req['memberLevel'] = (int) $req['member_level'];
            $req['isRecommended'] = false; // Will set to true for highest scorer

            $meetings_map[$meeting_key]['roles'][$slot_key]['requests'][] = $req;
        }

        // GREEDY ALLOCATION: per-meeting, recommend each member for at most one role.
        // Prevents same member showing as "Recommended" across multiple roles simultaneously.
        foreach ($meetings_map as &$meeting) {
            // Flatten all requests for this meeting into one list for sorting
            $candidates = [];
            foreach ($meeting['roles'] as $role_key => $role) {
                foreach ($role['requests'] as $req) {
                    $candidates[] = [
                        'role_key'  => $role_key,
                        'member_id' => (int) $req['member_id'],
                        'score'     => (int) $req['score'],
                        'priority'  => (int) $req['priority'],
                    ];
                }
            }

            // Sort: highest score first, then lowest priority number (P1 best)
            usort($candidates, function ($a, $b) {
                if ($b['score'] !== $a['score']) return $b['score'] - $a['score'];
                return $a['priority'] - $b['priority'];
            });

            // Greedy assign: each member gets at most one recommended role
            $role_winner   = []; // role_key  → member_id
            $member_placed = []; // member_id → true
            foreach ($candidates as $c) {
                if (isset($member_placed[$c['member_id']]) || isset($role_winner[$c['role_key']])) {
                    continue;
                }
                $role_winner[$c['role_key']]       = $c['member_id'];
                $member_placed[$c['member_id']]    = true;
            }

            // Apply recommendations and generate reasons
            foreach ($meeting['roles'] as &$role) {
                $role_key       = $role['roleName'];
                $winner_id      = $role_winner[$role_key] ?? null;

                // Find winner's request for reason generation
                $winner_req = null;
                if ($winner_id !== null) {
                    foreach ($role['requests'] as $req) {
                        if ((int) $req['member_id'] === $winner_id) {
                            $winner_req = $req;
                            break;
                        }
                    }
                }

                foreach ($role['requests'] as &$req) {
                    if ($winner_id !== null && (int) $req['member_id'] === $winner_id) {
                        $req['isRecommended'] = true;
                        $req['reasons']       = self::get_reason_tags_for_request($req);
                    } else {
                        $req['isRecommended'] = false;
                        $req['reason']        = $winner_req
                            ? self::get_reason_for_not_selected($req, $winner_req, $role['requests'])
                            : null;
                    }
                }
                unset($req);
            }
            unset($role);
        }
        unset($meeting);

        // Convert to final format: Role → Requested by Members
        // Use explicit deduplication by role name to prevent duplicate roles
        $result = [];
        foreach ($meetings_map as $meeting) {
            $roles_by_name = []; // Deduplicate roles by name

            foreach ($meeting['roles'] as $role) {
                $role_name = $role['roleName'];

                // If this role already exists, merge requests
                if (!isset($roles_by_name[$role_name])) {
                    $roles_by_name[$role_name] = [
                        'roleName' => $role_name,
                        'requests' => []
                    ];
                }

                // Add all requests to this role, avoiding duplicates
                foreach ($role['requests'] as $req) {
                    $req_id = (int) $req['id'];
                    // Check if request already exists in this role
                    $exists = false;
                    foreach ($roles_by_name[$role_name]['requests'] as $existing) {
                        if ((int) $existing['id'] === $req_id) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $roles_by_name[$role_name]['requests'][] = $req;
                    }
                }
            }

            // Convert deduplicated roles to final format
            $roles = [];
            foreach ($roles_by_name as $role_name => $role) {
                // Sort requests by recommendation, then by score
                $requests = $role['requests'];
                usort($requests, function ($a, $b) {
                    $rec_a = (bool) ($a['isRecommended'] ?? false);
                    $rec_b = (bool) ($b['isRecommended'] ?? false);
                    if ($rec_a !== $rec_b) {
                        return $rec_a ? -1 : 1; // Recommended first
                    }
                    return $b['score'] - $a['score']; // Then by score
                });

                $roles[] = [
                    'roleName' => $role_name,
                    'requestCount' => count($requests),
                    'requests' => array_map(function ($req) {
                        return [
                            'requestId' => (int) $req['id'],
                            'assignmentId' => (int) $req['assignment_id'],
                            'memberId' => (int) $req['member_id'],
                            'memberName' => $req['member_name'],
                            'memberLevel' => (int) $req['member_level'],
                            'pathway' => $req['pathway'],
                            'priority' => (int) $req['priority'],
                            'score' => (int) $req['score'],
                            'isRecommended' => (bool) ($req['isRecommended'] ?? false),
                            'reasons' => $req['reasons'] ?? [],
                            'reason' => $req['reason'] ?? null
                        ];
                    }, $requests)
                ];
            }

            $result[] = [
                'meetingId' => $meeting['meetingId'],
                'meetingDate' => $meeting['meetingDate'],
                'theme' => $meeting['theme'],
                'totalRequests' => array_sum(array_map(function ($r) { return $r['requestCount']; }, $roles)),
                'roles' => $roles
            ];
        }

        return $result;
    }

    /**
     * Get reason tags for why a request was recommended
     */
    private static function get_reason_tags_for_request($request) {
        $reasons = [];
        $priority = (int) $request['priority'];

        // Priority reason
        if ($priority === 1) {
            $reasons[] = 'P1 priority';
        } elseif ($priority === 2) {
            $reasons[] = 'P2 priority';
        }

        // Goal role reason
        $member_level = (int) $request['member_level'];
        $role_name = $request['role_name'];
        if (self::is_goal_role_for_level($request['member_id'], $role_name, $member_level)) {
            $reasons[] = 'Goal role';
        }

        // Fairness reason
        $days_since = self::get_days_since_last_role($request['member_id']);
        if ($days_since > 28) {
            $reasons[] = 'Fair turn';
        }

        // Level reason
        if ($member_level >= 3) {
            $reasons[] = 'Higher level';
        }

        return $reasons;
    }

    public static function get_member_request_history($member_id) {
        global $wpdb;
        $requests    = self::request_table();
        $meetings    = self::meeting_table();
        $assignments = self::assignment_table();
        $today = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, r.status as request_status, r.reason,
                    m.meeting_date, m.theme,
                    COALESCE(a.role_name, 'Unknown Role') as role_name
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             LEFT JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND m.meeting_date < %s
             ORDER BY m.meeting_date DESC, r.priority ASC",
            $member_id, $today
        ), ARRAY_A);
    }

    public static function get_member_pending_requests($member_id) {
        global $wpdb;
        $requests    = self::request_table();
        $meetings    = self::meeting_table();
        $assignments = self::assignment_table();
        $today = current_time('Y-m-d');

        // Read status directly from requests table — single source of truth.
        // LEFT JOIN assignments so orphaned requests (slot deleted) still appear.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id as request_id, r.assignment_id, r.priority,
                    r.status as request_status, r.reason,
                    r.created_at, r.updated_at,
                    m.id as meeting_id, m.meeting_date, m.theme, m.requests_close_at,
                    a.role_name
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             LEFT JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d
               AND m.meeting_date >= %s
             ORDER BY m.meeting_date ASC, r.priority ASC",
            $member_id, $today
        ), ARRAY_A);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'requestId'    => (int) $row['request_id'],
                'meetingId'    => (int) $row['meeting_id'],
                'meetingDate'  => $row['meeting_date'],
                'meetingTheme' => $row['theme'],
                'roleName'     => $row['role_name'] ?? 'Unknown Role',
                'priority'     => (int) $row['priority'],
                'status'       => $row['request_status'] ?? 'Pending',
                'reason'       => $row['reason'],
                'deadline'     => $row['requests_close_at'],
                'submittedDate' => $row['created_at'],
                'updatedAt'    => $row['updated_at'],
            ];
        }

        return ['requests' => $result];
    }

    public static function get_conflicting_requests($assignment_id) {
        global $wpdb;
        $requests_table = self::request_table();
        $members_table  = self::member_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.priority, r.member_id, m.full_name AS member_name, m.email, m.level, m.pathway
             FROM {$requests_table} r
             JOIN {$members_table} m ON r.member_id = m.id
             WHERE r.assignment_id = %d
             ORDER BY r.priority ASC, m.full_name ASC",
            $assignment_id
        ), ARRAY_A);
    }

    private static function notify_assignment_status($assignment_id, $member_id) {
        global $wpdb;
        $member     = self::get_member($member_id);
        $assignment = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::assignment_table() . " WHERE id = %d", $assignment_id), ARRAY_A);

        if (!$member || !$assignment) return;

        $meeting   = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::meeting_table() . " WHERE id = %d", $assignment['meeting_id']), ARRAY_A);
        if (!$meeting) return;

        $base_role = self::get_base_role_name($assignment['role_name']);

        $subject = "[Approved] Your Role: {$base_role} for " . $meeting['meeting_date'];
        $message = sprintf(
            "Hi %s,\n\nYour request for the role of '%s' for the meeting on %s (%s) has been approved!\n\nYou can view the updated agenda and your level progress on your dashboard.\n\nRegards,\nVP Education",
            $member['full_name'],
            $base_role,
            $meeting['meeting_date'],
            $meeting['theme']
        );
        wp_mail($member['email'], $subject, $message);

        $other_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.member_id, m.full_name, m.email
             FROM " . self::request_table() . " r
             JOIN " . self::member_table() . " m ON r.member_id = m.id
             WHERE r.assignment_id = %d AND r.member_id != %d",
            $assignment_id, $member_id
        ), ARRAY_A);

        foreach ($other_requests as $req) {
            wp_mail($req['email'],
                "[Update] Role Request for " . $meeting['meeting_date'],
                sprintf(
                    "Hi %s,\n\nThank you for requesting the '%s' role for the meeting on %s. This slot has now been filled by another member.\n\nPlease check the Available Meeting Slots on your dashboard to request a different role!\n\nRegards,\nVP Education",
                    $req['full_name'],
                    $base_role,
                    $meeting['meeting_date']
                )
            );
        }
    }

    // -------------------------------------------------------------------------
    // Notify all assigned members for a meeting
    // -------------------------------------------------------------------------

    public static function notify_assigned_members(int $meeting_id, ?string $test_email = null) {
        global $wpdb;

        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::meeting_table() . " WHERE id = %d", $meeting_id
        ), ARRAY_A);
        if (!$meeting) return new WP_Error('not_found', 'Meeting not found', ['status' => 404]);

        // Collect roles grouped by member_id, skip unassigned and Break slots
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.role_name, m.id AS member_id, m.full_name, m.email
             FROM " . self::assignment_table() . " a
             JOIN " . self::member_table() . " m ON m.id = a.member_id
             WHERE a.meeting_id = %d AND a.member_id IS NOT NULL
             ORDER BY a.sort_order ASC",
            $meeting_id
        ), ARRAY_A);

        if (empty($rows)) return ['found' => 0, 'sent' => 0];

        $by_member = [];
        foreach ($rows as $row) {
            $base = self::get_base_role_name($row['role_name']);
            if (strtolower($base) === 'break') continue;
            $mid = $row['member_id'];
            if (!isset($by_member[$mid])) {
                $by_member[$mid] = [
                    'name'  => $row['full_name'],
                    'email' => $row['email'],
                    'roles' => [],
                ];
            }
            // Avoid listing the same base role twice (e.g. TMOD appears in multiple sub-slots)
            if (!in_array($base, $by_member[$mid]['roles'], true)) {
                $by_member[$mid]['roles'][] = $base;
            }
        }

        $date_fmt   = date('l, F j, Y', strtotime($meeting['meeting_date']));
        $start_fmt  = !empty($meeting['start_time']) ? date('g:i A', strtotime($meeting['start_time'])) : '';
        $theme      = $meeting['theme'] ?? '';
        $venue      = $meeting['venue'] ?? '';
        $dashboard  = home_url('/member-dashboard/');
        $found         = count($by_member);
        $sent          = 0;
        $failed_emails = [];

        if ($test_email) {
            // Test mode: one summary email listing every member + their roles
            $lines = [];
            foreach ($by_member as $member) {
                $lines[] = "{$member['name']}: " . implode(', ', $member['roles']);
            }
            $subject = "[TEST] Role notification preview — {$date_fmt}";
            $message = "TEST PREVIEW — this is what each member will receive.\n\n"
                . "Meeting : {$date_fmt}\n"
                . ($start_fmt ? "Time    : {$start_fmt}\n" : '')
                . ($venue     ? "Venue   : {$venue}\n"     : '')
                . "Theme   : {$theme}\n\n"
                . "Members to be notified ({$found}):\n"
                . implode("\n", array_map(fn($l) => "  • {$l}", $lines)) . "\n\n"
                . "In production each member gets a personalised email with only their own roles.\n"
                . "Dashboard link sent: {$dashboard}";
            $sent = wp_mail($test_email, $subject, $message) ? $found : 0;
        } else {
            // Production: one personalised email per member, then one BCC blast for
            // any that failed (catches hosting per-execution mail() limits).
            $failed_emails = [];
            foreach ($by_member as $member) {
                if (empty($member['email'])) continue;
                $role_list  = implode("\n  • ", $member['roles']);
                $time_line  = $start_fmt ? "  Time    : {$start_fmt}\n" : '';
                $venue_line = $venue     ? "  Venue   : {$venue}\n"     : '';
                $subject    = "Your Toastmasters role — {$date_fmt}";
                $message    = "Hi {$member['name']},\n\n"
                    . "You have been assigned the following role(s) for our upcoming meeting:\n\n"
                    . "  • {$role_list}\n\n"
                    . "Meeting details:\n"
                    . "  Date    : {$date_fmt}\n"
                    . $time_line . $venue_line
                    . "  Theme   : {$theme}\n\n"
                    . "Please visit your dashboard to view the full agenda and prepare accordingly:\n"
                    . "{$dashboard}\n\n"
                    . "Looking forward to seeing you there!\n\n"
                    . "Regards,\nVP Education\nToastmasters Club of Pune North West";
                if (wp_mail($member['email'], $subject, $message)) {
                    $sent++;
                } else {
                    $failed_emails[] = $member['email'];
                }
            }

            // Fallback BCC for any that the host's mail() limit dropped
            if (!empty($failed_emails)) {
                $fallback_subject = "Toastmasters meeting roles — {$date_fmt} ({$theme})";
                $fallback_message = "Hi,\n\n"
                    . "This is a reminder that you have been assigned a role for our meeting on {$date_fmt}.\n"
                    . ($start_fmt ? "Time  : {$start_fmt}\n" : '')
                    . ($venue     ? "Venue : {$venue}\n"     : '')
                    . "Theme : {$theme}\n\n"
                    . "Please log in to your dashboard to see your specific role and the full agenda:\n"
                    . "{$dashboard}\n\n"
                    . "Regards,\nVP Education\nToastmasters Club of Pune North West";
                $headers = ['Bcc: ' . implode(', ', $failed_emails)];
                if (wp_mail(get_option('admin_email'), $fallback_subject, $fallback_message, $headers)) {
                    $sent += count($failed_emails);
                }
            }
        }

        return ['found' => $found, 'sent' => $sent, 'failed' => $found - $sent];
    }

    // =========================================================================
    // VOTING
    // =========================================================================

    public static function vote_nominees_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_vote_nominees';
    }

    public static function votes_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_votes';
    }

    /**
     * Which role names belong to which voting category.
     * Strips agenda parentheticals first (e.g. "Timer (Report)" → "Timer").
     * Uses word-boundary patterns to avoid false substring matches.
     */
    private static function nominee_category_for_role($role_name) {
        // Strip agenda detail suffix: "Toastmaster of the Day (Intro of theme)" → "Toastmaster of the Day"
        $base  = trim(preg_replace('/\s*\(.*\)$/', '', $role_name));
        $lower = strtolower($base);

        if (preg_match('/\b(toastmaster of the day|tmod|table topics master|ttm|general evaluator)\b/', $lower)) {
            return 'main_role';
        }

        if (preg_match('/\b(sergeant at arms|saa|timer|ah.counter|grammarian|active listener)\b/', $lower)) {
            return 'aux_role';
        }

        // "Table Topics Speaker" is managed live by VPE — exclude from auto-population
        if (preg_match('/\btable topics speaker\b/', $lower)) {
            return null;
        }

        if (preg_match('/\b(speaker|prepared speech|ice breaker)\b/', $lower)) {
            return 'speaker';
        }

        if (preg_match('/\bevaluator\b/', $lower)) {
            return 'evaluator';
        }

        return null;
    }

    /**
     * Auto-populate nominees from a meeting's confirmed role assignments.
     * Safe to call repeatedly — clears and re-inserts non-TT nominees.
     */
    public static function populate_vote_nominees($meeting_id) {
        global $wpdb;
        $nominees_table    = self::vote_nominees_table();
        $assignments_table = self::assignment_table();
        $members_table     = self::member_table();

        // Remove auto-populated rows; keep VPE-added TT speakers (member_id IS NULL with category = table_topics)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$nominees_table} WHERE meeting_id = %d AND category IN ('main_role', 'aux_role', 'speaker', 'evaluator')",
            $meeting_id
        ));

        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.role_name, a.member_id, m.full_name
               FROM {$assignments_table} a
          LEFT JOIN {$members_table} m ON m.id = a.member_id
              WHERE a.meeting_id = %d AND a.member_id IS NOT NULL",
            $meeting_id
        ), ARRAY_A);

        $sort = 0;
        $now  = current_time('mysql');
        $seen = []; // deduplicate: one nominee per (member_id, category)
        foreach ($assignments as $a) {
            // Base role name strips the agenda-segment detail in parentheses
            $base_role = trim(preg_replace('/\s*\(.*\)$/', '', $a['role_name']));
            $cat       = self::nominee_category_for_role($base_role);
            if (!$cat) continue;

            $key = $cat . '_' . $a['member_id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $wpdb->insert($nominees_table, [
                'meeting_id'   => (int) $meeting_id,
                'category'     => $cat,
                'member_id'    => (int) $a['member_id'],
                'display_name' => $a['full_name'] ?? '',
                'role_name'    => $base_role,
                'sort_order'   => $sort++,
                'created_at'   => $now,
            ]);
        }
    }

    /**
     * Returns nominees with live vote counts for a meeting.
     * Groups into [main_role => [...], aux_role => [...], table_topics => [...]]
     */
    public static function get_vote_nominees($meeting_id) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();
        $votes_table    = self::votes_table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.category, n.member_id, n.display_name, n.role_name, n.sort_order, n.is_winner,
                    COUNT(v.id) AS vote_count
               FROM {$nominees_table} n
          LEFT JOIN {$votes_table} v ON v.nominee_id = n.id
              WHERE n.meeting_id = %d
           GROUP BY n.id
           ORDER BY n.category, n.sort_order",
            $meeting_id
        ), ARRAY_A);

        $grouped = ['main_role' => [], 'aux_role' => [], 'table_topics' => [], 'speaker' => [], 'evaluator' => []];
        foreach ($rows as $row) {
            $row['vote_count'] = (int) $row['vote_count'];
            $row['is_winner']  = (int) $row['is_winner'];
            $cat = $row['category'];
            if (array_key_exists($cat, $grouped)) {
                $grouped[$cat][] = $row;
            }
        }
        return $grouped;
    }

    /**
     * VPE adds a Table Topics speaker live during the meeting.
     */
    public static function add_tt_speaker($meeting_id, $display_name, $member_id = null) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();

        $sort = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM {$nominees_table}
              WHERE meeting_id = %d AND category = 'table_topics'",
            $meeting_id
        ));

        $wpdb->insert($nominees_table, [
            'meeting_id'   => (int) $meeting_id,
            'category'     => 'table_topics',
            'member_id'    => $member_id ? (int) $member_id : null,
            'display_name' => sanitize_text_field($display_name),
            'role_name'    => 'Table Topics Speaker',
            'sort_order'   => $sort,
            'created_at'   => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Remove a TT speaker — only allowed if no votes cast yet.
     */
    public static function remove_tt_speaker($nominee_id) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();
        $votes_table    = self::votes_table();

        $nominee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$nominees_table} WHERE id = %d AND category = 'table_topics'",
            $nominee_id
        ), ARRAY_A);

        if (!$nominee) return new WP_Error('not_found', 'Nominee not found');

        $votes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$votes_table} WHERE nominee_id = %d",
            $nominee_id
        ));

        if ($votes > 0) return new WP_Error('has_votes', 'Cannot remove a nominee who has received votes');

        $wpdb->delete($nominees_table, ['id' => (int) $nominee_id]);
        return true;
    }

    /**
     * Cast a vote. Returns true on success, WP_Error on duplicate or invalid nominee.
     */
    public static function cast_vote($meeting_id, $nominee_id, $voter_token, $wp_user_id = null) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();
        $votes_table    = self::votes_table();

        $nominee = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$nominees_table} WHERE id = %d AND meeting_id = %d",
            $nominee_id, $meeting_id
        ), ARRAY_A);

        if (!$nominee) return new WP_Error('invalid_nominee', 'Nominee not found for this meeting');

        $category = $nominee['category'];

        // Check for duplicate vote in this category
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$votes_table}
              WHERE meeting_id = %d AND category = %s AND voter_token = %s",
            $meeting_id, $category, $voter_token
        ));

        if ($existing) return new WP_Error('already_voted', 'You have already voted in this category');

        $wpdb->insert($votes_table, [
            'meeting_id'   => (int) $meeting_id,
            'nominee_id'   => (int) $nominee_id,
            'category'     => $category,
            'voter_token'  => $voter_token,
            'wp_user_id'   => $wp_user_id ? (int) $wp_user_id : null,
            'voted_at'     => current_time('mysql'),
        ]);

        return true;
    }

    /**
     * Full results for VPE: nominees with vote counts, sorted by count desc.
     */
    public static function get_vote_results($meeting_id) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();
        $votes_table    = self::votes_table();

        $total_voters = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT voter_token) FROM {$votes_table} WHERE meeting_id = %d",
            $meeting_id
        ));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.category, n.display_name, n.role_name, COUNT(v.id) AS vote_count
               FROM {$nominees_table} n
          LEFT JOIN {$votes_table} v ON v.nominee_id = n.id
              WHERE n.meeting_id = %d
           GROUP BY n.id
           ORDER BY n.category, vote_count DESC",
            $meeting_id
        ), ARRAY_A);

        $grouped = ['main_role' => [], 'aux_role' => [], 'table_topics' => [], 'speaker' => [], 'evaluator' => []];
        foreach ($rows as $row) {
            $row['vote_count'] = (int) $row['vote_count'];
            $cat = $row['category'];
            if (array_key_exists($cat, $grouped)) {
                $grouped[$cat][] = $row;
            }
        }

        return ['total_voters' => $total_voters, 'results' => $grouped];
    }

    /**
     * Open or close the live poll for a meeting.
     */
    public static function set_poll_open($meeting_id, $open) {
        global $wpdb;
        $wpdb->update(
            self::meeting_table(),
            ['poll_open' => $open ? 1 : 0],
            ['id' => (int) $meeting_id]
        );
    }

    /**
     * Find the top vote-getter(s) in every category and mark them is_winner = 1.
     * Handles ties: all nominees sharing the highest count are marked winners.
     * Returns full results array.
     */
    public static function declare_winners($meeting_id) {
        global $wpdb;
        $nominees_table = self::vote_nominees_table();
        $votes_table    = self::votes_table();

        // Reset all winner flags for this meeting first
        $wpdb->query($wpdb->prepare(
            "UPDATE {$nominees_table} SET is_winner = 0 WHERE meeting_id = %d",
            $meeting_id
        ));

        $categories = ['main_role', 'aux_role', 'table_topics', 'speaker', 'evaluator'];
        foreach ($categories as $cat) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT n.id, COUNT(v.id) AS vote_count
                   FROM {$nominees_table} n
              LEFT JOIN {$votes_table} v ON v.nominee_id = n.id
                  WHERE n.meeting_id = %d AND n.category = %s
               GROUP BY n.id",
                $meeting_id, $cat
            ), ARRAY_A);

            if (empty($rows)) continue;
            $max = max(array_column($rows, 'vote_count'));
            if ((int) $max === 0) continue;

            $winner_ids = array_map('intval',
                array_column(array_filter($rows, fn($r) => (int) $r['vote_count'] === (int) $max), 'id')
            );
            if (empty($winner_ids)) continue;
            $placeholders = implode(',', $winner_ids);
            $wpdb->query("UPDATE {$nominees_table} SET is_winner = 1 WHERE id IN ({$placeholders})");
        }

        $wpdb->update(self::meeting_table(), ['winners_declared' => 1], ['id' => (int) $meeting_id]);

        return self::get_vote_results($meeting_id);
    }

    // -------------------------------------------------------------------------
    // Meeting Wrap-Up (Phase 1)
    // -------------------------------------------------------------------------

    /**
     * Load all data needed to render the VPE wrap-up panel for a meeting.
     */
    public static function get_wrap_up_data($meeting_id) {
        global $wpdb;
        $meeting_id      = (int) $meeting_id;
        $meetings_tbl    = self::meeting_table();
        $assignments_tbl = self::assignment_table();
        $members_tbl     = self::member_table();
        $attendance_tbl  = self::attendance_table();
        $history_tbl     = self::participation_history_table();
        $nominees_tbl    = self::vote_nominees_table();
        $votes_tbl       = self::votes_table();
        $wins_tbl        = self::win_history_table();

        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$meetings_tbl} WHERE id = %d", $meeting_id
        ), ARRAY_A);
        if (!$meeting) return null;

        // All assigned member slots (non-break, has a member)
        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id as assignment_id, a.role_name, a.member_id, m.full_name
               FROM {$assignments_tbl} a
          LEFT JOIN {$members_tbl} m ON m.id = a.member_id
              WHERE a.meeting_id = %d
                AND a.member_id IS NOT NULL AND a.member_id > 0
                AND a.role_name NOT LIKE 'Break%%'
              ORDER BY a.sort_order",
            $meeting_id
        ), ARRAY_A);

        // Who is already recorded as attended
        $attended_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$attendance_tbl}
              WHERE meeting_id = %d AND member_id IS NOT NULL",
            $meeting_id
        )));

        // Which assignments already have a participation_history entry
        $performed_aids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT assignment_id FROM {$history_tbl} WHERE meeting_id = %d",
            $meeting_id
        )));

        // Build deduplicated role-performer list (one row per member, strip sub-segment labels).
        $assignment_by_member = [];
        foreach ($assignments as $a) {
            $mid = (int) $a['member_id'];
            if (!$mid || isset($assignment_by_member[$mid])) continue;
            $assignment_by_member[$mid] = [
                'assignment_id' => (int) $a['assignment_id'],
                'role_name'     => preg_replace('/\s*\(.*\)$/', '', $a['role_name']),
                'full_name'     => $a['full_name'] ?? 'Unknown',
            ];
        }

        $role_performers = [];
        foreach ($assignment_by_member as $mid => $asgn) {
            $role_performers[] = [
                'assignment_id'  => $asgn['assignment_id'],
                'member_id'      => $mid,
                'full_name'      => $asgn['full_name'],
                'role_name'      => $asgn['role_name'],
                'attended'       => in_array($mid, $attended_ids),
                'role_performed' => in_array($asgn['assignment_id'], $performed_aids),
            ];
        }

        // Walk-in members: attended but no assigned role (populated by SAA or previous save).
        // other_members: everyone else — for the VPE search-to-add picker.
        $all_others = $wpdb->get_results(
            empty($assignment_by_member)
                ? "SELECT id, full_name FROM {$members_tbl} ORDER BY full_name"
                : "SELECT id, full_name FROM {$members_tbl}
                    WHERE id NOT IN (" . implode(',', array_map('intval', array_keys($assignment_by_member))) . ")
                    ORDER BY full_name",
            ARRAY_A
        ) ?: [];

        $walk_ins      = [];
        $other_members = [];
        foreach ($all_others as $m) {
            $mid = (int) $m['id'];
            if (in_array($mid, $attended_ids)) {
                $walk_ins[] = ['member_id' => $mid, 'full_name' => $m['full_name']];
            } else {
                $other_members[] = ['member_id' => $mid, 'full_name' => $m['full_name']];
            }
        }

        // Existing guest attendance rows
        $guests = $wpdb->get_results($wpdb->prepare(
            "SELECT id, guest_name FROM {$attendance_tbl}
              WHERE meeting_id = %d AND member_id IS NULL",
            $meeting_id
        ), ARRAY_A) ?: [];

        // All nominees with vote counts. is_winner=1 rows are pre-checked; others let VPE decide.
        $vote_winners = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.category, n.member_id, n.display_name, n.role_name, n.is_winner,
                    COUNT(v.id) AS vote_count
               FROM {$nominees_tbl} n
          LEFT JOIN {$votes_tbl} v ON v.nominee_id = n.id
              WHERE n.meeting_id = %d
           GROUP BY n.id
           ORDER BY n.category, vote_count DESC, n.sort_order",
            $meeting_id
        ), ARRAY_A) ?: [];
        foreach ($vote_winners as &$w) {
            $w['vote_count'] = (int) $w['vote_count'];
            $w['is_winner']  = (bool) $w['is_winner'];
            $w['member_id']  = $w['member_id'] ? (int) $w['member_id'] : null;
        }
        unset($w);

        // Already-saved win history entries
        $existing_wins = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wins_tbl} WHERE meeting_id = %d ORDER BY category",
            $meeting_id
        ), ARRAY_A) ?: [];

        return [
            'meeting'          => $meeting,
            'role_performers'  => $role_performers,
            'walk_ins'         => $walk_ins,
            'other_members'    => $other_members,
            'guests'           => $guests,
            'vote_winners'     => $vote_winners,
            'existing_wins'    => $existing_wins,
            'wrapped_up'       => (bool) ($meeting['wrapped_up'] ?? false),
        ];
    }

    /**
     * Returns today's meeting + full member list if the current user is the SAA for it.
     */
    public static function get_saa_meeting() {
        global $wpdb;
        $me = self::current_member();
        if (!$me) return null;

        $meetings_tbl    = self::meeting_table();
        $assignments_tbl = self::assignment_table();
        $attendance_tbl  = self::attendance_table();
        $members_tbl     = self::member_table();
        $today           = current_time('Y-m-d');

        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT m.* FROM {$meetings_tbl} m
               JOIN {$assignments_tbl} a ON a.meeting_id = m.id
              WHERE m.meeting_date = %s
                AND a.member_id = %d
                AND (a.role_name LIKE '%%Sergeant%%' OR a.role_name LIKE '%%SAA%%')
              LIMIT 1",
            $today, (int) $me['id']
        ), ARRAY_A);
        if (!$meeting) return null;

        $mid = (int) $meeting['id'];

        $attended_ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT member_id FROM {$attendance_tbl} WHERE meeting_id = %d AND member_id IS NOT NULL",
            $mid
        )));

        $guests = $wpdb->get_results($wpdb->prepare(
            "SELECT id, guest_name FROM {$attendance_tbl} WHERE meeting_id = %d AND member_id IS NULL",
            $mid
        ), ARRAY_A) ?: [];

        $all_members = $wpdb->get_results(
            "SELECT id, full_name FROM {$members_tbl} ORDER BY full_name",
            ARRAY_A
        ) ?: [];

        $members = [];
        foreach ($all_members as $m) {
            $m_id = (int) $m['id'];
            $members[] = [
                'member_id' => $m_id,
                'full_name' => $m['full_name'],
                'attended'  => in_array($m_id, $attended_ids),
            ];
        }

        return [
            'meeting_id'   => $mid,
            'meeting_date' => $meeting['meeting_date'],
            'theme'        => $meeting['theme'],
            'members'      => $members,
            'guests'       => $guests,
        ];
    }

    /**
     * SAA marks attendance for a meeting (member list + guests). Idempotent.
     */
    public static function save_saa_attendance($meeting_id, $data) {
        global $wpdb;
        $meeting_id  = (int) $meeting_id;
        $tbl         = self::attendance_table();
        $now         = current_time('mysql');
        $current_uid = (int) get_current_user_id();

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tbl} WHERE meeting_id = %d AND member_id IS NOT NULL", $meeting_id
        ));
        foreach ((array) ($data['attended_member_ids'] ?? []) as $mid) {
            $mid = (int) $mid;
            if (!$mid) continue;
            $wpdb->insert($tbl, [
                'meeting_id' => $meeting_id, 'member_id' => $mid,
                'guest_name' => null, 'marked_by' => $current_uid, 'created_at' => $now,
            ]);
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$tbl} WHERE meeting_id = %d AND member_id IS NULL", $meeting_id
        ));
        foreach ((array) ($data['guests'] ?? []) as $guest) {
            $name = sanitize_text_field($guest['name'] ?? '');
            if (!$name) continue;
            $wpdb->insert($tbl, [
                'meeting_id' => $meeting_id, 'member_id' => null,
                'guest_name' => $name, 'marked_by' => $current_uid, 'created_at' => $now,
            ]);
        }
        return true;
    }

    /**
     * Atomically record attendance, role completions, and winners for a meeting.
     * Safe to re-run (clears and re-inserts attendance + win history; skips
     * participation_history rows that already exist to avoid duplicates).
     */
    public static function save_wrap_up($meeting_id, $data) {
        global $wpdb;
        $meeting_id      = (int) $meeting_id;
        $meetings_tbl    = self::meeting_table();
        $attendance_tbl  = self::attendance_table();
        $history_tbl     = self::participation_history_table();
        $assignments_tbl = self::assignment_table();
        $wins_tbl        = self::win_history_table();

        $now         = current_time('mysql');
        $current_uid = (int) get_current_user_id();

        $meeting_date = $wpdb->get_var($wpdb->prepare(
            "SELECT meeting_date FROM {$meetings_tbl} WHERE id = %d", $meeting_id
        ));

        // ── Attendance: clear + re-insert ────────────────────────────────────
        $wpdb->delete($attendance_tbl, ['meeting_id' => $meeting_id]);

        foreach ((array) ($data['attendance'] ?? []) as $item) {
            $mid = (int) ($item['member_id'] ?? 0);
            if (!$mid) continue;
            $wpdb->insert($attendance_tbl, [
                'meeting_id' => $meeting_id,
                'member_id'  => $mid,
                'guest_name' => null,
                'marked_by'  => $current_uid,
                'created_at' => $now,
            ]);
        }

        foreach ((array) ($data['guests'] ?? []) as $guest) {
            $name = sanitize_text_field($guest['name'] ?? '');
            if (!$name) continue;
            $wpdb->insert($attendance_tbl, [
                'meeting_id' => $meeting_id,
                'member_id'  => null,
                'guest_name' => $name,
                'marked_by'  => $current_uid,
                'created_at' => $now,
            ]);
        }

        // ── Participation history: add for roles performed (skip existing) ───
        foreach ((array) ($data['attendance'] ?? []) as $item) {
            if (empty($item['role_performed'])) continue;
            $mid = (int) ($item['member_id'] ?? 0);
            $aid = (int) ($item['assignment_id'] ?? 0);
            if (!$mid || !$aid) continue;

            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$history_tbl}
                  WHERE member_id = %d AND assignment_id = %d",
                $mid, $aid
            ));
            if ($exists) continue;

            $member   = self::get_member($mid);
            $role_row = $wpdb->get_row($wpdb->prepare(
                "SELECT role_name, presentation_series FROM {$assignments_tbl} WHERE id = %d",
                $aid
            ), ARRAY_A);
            if (!$role_row) continue;

            $base_role = self::get_base_role_name($role_row['role_name']);
            if (strtolower($base_role) === 'break') continue;

            $wpdb->insert($history_tbl, [
                'member_id'           => $mid,
                'meeting_id'          => $meeting_id,
                'assignment_id'       => $aid,
                'role_name'           => $base_role,
                'meeting_date'        => $meeting_date,
                'level_at_completion' => max(1, (int) ($member['level'] ?? 1)),
                'presentation_series' => !empty($role_row['presentation_series'])
                    ? sanitize_text_field($role_row['presentation_series']) : null,
                'created_at'          => $now,
            ]);
        }

        // ── Win history: clear + re-insert ───────────────────────────────────
        $wpdb->delete($wins_tbl, ['meeting_id' => $meeting_id]);

        foreach ((array) ($data['winners'] ?? []) as $w) {
            $cat = sanitize_text_field($w['category'] ?? '');
            if (!$cat) continue;
            $mid          = !empty($w['member_id']) ? (int) $w['member_id'] : null;
            $display_name = sanitize_text_field($w['display_name'] ?? '');
            if (!$display_name && $mid) {
                $m = self::get_member($mid);
                $display_name = $m['full_name'] ?? '';
            }
            $wpdb->insert($wins_tbl, [
                'meeting_id'   => $meeting_id,
                'member_id'    => $mid,
                'display_name' => $display_name,
                'category'     => $cat,
                'role_name'    => sanitize_text_field($w['role_name'] ?? ''),
                'vote_count'   => (int) ($w['vote_count'] ?? 0),
                'is_tie'       => (int) ($w['is_tie'] ?? 0),
                'won_at'       => $meeting_date,
                'created_at'   => $now,
            ]);
        }

        // ── Mark meeting complete ─────────────────────────────────────────────
        $wpdb->update(
            $meetings_tbl,
            ['wrapped_up' => 1, 'poll_open' => 0],
            ['id' => $meeting_id]
        );

        return true;
    }

    // -------------------------------------------------------------------------
    // Pathways Level Progress (L1–L3 inference engine)
    // -------------------------------------------------------------------------

    private static function pathway_offsets_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_pathway_offsets';
    }

    private static function level_up_requests_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_level_up_requests';
    }

    /**
     * Minimum speeches required per level (all 11 paths share the same counts for L1–L3).
     * L4+ not tracked here — too complex for speech-count inference.
     */
    public static function get_pathways_speech_requirements(): array {
        $default = [1 => 5, 2 => 3, 3 => 3];
        return [
            'Dynamic Leadership'      => $default,
            'Effective Coaching'      => $default,
            'Engaging Humor'          => $default,
            'Innovative Planning'     => $default,
            'Leadership Development'  => $default,
            'Motivational Strategies' => $default,
            'Persuasive Influence'    => $default,
            'Presentation Mastery'    => $default,
            'Strategic Relationships' => $default,
            'Team Collaboration'      => $default,
            'Visionary Communication' => $default,
            '_default'                => $default,
        ];
    }

    /**
     * Counts speeches at a given level for a member and compares to the path requirement.
     * Returns null for L4+ (not tracked by inference).
     */
    public static function get_member_level_speech_progress($member_id, $level): ?array {
        if ($level < 1 || $level > 3) {
            return null;
        }

        global $wpdb;
        $history = self::participation_history_table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT role_name, meeting_date
               FROM {$history}
              WHERE member_id = %d
                AND level_at_completion = %d
                AND (role_name LIKE 'Speaker%%' OR role_name = 'Ice Breaker')
              ORDER BY meeting_date ASC",
            $member_id, $level
        ), ARRAY_A);

        $speeches = array_map(fn($r) => [
            'meeting_date' => $r['meeting_date'],
            'role_name'    => $r['role_name'],
        ], $rows);

        $done_from_history = count($speeches);

        $offset = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT `offset` FROM " . self::pathway_offsets_table() . "
              WHERE member_id = %d AND level = %d",
            $member_id, $level
        ));

        $member = self::get_member((int) $member_id);
        $reqs   = self::get_pathways_speech_requirements();
        $path   = $member['pathway'] ?? '_default';
        $needed = $reqs[$path][$level] ?? $reqs['_default'][$level];

        $done = $done_from_history + $offset;

        return [
            'done'     => min($done, $needed),
            'needed'   => $needed,
            'met'      => $done >= $needed,
            'offset'   => $offset,
            'speeches' => $speeches,
        ];
    }

    /**
     * Combined level status: speech progress (L1–L3 only) + club role gaps.
     */
    public static function get_member_full_level_status($member_id): array {
        $member = self::get_member((int) $member_id);
        $level  = (int)($member['level'] ?? 1);

        $speech_progress = self::get_member_level_speech_progress($member_id, $level);
        $role_gaps       = self::get_member_level_gaps($member_id, $level);

        $all_roles_met = empty(array_filter($role_gaps, fn($g) => !$g['met']));

        if ($speech_progress === null) {
            // L4+ — only role gaps tracked
            $ready          = $all_roles_met;
            $verdict        = $ready ? 'complete' : 'incomplete';
            $verdict_detail = $ready ? [] : array_map(
                fn($g) => $g['label'] . ' not completed',
                array_filter($role_gaps, fn($g) => !$g['met'])
            );
        } else {
            $ready   = $speech_progress['met'] && $all_roles_met;
            $verdict = $ready ? 'complete' : 'incomplete';
            $verdict_detail = [];
            if (!$speech_progress['met']) {
                $still = $speech_progress['needed'] - $speech_progress['done'];
                $verdict_detail[] = "{$still} more speech" . ($still > 1 ? 'es' : '') . " needed at Level {$level}";
            }
            foreach (array_filter($role_gaps, fn($g) => !$g['met']) as $g) {
                $verdict_detail[] = $g['label'] . ' not completed';
            }
        }

        return [
            'level'            => $level,
            'level_completed'  => (int)($member['level_completed'] ?? 0),
            'pathway'          => $member['pathway'] ?? '',
            'speech_progress'  => $speech_progress,
            'role_gaps'        => $role_gaps,
            'ready_to_advance' => $ready,
            'system_verdict'   => $verdict,
            'verdict_detail'   => array_values($verdict_detail),
        ];
    }

    /**
     * Returns mentee progress alerts for a mentor's dashboard.
     */
    public static function get_mentor_mentee_alerts($mentor_member_id): array {
        global $wpdb;
        $members = self::member_table();

        $mentees = $wpdb->get_results($wpdb->prepare(
            "SELECT id, full_name, level, pathway
               FROM {$members}
              WHERE mentor_id = %d AND state != 'Resigned'
              ORDER BY full_name ASC",
            $mentor_member_id
        ), ARRAY_A);

        $result = [];
        foreach ($mentees as $mentee) {
            $m_id   = (int) $mentee['id'];
            $status = self::get_member_full_level_status($m_id);
            $alerts = [];

            if ($status['speech_progress'] !== null && $status['speech_progress']['done'] === 0) {
                $alerts[] = [
                    'type'  => 'needs_first_speech',
                    'label' => 'Has not delivered any speech at Level ' . $status['level'] . ' yet',
                ];
            }
            foreach ($status['role_gaps'] as $gap) {
                if (!$gap['met']) {
                    $alerts[] = [
                        'type'  => 'needs_role',
                        'role'  => $gap['label'],
                        'label' => $gap['label'] . ' role not completed at Level ' . $status['level'],
                    ];
                }
            }
            if ($status['ready_to_advance']) {
                $alerts[] = [
                    'type'  => 'ready_for_level_up',
                    'label' => 'All Level ' . $status['level'] . ' requirements met — ready to advance!',
                ];
            }

            $result[] = [
                'member_id'       => $m_id,
                'name'            => $mentee['full_name'],
                'level'           => (int) $mentee['level'],
                'pathway'         => $mentee['pathway'],
                'alerts'          => $alerts,
                'speech_progress' => $status['speech_progress'],
                'ready_to_advance'=> $status['ready_to_advance'],
            ];
        }
        return $result;
    }

    /**
     * Member submits a formal level-up request. Returns new request ID or WP_Error.
     */
    public static function submit_level_up_request($member_id, $note = '') {
        global $wpdb;
        $table  = self::level_up_requests_table();
        $member = self::get_member((int) $member_id);
        $level  = (int)($member['level'] ?? 1);

        if ($level >= 3) {
            return new WP_Error('tmp_level_limit', 'Level-up requests are only supported for L1→L2 and L2→L3.');
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE member_id = %d AND status = 'pending'",
            $member_id
        ));
        if ($existing) {
            return new WP_Error('tmp_duplicate_request', 'A pending level-up request already exists.');
        }

        $status  = self::get_member_full_level_status((int) $member_id);
        $verdict = $status['system_verdict'];

        $wpdb->insert($table, [
            'member_id'      => (int) $member_id,
            'from_level'     => $level,
            'to_level'       => $level + 1,
            'status'         => 'pending',
            'member_note'    => sanitize_textarea_field($note),
            'evidence'       => wp_json_encode($status),
            'system_verdict' => $verdict,
            'created_at'     => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * VPE approves a level-up request. Bumps the member's level.
     */
    public static function approve_level_up_request($request_id, $vpe_user_id, $note = ''): bool {
        global $wpdb;
        $table = self::level_up_requests_table();

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND status = 'pending'",
            $request_id
        ), ARRAY_A);
        if (!$req) {
            return false;
        }

        $wpdb->update($table, [
            'status'      => 'approved',
            'vpe_note'    => sanitize_textarea_field($note),
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => (int) $vpe_user_id,
        ], ['id' => (int) $request_id]);

        self::upsert_member((int) $req['member_id'], ['level' => (int) $req['to_level']]);
        return true;
    }

    /**
     * VPE denies a level-up request.
     */
    public static function deny_level_up_request($request_id, $vpe_user_id, $note = ''): bool {
        global $wpdb;
        $table = self::level_up_requests_table();

        $updated = $wpdb->update($table, [
            'status'      => 'denied',
            'vpe_note'    => sanitize_textarea_field($note),
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => (int) $vpe_user_id,
        ], ['id' => (int) $request_id, 'status' => 'pending']);

        return (bool) $updated;
    }

    /**
     * VPE sets a pre-system speech offset for a member at a given level.
     */
    public static function set_pathway_offset($member_id, $level, $offset, $notes = ''): bool {
        global $wpdb;
        $table = self::pathway_offsets_table();

        // Use INSERT ... ON DUPLICATE KEY UPDATE with explicit backtick on `offset`
        // because $wpdb->replace() leaves column names unquoted and 'offset' is a SQL keyword.
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (member_id, level, `offset`, notes, created_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE `offset` = VALUES(`offset`), notes = VALUES(notes), created_at = VALUES(created_at)",
            (int) $member_id,
            (int) $level,
            max(0, (int) $offset),
            sanitize_text_field($notes),
            current_time('mysql')
        ));

        return $result !== false;
    }

    // -------------------------------------------------------------------------
    // New Member Spotlight
    // -------------------------------------------------------------------------

    public static function get_new_member_spotlight() {
        $raw = get_option('tmp_new_member_spotlight', null);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        if (empty($data['active']) || empty($data['member_id'])) return null;
        $member = self::get_member((int) $data['member_id']);
        if (!$member) return null;
        return [
            'member'    => $member,
            'blurb'     => $data['blurb']     ?? '',
            'photo_url' => $data['photo_url'] ?? '',
        ];
    }

    // =========================================================================
    // Recognition — TM of Month / Quarter
    // =========================================================================

    public static function mentor_ratings_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_mentor_ratings';
    }

    public static function recognition_awards_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_recognition_awards';
    }

    /**
     * Roles that count as "service roles" for the recognition scoring.
     * Prepared speeches (Ice Breaker, project speeches) are personal development
     * and are intentionally excluded — they don't count toward club service.
     */
    public static function is_service_role($role_name) {
        $lower = strtolower(trim($role_name));
        $service_patterns = [
            'timer', 'grammarian', 'ah-counter', 'ah counter',
            'sergeant at arms', 'toastmaster of the day',
            'general evaluator', 'evaluator',
            'table topics master', 'table topics speaker',
            'introductory mentor', 'intro mentor',
            'presiding officer', 'educational presentation',
            'active listener',
        ];
        foreach ($service_patterns as $p) {
            if (strpos($lower, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compute recognition scores for all active members over a date range.
     *
     * Scoring (100 pts total):
     *   A. Club Service (60 pts — rate-based, capped at 1 per meeting)
     *      A1. Attendance rate × 25
     *      A2. Service role rate × 35  (service roles taken, max 1 per meeting / total meetings)
     *   B. Achievements (35 pts)
     *      B1. Win rate × 20           (wins / total meetings)
     *      B2. Level up in period = 15 flat pts
     *   C. Mentor bonus (5 pts)        (avg mentee rating / 5 × 5)
     *
     * Returns array of members sorted by score desc, each with score_breakdown.
     */
    public static function compute_recognition_scores($period_start, $period_end) {
        global $wpdb;

        $members_tbl    = self::member_table();
        $attendance_tbl = self::attendance_table();
        $history_tbl    = self::participation_history_table();
        $wins_tbl       = self::win_history_table();
        $level_ups_tbl  = $wpdb->prefix . 'tmp_level_up_history';
        $ratings_tbl    = self::mentor_ratings_table();

        // Total meetings held in the period
        $total_meetings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::meeting_table() . "
             WHERE meeting_date BETWEEN %s AND %s AND wrapped_up = 1",
            $period_start, $period_end
        ));
        if ($total_meetings === 0) {
            return [];
        }

        // A1 — meetings each member attended
        $attendance_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, COUNT(DISTINCT meeting_id) as cnt
             FROM {$attendance_tbl}
             WHERE member_id IS NOT NULL
               AND meeting_id IN (
                   SELECT id FROM " . self::meeting_table() . "
                   WHERE meeting_date BETWEEN %s AND %s AND wrapped_up = 1
               )
             GROUP BY member_id",
            $period_start, $period_end
        ), ARRAY_A);
        $attendance_map = [];
        foreach ($attendance_rows as $r) {
            $attendance_map[(int) $r['member_id']] = (int) $r['cnt'];
        }

        // A2 — service roles taken, deduplicated to 1 per meeting per member
        // We count distinct meeting_ids where at least one service role was performed.
        $history_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, meeting_id, role_name
             FROM {$history_tbl}
             WHERE meeting_date BETWEEN %s AND %s",
            $period_start, $period_end
        ), ARRAY_A);

        $service_meetings_map = []; // member_id → set of meeting_ids with a service role
        foreach ($history_rows as $r) {
            if (self::is_service_role($r['role_name'])) {
                $mid = (int) $r['member_id'];
                $service_meetings_map[$mid][(int) $r['meeting_id']] = true;
            }
        }

        // B1 — meeting wins in period
        $win_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, COUNT(*) as cnt
             FROM {$wins_tbl}
             WHERE member_id IS NOT NULL AND won_at BETWEEN %s AND %s
             GROUP BY member_id",
            $period_start, $period_end
        ), ARRAY_A);
        $wins_map = [];
        foreach ($win_rows as $r) {
            $wins_map[(int) $r['member_id']] = (int) $r['cnt'];
        }

        // B2 — level ups in period
        $levelup_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id FROM {$level_ups_tbl}
             WHERE leveled_up_at BETWEEN %s AND %s",
            $period_start . ' 00:00:00', $period_end . ' 23:59:59'
        ), ARRAY_A);
        $levelup_set = [];
        foreach ($levelup_rows as $r) {
            $levelup_set[(int) $r['member_id']] = true;
        }

        // C — mentor ratings (avg per mentor over period)
        $rating_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT mentor_id, AVG(rating) as avg_rating, COUNT(*) as cnt
             FROM {$ratings_tbl}
             WHERE period_start >= %s AND period_end <= %s
             GROUP BY mentor_id",
            $period_start, $period_end
        ), ARRAY_A);
        $ratings_map = [];
        foreach ($rating_rows as $r) {
            $ratings_map[(int) $r['mentor_id']] = round((float) $r['avg_rating'], 2);
        }

        // Score all active members
        $members = $wpdb->get_results(
            "SELECT id, full_name, pathway, level, mentor_id
             FROM {$members_tbl}
             WHERE state = 'Active'
             ORDER BY full_name ASC",
            ARRAY_A
        );

        $results = [];
        foreach ($members as $m) {
            $mid = (int) $m['id'];

            $attended       = $attendance_map[$mid] ?? 0;
            $service_count  = isset($service_meetings_map[$mid]) ? count($service_meetings_map[$mid]) : 0;
            $wins           = $wins_map[$mid] ?? 0;
            $leveled_up     = isset($levelup_set[$mid]);
            $avg_rating     = $ratings_map[$mid] ?? null;

            $a1 = round(($attended       / $total_meetings) * 25, 2);
            $a2 = round(($service_count  / $total_meetings) * 35, 2);
            $b1 = round(($wins           / $total_meetings) * 20, 2);
            $b2 = $leveled_up ? 15.0 : 0.0;
            $c  = $avg_rating !== null ? round(($avg_rating / 5) * 5, 2) : 0.0;

            $total = $a1 + $a2 + $b1 + $b2 + $c;

            $results[] = [
                'member_id'   => $mid,
                'member_name' => $m['full_name'],
                'pathway'     => $m['pathway'],
                'level'       => (int) $m['level'],
                'score'       => round($total, 2),
                'breakdown'   => [
                    'attendance_meetings' => $attended,
                    'total_meetings'      => $total_meetings,
                    'attendance_score'    => $a1,
                    'service_meetings'    => $service_count,
                    'service_score'       => $a2,
                    'wins'                => $wins,
                    'win_score'           => $b1,
                    'leveled_up'          => $leveled_up,
                    'level_up_score'      => $b2,
                    'mentor_avg_rating'   => $avg_rating,
                    'mentor_score'        => $c,
                ],
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }

    // ── Mentor ratings ────────────────────────────────────────────────────────

    public static function save_mentor_rating($data) {
        global $wpdb;
        $table = self::mentor_ratings_table();
        $now   = current_time('mysql');

        $mentee_id    = absint($data['mentee_id'] ?? 0);
        $mentor_id    = absint($data['mentor_id']  ?? 0);
        $rating       = max(1, min(5, absint($data['rating'] ?? 0)));
        $feedback     = sanitize_textarea_field($data['feedback'] ?? '');
        $period_start = sanitize_text_field($data['period_start'] ?? '');
        $period_end   = sanitize_text_field($data['period_end']   ?? '');

        if (!$mentee_id || !$mentor_id || !$period_start || !$period_end) {
            return new WP_Error('tmp_invalid', 'Missing required fields.', ['status' => 400]);
        }

        // Upsert — one rating per mentee per period
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE mentee_id = %d AND period_start = %s AND period_end = %s",
            $mentee_id, $period_start, $period_end
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                ['rating' => $rating, 'feedback' => $feedback, 'created_at' => $now],
                ['id' => (int) $existing]
            );
        } else {
            $wpdb->insert($table, [
                'mentor_id'    => $mentor_id,
                'mentee_id'    => $mentee_id,
                'rating'       => $rating,
                'feedback'     => $feedback,
                'period_start' => $period_start,
                'period_end'   => $period_end,
                'created_at'   => $now,
            ]);
        }

        return ['success' => true];
    }

    public static function get_mentor_rating_for_period($mentee_id, $period_start, $period_end) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::mentor_ratings_table() . "
             WHERE mentee_id = %d AND period_start = %s AND period_end = %s",
            absint($mentee_id), $period_start, $period_end
        ), ARRAY_A);
    }

    // ── Award CRUD ────────────────────────────────────────────────────────────

    public static function declare_recognition_award($data) {
        global $wpdb;
        $table = self::recognition_awards_table();
        $now   = current_time('mysql');

        $member_id    = absint($data['member_id']    ?? 0);
        $period_type  = sanitize_text_field($data['period_type']  ?? '');
        $period_start = sanitize_text_field($data['period_start'] ?? '');
        $period_end   = sanitize_text_field($data['period_end']   ?? '');

        if (!$member_id || !in_array($period_type, ['month', 'quarter'], true) || !$period_start || !$period_end) {
            return new WP_Error('tmp_invalid', 'Missing required fields.', ['status' => 400]);
        }

        $member = self::get_member($member_id);
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }

        $breakdown  = $data['breakdown'] ?? [];
        $score      = (float) ($data['score'] ?? 0);
        $period_label = sanitize_text_field($data['period_label'] ?? '');
        $show_home  = isset($data['display_on_homepage']) ? (int) (bool) $data['display_on_homepage'] : 1;
        $send_email = !empty($data['send_email']);

        $wpdb->insert($table, [
            'member_id'           => $member_id,
            'member_name'         => $member['full_name'],
            'period_type'         => $period_type,
            'period_label'        => $period_label,
            'period_start'        => $period_start,
            'period_end'          => $period_end,
            'score'               => $score,
            'score_breakdown'     => wp_json_encode($breakdown),
            'declared_by'         => get_current_user_id(),
            'declared_at'         => $now,
            'display_on_homepage' => $show_home,
            'email_sent'          => 0,
        ]);

        $award_id = (int) $wpdb->insert_id;
        if (!$award_id) {
            return new WP_Error('tmp_db_error', 'Failed to save award.', ['status' => 500]);
        }

        if ($send_email) {
            self::send_recognition_email($award_id, $member, $period_type, $period_label, $send_email);
        }

        return ['success' => true, 'award_id' => $award_id];
    }

    public static function update_recognition_award_homepage($id, $show) {
        global $wpdb;
        return (bool) $wpdb->update(
            self::recognition_awards_table(),
            ['display_on_homepage' => (int) (bool) $show],
            ['id' => absint($id)]
        );
    }

    public static function delete_recognition_award($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::recognition_awards_table(), ['id' => absint($id)]);
    }

    public static function get_recognition_awards($period_type = null, $limit = 20) {
        global $wpdb;
        $table = self::recognition_awards_table();

        $where = $period_type ? $wpdb->prepare("WHERE period_type = %s", $period_type) : '';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY period_start DESC LIMIT %d",
                absint($limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_homepage_recognition_awards() {
        global $wpdb;
        $table = self::recognition_awards_table();

        // Latest declared monthly and quarterly award that are marked for homepage display
        $month   = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE period_type = 'month' AND display_on_homepage = 1 ORDER BY period_start DESC LIMIT 1",
            ARRAY_A
        );
        $quarter = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE period_type = 'quarter' AND display_on_homepage = 1 ORDER BY period_start DESC LIMIT 1",
            ARRAY_A
        );

        return [
            'month'   => $month   ?: null,
            'quarter' => $quarter ?: null,
        ];
    }

    public static function get_member_recognition_history($member_id) {
        global $wpdb;
        $table = self::recognition_awards_table();
        return $wpdb->get_results($wpdb->prepare(
            "SELECT period_type, period_label, period_start, score, declared_at
             FROM {$table}
             WHERE member_id = %d
             ORDER BY period_start DESC",
            absint($member_id)
        ), ARRAY_A) ?: [];
    }

    private static function send_recognition_email($award_id, $member, $period_type, $period_label, $broadcast) {
        global $wpdb;

        $club_name    = get_bloginfo('name');
        $period_title = $period_type === 'quarter' ? 'Toastmaster of the Quarter' : 'Toastmaster of the Month';
        $winner_name  = $member['full_name'];
        $winner_email = $member['email'];

        $subject = "{$club_name}: {$period_title} — Congratulations, {$winner_name}!";
        $body    = "Dear {$winner_name},\n\n"
            . "Congratulations! You have been named {$period_title} for {$period_label} "
            . "at {$club_name}.\n\n"
            . "Thank you for your dedication, participation, and service to the club. "
            . "Your commitment inspires the whole team!\n\n"
            . "Keep up the great work,\n{$club_name} Leadership";

        wp_mail($winner_email, $subject, $body);

        if ($broadcast === 'all') {
            $members_tbl = self::member_table();
            $emails = $wpdb->get_col(
                "SELECT email FROM {$members_tbl} WHERE state = 'Active' AND email != ''"
            );
            foreach ($emails as $email) {
                if ($email === $winner_email) continue;
                $bcast_body = "Dear Club Member,\n\n"
                    . "We are pleased to announce that {$winner_name} has been named "
                    . "{$period_title} for {$period_label} at {$club_name}.\n\n"
                    . "Please join us in congratulating {$winner_name} on this achievement!\n\n"
                    . "Best regards,\n{$club_name} Leadership";
                wp_mail($email, "{$club_name}: {$period_title} Announcement", $bcast_body);
            }
        }

        $wpdb->update(
            self::recognition_awards_table(),
            ['email_sent' => 1],
            ['id' => absint($award_id)]
        );
    }
}
