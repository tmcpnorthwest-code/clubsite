<?php
/**
 * Creates and tears down isolated test data.
 *
 * All test members are flagged with a `[TEST]` prefix in full_name so they
 * are easy to identify and bulk-delete from the DB if cleanup fails.
 */
class Fixtures {
    /** IDs created during this run — used for cleanup. */
    public array $member_ids = [];
    public array $meeting_ids = [];

    private string $members_table;
    private string $meetings_table;
    private string $assignments_table;
    private string $requests_table;

    public function __construct() {
        $this->members_table     = TMP_Repository::member_table();
        $this->meetings_table    = TMP_Repository::meeting_table();
        $this->assignments_table = TMP_Repository::assignment_table();
        $this->requests_table    = TMP_Repository::request_table();
    }

    /**
     * Insert a test member directly into tmp_members.
     *
     * @param string $name      Display name (will be prefixed [TEST])
     * @param int    $level     Pathways level 0–5
     * @param string $status    Active|Inactive
     * @param string $paid_until YYYY-MM-DD — far future = paid up
     * @return int  member ID
     */
    public function member(string $name, int $level = 1, string $state = 'Active', string $paid_until = '2030-12-31'): int {
        global $wpdb;
        $ok = $wpdb->insert($this->members_table, [
            'full_name'  => "[TEST] {$name}",
            'email'      => strtolower(str_replace(' ', '.', $name)) . '.test@example.com',
            'level'      => $level,
            'state'      => $state,   // column is `state`, not `status`
            'pathway'    => 'Dynamic Leadership',
            'paid_until' => $paid_until,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        if (!$ok) {
            throw new RuntimeException("Fixture: member insert failed for {$name} — " . $wpdb->last_error);
        }
        $id = (int) $wpdb->insert_id;
        $this->member_ids[] = $id;
        return $id;
    }

    /**
     * Create a meeting via TMP_Repository::save_meeting() so role slots are
     * generated the same way the VPE form does it.
     *
     * @param string   $date        YYYY-MM-DD
     * @param string[] $roles       Standard role names to include
     * @param int      $speech_slots Number of speaker/evaluator pairs
     * @param string   $deadline    datetime-local string (or empty = open)
     * @return int  meeting ID
     */
    public function meeting(
        string $date,
        array  $roles        = ['Toastmaster of the Day', 'General Evaluator', 'Timer', 'Ah-Counter'],
        int    $speech_slots = 2,
        string $deadline     = ''
    ): int {
        $result = TMP_Repository::save_meeting([
            'meeting_date'      => $date,
            'theme'             => '[TEST] Workflow Test Meeting',
            'start_time'        => '18:30',
            'total_duration'    => 90,
            'requests_close_at' => $deadline,
            'roles'             => $roles,
            'speech_slots'      => $speech_slots,
        ]);

        if (is_wp_error($result)) {
            throw new RuntimeException('Fixture: save_meeting failed — ' . $result->get_error_message());
        }
        if (!is_array($result) || empty($result['id'])) {
            throw new RuntimeException('Fixture: save_meeting returned unexpected value: ' . json_encode($result));
        }

        // save_meeting() returns the full meeting row, not just the ID
        $id = (int) $result['id'];
        $this->meeting_ids[] = $id;
        return $id;
    }

    /**
     * Return all assignment rows for a meeting keyed by role_name.
     */
    public function get_slots(int $meeting_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->assignments_table} WHERE meeting_id = %d ORDER BY sort_order ASC",
            $meeting_id
        ), ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Find the first open (no member assigned) slot for a given role base name.
     */
    /**
     * Find the first open slot whose role_name starts with $role_prefix.
     * Anchored-start LIKE avoids false matches on slots that mention the
     * role in a parenthetical note (e.g. "TMOD (Introduces Speaker 1)").
     */
    public function slot_id(int $meeting_id, string $role_prefix): ?int {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->assignments_table}
              WHERE meeting_id = %d
                AND role_name LIKE %s
                AND (member_id IS NULL OR member_id = 0)
              ORDER BY sort_order ASC
              LIMIT 1",
            $meeting_id,
            $wpdb->esc_like($role_prefix) . '%'   // anchored to start, no leading wildcard
        ));
        return $id ? (int) $id : null;
    }

    /**
     * Submit role request(s) for a member.
     *
     * @param int   $member_id
     * @param int   $meeting_id
     * @param int[] $slot_ids    Ordered priority list of assignment IDs (P1 first)
     * @return mixed WP_Error or the saved request data
     */
    public function request(int $member_id, int $meeting_id, array $slot_ids): mixed {
        return TMP_Repository::save_requests([
            'member_id'  => $member_id,
            'meeting_id' => $meeting_id,
            'priorities' => $slot_ids,
        ]);
    }

    /**
     * Fetch one request row by member + meeting + assignment.
     */
    public function get_request(int $member_id, int $meeting_id, int $slot_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->requests_table}
              WHERE member_id = %d AND meeting_id = %d AND assignment_id = %d
              LIMIT 1",
            $member_id, $meeting_id, $slot_id
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Count requests in a given status for a meeting.
     */
    public function count_requests(int $meeting_id, string $status): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->requests_table}
              WHERE meeting_id = %d AND status = %s",
            $meeting_id, $status
        ));
    }

    /**
     * Fetch the assignment row for a slot.
     */
    public function get_slot(int $slot_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->assignments_table} WHERE id = %d", $slot_id
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Remove all data created during this test run (reverse dependency order).
     */
    public function teardown(): void {
        global $wpdb;

        if ($this->meeting_ids) {
            $ids = implode(',', array_map('intval', $this->meeting_ids));
            $wpdb->query("DELETE FROM {$this->requests_table}    WHERE meeting_id IN ({$ids})");
            $wpdb->query("DELETE FROM {$this->assignments_table} WHERE meeting_id IN ({$ids})");
            $wpdb->query("DELETE FROM {$this->meetings_table}    WHERE id         IN ({$ids})");
        }

        if ($this->member_ids) {
            $ids = implode(',', array_map('intval', $this->member_ids));
            $wpdb->query("DELETE FROM {$this->members_table} WHERE id IN ({$ids})");
        }
    }
}
