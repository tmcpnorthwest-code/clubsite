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

    // -------------------------------------------------------------------------
    // Standard roles & TI requirements
    // -------------------------------------------------------------------------

    public static function get_standard_roles() {
        return [
            'Sergeant at Arms'       => 'SAA',
            'Presiding Officer'      => 'Presiding Officer',
            'Toastmaster of the Day' => 'TMOD',
            'Table Topics Master'    => 'Topics Master',
            'Table Topics Speaker'   => 'TT Speaker',
            'General Evaluator'      => 'GE',
            'Timer'                  => 'Timer',
            'Ah-Counter'             => 'Ah-Counter',
            'Grammarian'             => 'Grammarian',
            'Introductory Mentor'    => 'Intro Mentor',
            'Educational Presentation' => 'Edu Presentation',
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
                ['type' => 'role_or', 'roles' => ['Timer', 'Ah-Counter'],  'min' => 1, 'label' => 'Timer or Ah-Counter'],
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

        return $gaps;
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

            $row['is_eligible']                   = (!$is_unpaid || $is_exempt);
            $row['formatted_name']                = sprintf("%s (%s Level %d)", $row['full_name'], $row['pathway'], $row['level']);
            $row['recent_participation_count']    = $participation_map[$row['id']] ?? 0;
            $row['total_recent_meetings_checked'] = count($last_3_meeting_ids);
        }

        return $rows;
    }

    public static function get_member($id) {
        global $wpdb;
        $table = self::member_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, mentor.full_name as mentor_name
             FROM {$table} m
             LEFT JOIN {$table} mentor ON m.mentor_id = mentor.id
             WHERE m.id = %d",
            $id
        ), ARRAY_A);
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

        $record = array(
            'user_id'                    => !empty($data['user_id']) ? absint($data['user_id']) : null,
            'customer_id'                => sanitize_text_field($data['customer_id'] ?? ''),
            'full_name'                  => sanitize_text_field($data['full_name'] ?? ''),
            'email'                      => sanitize_email($data['email'] ?? ''),
            'phone'                      => sanitize_text_field($data['phone'] ?? ''),
            'pathway'                    => sanitize_text_field($data['pathway'] ?? 'Presentation Mastery'),
            'level'                      => max(0, min(5, absint($data['level'] ?? 1))),
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
            $wpdb->update($table, $record, array('id' => absint($data['id'])));
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

    public static function delete_meeting($id) {
        global $wpdb;
        $id = absint($id);
        $wpdb->delete(self::assignment_table(), array('meeting_id' => $id));
        return (bool) $wpdb->delete(self::meeting_table(), array('id' => $id));
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
            }
        }

        return $rows;
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
        $level = (int) $member['level'];

        if (strpos($role, 'general evaluator') !== false || strpos($role, 'general') !== false && strpos($role, 'evaluator') !== false) {
            return $level >= 4
                ? ['suitable' => true,  'reason' => 'L4+']
                : ['suitable' => false, 'reason' => 'Needs L4+'];
        }
        if (strpos($role, 'toastmaster') !== false || strpos($role, 'topics master') !== false) {
            return $level >= 3
                ? ['suitable' => true,  'reason' => 'L3+']
                : ['suitable' => false, 'reason' => 'Needs L3+'];
        }
        if (strpos($role, 'grammarian') !== false) {
            return $level >= 2
                ? ['suitable' => true,  'reason' => 'L2+']
                : ['suitable' => false, 'reason' => 'Needs L2+'];
        }

        // L1 ordering: Ice Breaker (Speaker at L1) only after Table Topics Speaker at L1
        if ($level <= 1 && preg_match('/^speaker(\s+\d+)?$/i', trim($role_name))) {
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

        // Closed: member has levelled up past L1 in TI
        if ($level >= 2) {
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
            "SELECT m.id as meeting_id, m.meeting_date, m.theme,
                    a.id as assignment_id, a.role_name,
                    (SELECT COUNT(*) FROM " . self::request_table() . " r WHERE r.assignment_id = a.id) as current_requests
             FROM {$meetings} m
             JOIN {$assignments} a ON m.id = a.meeting_id
             WHERE m.meeting_date >= %s
               AND (m.requests_close_at IS NULL OR m.requests_close_at >= %s)
               AND (a.member_id IS NULL OR a.member_id = 0 OR a.member_id = '')
             ORDER BY m.meeting_date ASC LIMIT 50",
            $today,
            $now
        ), ARRAY_A);

        $member       = self::current_member();
        $member_level = $member ? (int) $member['level'] : 1;
        $member_id    = $member ? (int) $member['id']    : 0;

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
            $slot['cooloff'] = $cooloff_info[$base_role] ?? null;
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
            return strpos(strtolower($s['role_name']), 'presiding officer') === false;
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

                // Hard level gate (skip unsuitable entirely)
                $role_lower = strtolower($base_role);
                $gate_level = 1;
                if (strpos($role_lower, 'general evaluator') !== false) {
                    $gate_level = 4;
                } elseif (strpos($role_lower, 'toastmaster') !== false || strpos($role_lower, 'topics master') !== false) {
                    $gate_level = 3;
                } elseif (strpos($role_lower, 'grammarian') !== false) {
                    $gate_level = 2;
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

                    // Cooloff check
                    if (isset($cooloff_map[$m_id][$base_role])) {
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
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($table, $record);
            $id = (int) $wpdb->insert_id;

            $selected_roles = $data['roles'] ?? [];
            $agenda = [];

            if (in_array('Sergeant at Arms', $selected_roles)) {
                $agenda[] = ['role' => 'Sergeant at Arms', 'note' => 'Starts meeting', 'dur' => 2];
            }
            if (in_array('Presiding Officer', $selected_roles)) {
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Address and guests', 'dur' => 5];
            }
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Intro of theme', 'dur' => 5];
            }
            $intro_roles = ['Grammarian', 'Timer', 'Ah-Counter', 'General Evaluator'];
            foreach ($intro_roles as $r) {
                if (in_array($r, $selected_roles)) {
                    $agenda[] = ['role' => $r, 'note' => 'Intro', 'dur' => 2];
                }
            }
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Intro segments', 'dur' => 3];
            }

            // Table Topics section
            if (in_array('Table Topics Master', $selected_roles)) {
                $agenda[] = ['role' => 'Table Topics Master', 'note' => 'Runs Table Topics', 'dur' => 15];
                $agenda[] = ['role' => 'Table Topics Speaker', 'note' => 'Table Topics answer', 'dur' => 2];
            }

            $speech_slots = absint($data['speech_slots'] ?? 0);
            for ($i = 1; $i <= $speech_slots; $i++) {
                $agenda[] = ['role' => "Evaluator $i", 'note' => 'Intro speaker', 'dur' => 1];
                $agenda[] = ['role' => "Speaker $i",   'note' => 'Speech',        'dur' => 8];
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Rate the speaker', 'dur' => 1];
            }

            $agenda[] = ['role' => 'Break', 'note' => 'Networking', 'dur' => 5];

            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Discuss theme', 'dur' => 3];
            }
            for ($i = 1; $i <= $speech_slots; $i++) {
                $agenda[] = ['role' => "Evaluator $i", 'note' => 'Evaluation', 'dur' => 3];
            }
            if (in_array('Timer', $selected_roles))    $agenda[] = ['role' => 'Timer',              'note' => 'Report',       'dur' => 2];
            if (in_array('Ah-Counter', $selected_roles)) $agenda[] = ['role' => 'Ah-Counter',        'note' => 'Report',       'dur' => 3];
            if (in_array('Grammarian', $selected_roles)) $agenda[] = ['role' => 'Grammarian',        'note' => 'Report',       'dur' => 3];
            if (in_array('General Evaluator', $selected_roles)) $agenda[] = ['role' => 'General Evaluator', 'note' => 'Final Report', 'dur' => 5];
            if (in_array('Toastmaster of the Day', $selected_roles)) $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Role Player voting', 'dur' => 1];
            if (in_array('Presiding Officer', $selected_roles)) $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Closing address', 'dur' => 4];

            $order = 10;
            foreach ($agenda as $item) {
                self::save_assignment([
                    'meeting_id' => $id,
                    'role_name'  => sanitize_text_field($item['role'] . ($item['note'] ? " ({$item['note']})" : "")),
                    'duration'   => $item['dur'],
                    'status'     => 'Planned',
                    'sort_order' => $order,
                ]);
                $order += 10;
            }
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
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

        $record['updated_at'] = $now;

        if (!empty($data['id'])) {
            $old = $wpdb->get_row($wpdb->prepare(
                "SELECT status, member_id, role_name, meeting_id, presentation_series FROM {$table} WHERE id = %d",
                $data['id']
            ), ARRAY_A);

            $new_status    = $data['status'] ?? ($old['status'] ?? '');
            $is_final      = in_array($new_status, ['Confirmed', 'Completed']);
            $was_not_final = $old && !in_array($old['status'], ['Confirmed', 'Completed']);

            if ($was_not_final && $is_final && !empty($data['member_id'])) {
                self::notify_assignment_status(absint($data['id']), absint($data['member_id']));

                $role_name   = $old['role_name'] ?? ($record['role_name'] ?? '');
                $meeting_id  = $old['meeting_id'] ?? ($record['meeting_id'] ?? 0);
                $meeting_date = $wpdb->get_var($wpdb->prepare(
                    "SELECT meeting_date FROM " . self::meeting_table() . " WHERE id = %d",
                    $meeting_id
                ));
                $series = $data['presentation_series'] ?? $old['presentation_series'] ?? null;

                $wpdb->insert(self::participation_history_table(), [
                    'member_id'           => absint($data['member_id']),
                    'meeting_id'          => absint($meeting_id),
                    'assignment_id'       => absint($data['id']),
                    'role_name'           => self::get_base_role_name($role_name),
                    'meeting_date'        => $meeting_date,
                    'level_at_completion' => (int) self::get_member(absint($data['member_id']))['level'],
                    'presentation_series' => $series ? sanitize_text_field($series) : null,
                    'created_at'          => $now,
                ]);
            }

            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            $saved = array('id' => absint($data['id'])) + $record;

            $meeting_id_for_singular = $record['meeting_id'] ?? ($old['meeting_id'] ?? 0);
            if ($meeting_id_for_singular) {
                $full_role = $wpdb->get_var($wpdb->prepare("SELECT role_name FROM {$table} WHERE id = %d", $data['id']));
                $base      = self::get_base_role_name($full_role);

                if (self::is_singular_role($base)) {
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
        $today = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, m.meeting_date, m.theme, a.role_name, a.member_id as assigned_id, a.status as assignment_status
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND m.meeting_date >= %s
             ORDER BY m.meeting_date ASC, r.priority ASC",
            $member_id, $today
        ), ARRAY_A);
    }

    public static function get_all_pending_requests() {
        global $wpdb;
        $requests   = self::request_table();
        $meetings   = self::meeting_table();
        $assignments = self::assignment_table();
        $members    = self::member_table();
        $today = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, m.meeting_date, m.theme, a.role_name, mem.full_name as member_name
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             JOIN {$members} mem ON r.member_id = mem.id
             WHERE m.meeting_date >= %s
             ORDER BY m.meeting_date ASC, r.priority ASC",
            $today
        ), ARRAY_A);
    }

    public static function get_member_request_history($member_id) {
        global $wpdb;
        $requests   = self::request_table();
        $meetings   = self::meeting_table();
        $assignments = self::assignment_table();
        $today = current_time('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, m.meeting_date, m.theme, a.role_name, a.member_id as assigned_id, a.status as assignment_status
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND m.meeting_date < %s
             ORDER BY m.meeting_date DESC, r.priority ASC",
            $member_id, $today
        ), ARRAY_A);
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
}
