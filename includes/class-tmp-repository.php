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

    public static function request_table() {
        global $wpdb;
        return $wpdb->prefix . 'tmp_member_requests';
    }

    public static function get_standard_roles() {
        return [
            'Sergeant at Arms'       => 'SAA',
            'Presiding Officer'      => 'Presiding Officer',
            'Toastmaster of the Day' => 'TMOD',
            'Table Topics Master'    => 'Topics Master',
            'General Evaluator'      => 'GE',
            'Timer'                 => 'Timer',
            'Ah Counter'            => 'Ah Counter',
            'Grammarian'            => 'Grammarian',
        ];
    }

    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
            // Fallback: Write directly to the plugin directory if wp-content/debug.log is blocked
            $log_file = TMP_PLUGIN_DIR . 'debug.log';
            $timestamp = gmdate('Y-m-d H:i:s');
            @file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
        }
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

    /**
     * Extracts the base role name by removing parenthetical notes.
     * e.g., "Toastmaster of the Day (Intro)" becomes "Toastmaster of the Day"
     */
    public static function get_base_role_name($role_name) {
        return trim(preg_replace('/\s*\(.*?\)\s*/', '', $role_name));
    }

    /**
     * Checks if a role is singular (e.g., only one person performs all TMOD segments).
     */
    public static function is_singular_role($base_role) {
        if (array_key_exists($base_role, self::get_standard_roles())) {
            return true;
        }

        // Also treat numbered slots (Speaker 1, Evaluator 2, etc.) as singular entities
        return (bool) preg_match('/^(Speaker|Evaluator)\s+\d+$/i', $base_role);
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

    public static function delete_meeting($id) {
        global $wpdb;
        $id = absint($id);
        $wpdb->delete(self::assignment_table(), array('meeting_id' => $id));
        return (bool) $wpdb->delete(self::meeting_table(), array('id' => $id));
    }

    public static function meetings() {
        global $wpdb;
        $meetings = self::meeting_table();
        $assignments = self::assignment_table();
        $members = self::member_table();
        $requests = self::request_table();

        $rows = $wpdb->get_results("SELECT * FROM {$meetings} ORDER BY meeting_date DESC, id DESC LIMIT 25", ARRAY_A);
        if (!is_array($rows)) {
            return array();
        }

        foreach ($rows as &$meeting) {
            $meeting['assignments'] = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, m.full_name AS member_name,
                        (SELECT COUNT(*) FROM {$requests} r WHERE r.assignment_id = a.id) as request_count
                 FROM {$assignments} a
                 LEFT JOIN {$members} m ON m.id = a.member_id
                 WHERE a.meeting_id = %d
                 ORDER BY a.sort_order ASC, a.id ASC",
                $meeting['id']
            ), ARRAY_A) ?: array();

            foreach ($meeting['assignments'] as &$assignment) {
                if (!empty($assignment['member_id'])) {
                    $assignment['suitability'] = self::check_suitability($assignment['role_name'], $assignment['member_id']);
                }
            }
        }

        return $rows;
    }

    public static function check_suitability($role_name, $member_id) {
        $member = self::get_member($member_id);
        if (!$member) return ['suitable' => false, 'reason' => 'No member'];
        
        $role = strtolower($role_name);
        $level = (int)$member['level'];

        if (strpos($role, 'evaluator') !== false) {
            return $level >= 2 ? ['suitable' => true, 'reason' => 'L2+'] : ['suitable' => false, 'reason' => 'Needs L2+'];
        }
        if (strpos($role, 'toastmaster') !== false || strpos($role, 'topics') !== false || strpos($role, 'general') !== false || strpos($role, 'presiding') !== false) {
            return $level >= 3 ? ['suitable' => true, 'reason' => 'L3+'] : ['suitable' => false, 'reason' => 'Needs L3+'];
        }
        
        return ['suitable' => true, 'reason' => 'Suitable'];
    }

    public static function get_recommendations($member) {
        $level = (int) ($member['level'] ?? 1);
        $recs = array();

        if ($level === 1) {
            $recs[] = array('title' => 'Ice Breaker', 'type' => 'Speech', 'note' => 'Your first 4-6 minute speech.');
            $recs[] = array('title' => 'Evaluation & Feedback (Part 1)', 'type' => 'Speech', 'note' => 'Incorporate feedback from your first speech.');
        } else {
            $recs[] = array('title' => "Level {$level} Project Speech", 'type' => 'Speech', 'note' => 'Focus on your path-specific elective.');
            $recs[] = array('title' => 'Speech Evaluator', 'type' => 'Role', 'note' => 'Practice active listening and constructive feedback.');
        }

        $recs[] = array('title' => 'Timer or Grammarian', 'type' => 'Role', 'note' => 'Great for building confidence on stage.');
        return $recs;
    }

    public static function get_open_slots() {
        global $wpdb;
        $meetings = self::meeting_table();
        $assignments = self::assignment_table();
        $today = gmdate('Y-m-d');

        return $wpdb->get_results($wpdb->prepare("SELECT m.id as meeting_id, m.meeting_date, m.theme, a.id as assignment_id, a.role_name FROM {$meetings} m JOIN {$assignments} a ON m.id = a.meeting_id WHERE m.meeting_date >= %s AND a.member_id IS NULL ORDER BY m.meeting_date ASC LIMIT 50", $today), ARRAY_A);
    }

    public static function get_suggestions($meeting_id) {
        global $wpdb;
        $assignments_table = self::assignment_table();
        $members_table = self::member_table();

        $trace = [];
        $trace[] = "Starting suggestion engine for meeting ID: $meeting_id";
        
        $all_assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role_name, member_id FROM {$assignments_table} WHERE meeting_id = %d",
            $meeting_id
        ), ARRAY_A);

        $total_assignments = count($all_assignments);
        $trace[] = "Total roles defined for this meeting: $total_assignments";

        $singular_role_map = []; // base_role => member_id
        $assigned_ids = [];
        
        // Fetch prioritized member requests for this meeting
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT member_id, assignment_id, priority FROM " . self::request_table() . " WHERE meeting_id = %d ORDER BY priority ASC",
            $meeting_id
        ), ARRAY_A);

        foreach ($all_assignments as $asgn) {
            $m_id = (int)$asgn['member_id'];
            if ($m_id > 0) {
                $assigned_ids[] = $m_id;
                $base = self::get_base_role_name($asgn['role_name']);
                if (self::is_singular_role($base)) {
                    $singular_role_map[$base] = $m_id;
                }
            }
        }
        $assigned_ids = array_unique($assigned_ids);

        $slots = $wpdb->get_results($wpdb->prepare(
            "SELECT id, role_name FROM {$assignments_table} WHERE meeting_id = %d AND (member_id IS NULL OR member_id = 0 OR member_id = '')",
            $meeting_id
        ), ARRAY_A);

        $open_count = count($slots);
        self::log("TMP Debug: Found $open_count open slots for meeting ID: $meeting_id");
        $trace[] = "Found $open_count unassigned roles to fill.";

        if ($open_count === 0) {
            if ($total_assignments === 0) {
                $trace[] = "Hint: Add roles to this meeting first using the 'Save Assignment' form. Leave the 'Member' dropdown as 'Unassigned' to create an open slot.";
            } else {
                $trace[] = "Hint: All roles in this meeting already have members assigned.";
            }
            return ['suggestions' => [], 'trace' => $trace];
        }

        $members = self::members();
        $trace[] = "Found " . count($members) . " total members in database.";
        if (empty($members)) $trace[] = "Warning: No members found in database. Add members first.";

        $suggestions = [];

        // Phase 1: Try to satisfy member requests by priority (1 through 3)
        for ($p = 1; $p <= 3; $p++) {
            foreach ($slots as $slot_index => $slot) {
                // Skip if already suggested
                if (isset($suggestions[$slot['id']])) continue;

                $slot_id = (int)$slot['id'];
                $possible_requests = array_filter($requests, fn($r) => (int)$r['assignment_id'] === $slot_id && (int)$r['priority'] === $p);

                foreach ($possible_requests as $req) {
                    $m_id = (int)$req['member_id'];
                    if (in_array($m_id, $assigned_ids)) continue;

                    $m_data = array_values(array_filter($members, fn($m) => (int)$m['id'] === $m_id))[0] ?? null;
                    if ($m_data) {
                        $suggestions[$slot_id] = [
                            'id' => $slot_id,
                            'role_name' => $slot['role_name'],
                            'suggested_member_id' => $m_id, 
                            'suggested_member_name' => $m_data['full_name']
                        ];
                        $assigned_ids[] = $m_id;
                        $trace[] = "Satisfied Priority $p Request: {$m_data['full_name']} for {$slot['role_name']}";
                        
                        $base = self::get_base_role_name($slot['role_name']);
                        if (self::is_singular_role($base)) $singular_role_map[$base] = $m_id;
                        break; 
                    }
                }
            }
        }

        // Phase 2: Use intelligent suitability for remaining empty slots
        foreach ($slots as $slot) {
            if (isset($suggestions[$slot['id']])) continue;

            $role_label = $slot['role_name'];
            $base_role = self::get_base_role_name($role_label);
            $role_lower = strtolower($base_role);
            $found_match = false;

            if (self::is_singular_role($base_role) && isset($singular_role_map[$base_role])) {
                $m_id = $singular_role_map[$base_role];
                $m_data = array_values(array_filter($members, function($m) use ($m_id) {
                    return (int)$m['id'] === $m_id;
                }))[0] ?? null;
                if ($m_data) {
                    $suggestions[$slot['id']] = array_merge($slot, [
                        'suggested_member_id' => $m_id, 
                        'suggested_member_name' => $m_data['full_name']
                    ]);
                    $trace[] = "Re-using {$m_data['full_name']} for singular role: $role_label";
                    continue;
                }
            }

            foreach ($members as $member) {
                $member_id = (int)$member['id'];
                if (in_array($member_id, $assigned_ids)) continue;

                $match = false;
                $level = (int)$member['level'];
                $state = $member['state'] ?? 'Active';

                if (strpos($role_lower, 'speaker') !== false || strpos($role_lower, 'ice breaker') !== false) {
                    // Speakers: Prioritize members needing a speech slot or at Level 1
                    $match = true;
                    $priority = ($state === 'Needs speech slot' || $level === 1) ? "Priority" : "General";
                    $trace[] = "Matched Speaker: " . $member['full_name'] . " ($priority match)";
                } elseif (strpos($role_lower, 'evaluator') !== false) {
                    if ($level >= 2) {
                        $match = true;
                        $trace[] = "Matched Evaluator: " . $member['full_name'] . " (Level $level >= 2)";
                    }
                } elseif (strpos($role_lower, 'toastmaster') !== false || strpos($role_lower, 'topics') !== false || strpos($role_lower, 'general') !== false || strpos($role_lower, 'presiding') !== false) {
                    if ($level >= 3) {
                        $match = true;
                        $trace[] = "Matched Leadership ($role_label): " . $member['full_name'] . " (Level $level >= 3)";
                    }
                } else {
                    $match = true;
                    $trace[] = "Matched " . $slot['role_name'] . ": " . $member['full_name'];
                }

                if ($match) {
                    $suggestions[$slot['id']] = array_merge($slot, ['suggested_member_id' => $member['id'], 'suggested_member_name' => $member['full_name']]);
                    $assigned_ids[] = $member_id;
                    $found_match = true;
                    
                    if (self::is_singular_role($base_role)) {
                        $singular_role_map[$base_role] = $member_id;
                    }
                    break; 
                }
            }

            if (!$found_match) {
                $trace[] = "Could not find a suitable unassigned member for: " . $slot['role_name'];
            }
        }
        return ['suggestions' => array_values($suggestions), 'trace' => $trace];
    }

    public static function save_meeting($data) {
        global $wpdb;
        $table = self::meeting_table();
        $now = current_time('mysql');

        $record = array(
            'meeting_date' => sanitize_text_field($data['meeting_date'] ?? ''),
            'start_time' => sanitize_text_field($data['start_time'] ?? '18:30'),
            'total_duration' => absint($data['total_duration'] ?? 120),
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

            // Auto-generate assignments for new meetings
            $selected_roles = $data['roles'] ?? [];
            $agenda = [];
            
            // 1. SAA
            if (in_array('Sergeant at Arms', $selected_roles)) {
                $agenda[] = ['role' => 'Sergeant at Arms', 'note' => 'Starts meeting', 'dur' => 2];
            }
            // 2. Presiding Officer Intro
            if (in_array('Presiding Officer', $selected_roles)) {
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Address and guests', 'dur' => 5];
            }
            // 3. TMOD Theme Intro
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Intro of theme', 'dur' => 5];
            }
            // 4. Role Intros
            $intro_roles = ['Grammarian', 'Timer', 'Ah Counter', 'General Evaluator'];
            foreach ($intro_roles as $r) {
                if (in_array($r, $selected_roles)) $agenda[] = ['role' => $r, 'note' => 'Intro', 'dur' => 2];
            }
            // 5. TMOD Segments
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Intro segments', 'dur' => 3];
            }
            // 6. Speaker/Evaluator Sequence
            $slots = absint($data['speech_slots'] ?? 0);
            for ($i = 1; $i <= $slots; $i++) {
                $agenda[] = ['role' => "Evaluator $i", 'note' => 'Intro speaker', 'dur' => 1];
                $agenda[] = ['role' => "Speaker $i", 'note' => 'Speech', 'dur' => 7];
            }
            // 7. Break
            $agenda[] = ['role' => 'Break', 'note' => 'Networking', 'dur' => 5];
            // 8. TMOD Discuss theme
            if (in_array('Toastmaster of the Day', $selected_roles)) {
                $agenda[] = ['role' => 'Toastmaster of the Day', 'note' => 'Discuss theme', 'dur' => 3];
            }
            // 9. Evaluations
            for ($i = 1; $i <= $slots; $i++) {
                $agenda[] = ['role' => "Evaluator $i", 'note' => 'Evaluation', 'dur' => 2];
            }
            // 10. Reports
            if (in_array('Timer', $selected_roles)) $agenda[] = ['role' => 'Timer', 'note' => 'Report', 'dur' => 2];
            if (in_array('Grammarian', $selected_roles)) $agenda[] = ['role' => 'Grammarian', 'note' => 'Report', 'dur' => 3];
            if (in_array('Ah Counter', $selected_roles)) $agenda[] = ['role' => 'Ah Counter', 'note' => 'Report', 'dur' => 3];
            if (in_array('General Evaluator', $selected_roles)) $agenda[] = ['role' => 'General Evaluator', 'note' => 'Final Report', 'dur' => 5];
            // 11. Closing
            if (in_array('Presiding Officer', $selected_roles)) {
                $agenda[] = ['role' => 'Presiding Officer', 'note' => 'Closing address', 'dur' => 4];
            }

            $order = 10;
            foreach ($agenda as $item) {
                self::save_assignment([
                    'meeting_id' => $id,
                    'role_name' => sanitize_text_field($item['role'] . ($item['note'] ? " (" . $item['note'] . ")" : "")),
                    'duration' => $item['dur'],
                    'status' => 'Planned',
                    'sort_order' => $order
                ]);
                $order += 10;
            }
        }

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    public static function save_assignment($data) {
        global $wpdb;
        $table = self::assignment_table();
        $now = current_time('mysql');

        $record = array();
        if (isset($data['meeting_id'])) $record['meeting_id'] = absint($data['meeting_id']);
        if (isset($data['member_id'])) $record['member_id'] = !empty($data['member_id']) ? absint($data['member_id']) : null;
        if (isset($data['role_name'])) $record['role_name'] = sanitize_text_field($data['role_name']);
        if (isset($data['speech_title'])) $record['speech_title'] = sanitize_text_field($data['speech_title']);
        if (isset($data['duration'])) $record['duration'] = absint($data['duration']);
        if (isset($data['status'])) $record['status'] = sanitize_text_field($data['status']);
        if (isset($data['sort_order'])) $record['sort_order'] = absint($data['sort_order']);
        
        $record['updated_at'] = $now;

        if (!empty($data['id'])) {
            $old_record = $wpdb->get_row($wpdb->prepare("SELECT status, member_id FROM {$table} WHERE id = %d", $data['id']), ARRAY_A);
            if ($old_record && ($old_record['status'] !== 'Confirmed') && (isset($data['status']) && $data['status'] === 'Confirmed') && !empty($data['member_id'])) {
                self::notify_assignment_status(absint($data['id']), absint($data['member_id']));
            }

            $wpdb->update($table, $record, array('id' => absint($data['id'])));
            $saved = array('id' => absint($data['id'])) + $record;

            // Sync logic: Propagate assignment to all segments of a singular role (TMOD, Evaluator 1, etc)
            if (!empty($record['meeting_id'])) {
                $full_role = $wpdb->get_var($wpdb->prepare("SELECT role_name FROM {$table} WHERE id = %d", $data['id']));
                $base = self::get_base_role_name($full_role);
                
                if (self::is_singular_role($base)) {
                    $new_member_id = !empty($record['member_id']) ? absint($record['member_id']) : 0;
                    $new_status = sanitize_text_field($record['status'] ?? 'Confirmed');

                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET member_id = IF(%d = 0, NULL, %d), status = %s WHERE meeting_id = %d AND (role_name = %s OR role_name LIKE %s)",
                        $new_member_id,
                        $new_member_id,
                        $new_status,
                        $record['meeting_id'],
                        $base,
                        $wpdb->esc_like($base) . ' (%'
                    ));
                }
            }
            return $saved;
        }

        if (empty($record['meeting_id']) || empty($record['role_name'])) {
            return new WP_Error('tmp_invalid_assignment', 'Meeting and role are required for new assignments.', array('status' => 400));
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return array('id' => (int) $wpdb->insert_id) + $record;
    }

    public static function delete_assignment($id) {
        global $wpdb;
        return (bool) $wpdb->delete(self::assignment_table(), array('id' => absint($id)));
    }

    public static function save_requests($data) {
        global $wpdb;
        $table = self::request_table();
        $meeting_id = absint($data['meeting_id'] ?? 0);
        $member_id = absint($data['member_id'] ?? 0);
        $priorities = $data['priorities'] ?? [];

        if (!$meeting_id || !$member_id) return false;

        // Clear previous requests for this meeting by this member
        $wpdb->delete($table, ['meeting_id' => $meeting_id, 'member_id' => $member_id]);

        foreach ($priorities as $index => $assignment_id) {
            if (empty($assignment_id)) continue;
            $wpdb->insert($table, [
                'meeting_id' => $meeting_id, 'member_id' => $member_id, 'assignment_id' => absint($assignment_id), 'priority' => $index + 1, 'created_at' => current_time('mysql')
            ]);
        }

        self::notify_vpe_of_request($meeting_id, $member_id, $priorities);

        return true;
    }

    /**
     * Notifies the VP Education when a member submits prioritized role requests.
     */
    private static function notify_vpe_of_request($meeting_id, $member_id, $priorities) {
        // Get users with the VPE role
        $vpes = get_users(['role' => 'tm_vp_education']);
        $emails = !empty($vpes) ? wp_list_pluck($vpes, 'user_email') : [get_option('admin_email')];

        $member = self::get_member($member_id);
        global $wpdb;
        $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::meeting_table() . " WHERE id = %d", $meeting_id), ARRAY_A);

        if (!$member || !$meeting) {
            return;
        }

        $role_details = [];
        $assignments_table = self::assignment_table();

        foreach ($priorities as $index => $asgn_id) {
            if (empty($asgn_id)) {
                continue;
            }
            $role_name = $wpdb->get_var($wpdb->prepare(
                "SELECT role_name FROM {$assignments_table} WHERE id = %d",
                $asgn_id
            ));
            if ($role_name) {
                $role_details[] = sprintf("Priority %d: %s", $index + 1, self::get_base_role_name($role_name));
            }
        }

        if (empty($role_details)) {
            return;
        }

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
        return (bool) $wpdb->delete(self::request_table(), array(
            'id' => absint($id),
            'member_id' => absint($member_id)
        ));
    }

    /**
     * Retrieves active role requests for a specific member.
     */
    public static function get_member_requests($member_id) {
        global $wpdb;
        $requests = self::request_table();
        $meetings = self::meeting_table();
        $assignments = self::assignment_table();
        $today = gmdate('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, m.meeting_date, m.theme, a.role_name, a.member_id as assigned_id, a.status as assignment_status
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND m.meeting_date >= %s
             ORDER BY m.meeting_date ASC, r.priority ASC",
            $member_id,
            $today
        ), ARRAY_A);
    }

    /**
     * Retrieves historical role requests for a specific member.
     */
    public static function get_member_request_history($member_id) {
        global $wpdb;
        $requests = self::request_table();
        $meetings = self::meeting_table();
        $assignments = self::assignment_table();
        $today = gmdate('Y-m-d');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.priority, m.meeting_date, m.theme, a.role_name, a.member_id as assigned_id, a.status as assignment_status
             FROM {$requests} r
             JOIN {$meetings} m ON r.meeting_id = m.id
             JOIN {$assignments} a ON r.assignment_id = a.id
             WHERE r.member_id = %d AND m.meeting_date < %s
             ORDER BY m.meeting_date DESC, r.priority ASC",
            $member_id,
            $today
        ), ARRAY_A);
    }

    /**
     * Retrieves all member requests for a specific assignment (role slot).
     */
    public static function get_conflicting_requests($assignment_id) {
        global $wpdb;
        $requests_table = self::request_table();
        $members_table = self::member_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.priority, r.member_id, m.full_name AS member_name, m.email
             FROM {$requests_table} r
             JOIN {$members_table} m ON r.member_id = m.id
             WHERE r.assignment_id = %d
             ORDER BY r.priority ASC, m.full_name ASC",
            $assignment_id
        ), ARRAY_A);
    }

    /**
     * Sends email notifications when a role request is approved or filled by someone else.
     */
    private static function notify_assignment_status($assignment_id, $member_id) {
        global $wpdb;
        $member = self::get_member($member_id);
        $assignment = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::assignment_table() . " WHERE id = %d", $assignment_id), ARRAY_A);
        
        if (!$member || !$assignment) return;

        $meeting = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::meeting_table() . " WHERE id = %d", $assignment['meeting_id']), ARRAY_A);
        if (!$meeting) return;

        $base_role = self::get_base_role_name($assignment['role_name']);

        // 1. Notify Approved Member
        $subject = "[Approved] Your Role: {$base_role} for " . $meeting['meeting_date'];
        $message = sprintf(
            "Hi %s,\n\nYour request for the role of '%s' for the meeting on %s (%s) has been approved!\n\nYou can view the updated agenda and your level progress on your dashboard.\n\nRegards,\nVP Education",
            $member['full_name'],
            $base_role,
            $meeting['meeting_date'],
            $meeting['theme']
        );
        wp_mail($member['email'], $subject, $message);

        // 2. Notify Denied Members (those who requested this specific assignment but didn't get it)
        $requests = $wpdb->get_results($wpdb->prepare(
            "SELECT r.member_id, m.full_name, m.email 
             FROM " . self::request_table() . " r 
             JOIN " . self::member_table() . " m ON r.member_id = m.id 
             WHERE r.assignment_id = %d AND r.member_id != %d",
            $assignment_id,
            $member_id
        ), ARRAY_A);

        foreach ($requests as $req) {
            $subject = "[Update] Role Request for " . $meeting['meeting_date'];
            $message = sprintf(
                "Hi %s,\n\nThank you for requesting the '%s' role for the meeting on %s. This slot has now been filled by another member.\n\nPlease check the Available Meeting Slots on your dashboard to request a different role!\n\nRegards,\nVP Education",
                $req['full_name'],
                $base_role,
                $meeting['meeting_date']
            );
            wp_mail($req['email'], $subject, $message);
        }
    }
}
