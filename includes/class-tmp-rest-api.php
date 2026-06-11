<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_REST_API {
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    public static function routes() {
        // ── Member (self) ──────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'me'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('toastmasters/v1', '/me/requests', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_requests'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/requests/history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_request_history'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/participation-history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_participation_history'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/recommendations', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_recommendations'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/level-gaps', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_level_gaps'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/pending-requests', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_pending_requests'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // ── Role requests ──────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/requests', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'save_requests'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/requests/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_request'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/requests/approve-and-cascade-reject', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'approve_and_cascade_reject'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Open slots ─────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/open-slots', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_open_slots'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // ── Mentor ─────────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/mentor/mentees', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response(TMP_Repository::get_mentees_for_current_user()),
            'permission_callback' => 'is_user_logged_in',
        ]);

        // ── Members ────────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/members', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'members'],
                'permission_callback' => [__CLASS__, 'can_view_all_members'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_member'],
                'permission_callback' => [__CLASS__, 'can_manage_members'],
            ],
        ]);

        register_rest_route('toastmasters/v1', '/members/eligible-mentors', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response(TMP_Repository::get_eligible_mentors()),
            'permission_callback' => [__CLASS__, 'can_manage_members'],
        ]);

        register_rest_route('toastmasters/v1', '/members/due-for-roles', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response(TMP_Repository::get_members_due_for_roles()),
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_member'],
            'permission_callback' => [__CLASS__, 'can_manage_members'],
        ]);

        register_rest_route('toastmasters/v1', '/members/import', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'import_members'],
            'permission_callback' => [__CLASS__, 'can_manage_members'],
        ]);

        // ── Meetings ───────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'meetings'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_meeting'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        register_rest_route('toastmasters/v1', '/meetings/requests', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_all_requests'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_meeting'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/suggestions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_meeting_suggestions'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Assignments ────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/assignments', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'save_assignment'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/assignments/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_assignment'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/assignments/(?P<id>\d+)/conflicts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_assignment_conflicts'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/assignments/approve-all-recommended', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'approve_all_recommended_requests'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/assignments/approve-conflict-resolved', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'approve_conflict_resolved'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Club ───────────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/club/kpis', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response(TMP_Repository::get_club_kpis()),
            'permission_callback' => [__CLASS__, 'can_view_all_members'],
        ]);

        // ── Role gate settings ─────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/settings/role-gates', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_role_gates'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_role_gates'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        // ── Requirement overrides ──────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/me/requirement-override', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_requirement_override'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/requirement-override/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'delete_requirement_override'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)/requirement-overrides', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_member_requirement_overrides'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Enrolment ──────────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/enrol', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'enrol'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── Permission helpers ─────────────────────────────────────────────────────

    public static function can_manage_members() {
        return current_user_can('tmp_manage_members');
    }

    public static function can_view_all_members() {
        return current_user_can('tmp_view_all_members') || current_user_can('tmp_manage_members');
    }

    public static function can_manage_meetings() {
        return current_user_can('tmp_manage_meetings');
    }

    // ── Member (self) handlers ─────────────────────────────────────────────────

    public static function me() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_member_not_found', 'No Toastmasters member record is linked to this WordPress account email.', ['status' => 404]);
        }

        if (!empty($member['paid_until']) && strtotime($member['paid_until']) < time() && !$member['is_exempt_from_unpaid_block']) {
            return new WP_Error('tmp_unpaid_member', 'Your membership payment is overdue. Please contact the Club Admin to renew your membership or for an exemption.', ['status' => 403]);
        }

        $member['mentorship_stage'] = TMP_Repository::compute_mentorship_stage((int) $member['id'], $member);
        $member['next_action']      = TMP_Repository::compute_next_action((int) $member['id'], $member);

        return rest_ensure_response($member);
    }

    public static function get_my_requests() {
        $member = TMP_Repository::current_member();
        if (!$member) return [];
        return rest_ensure_response(TMP_Repository::get_member_requests($member['id']));
    }

    public static function get_my_request_history() {
        $member = TMP_Repository::current_member();
        if (!$member) return [];
        return rest_ensure_response(TMP_Repository::get_member_request_history($member['id']));
    }

    public static function get_my_participation_history() {
        $member = TMP_Repository::current_member();
        if (!$member) return [];
        return rest_ensure_response(TMP_Repository::get_member_participation_history($member['id']));
    }

    public static function get_my_recommendations() {
        $member = TMP_Repository::current_member();
        if (!$member) return [];
        return rest_ensure_response(TMP_Repository::get_recommendations($member));
    }

    public static function get_my_level_gaps() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_member_not_found', 'Member not found.', ['status' => 404]);
        }
        $level = (int) $member['level'];
        $gaps  = TMP_Repository::get_member_level_gaps($member['id'], $level);
        return rest_ensure_response(['level' => $level, 'gaps' => $gaps]);
    }

    public static function get_my_pending_requests() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_member_not_found', 'Member not found.', ['status' => 404]);
        }
        return rest_ensure_response(TMP_Repository::get_member_pending_requests($member['id']));
    }

    public static function get_open_slots() {
        return rest_ensure_response(TMP_Repository::get_open_slots());
    }

    // ── Role requests ──────────────────────────────────────────────────────────

    public static function save_requests(WP_REST_Request $request) {
        $result = TMP_Repository::save_requests($request->get_json_params());
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(['success' => $result]);
    }

    public static function delete_request(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_unauthorized', 'You must be a linked member to cancel requests.', ['status' => 401]);
        }
        return rest_ensure_response(['deleted' => TMP_Repository::delete_request((int) $request['id'], $member['id'])]);
    }

    public static function approve_and_cascade_reject(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $request_id = (int) ($params['request_id'] ?? 0);
        $member_id = (int) ($params['member_id'] ?? 0);
        $meeting_id = (int) ($params['meeting_id'] ?? 0);
        $role_name = sanitize_text_field($params['role_name'] ?? '');

        if (!$request_id || !$member_id || !$meeting_id || !$role_name) {
            return new WP_Error('tmp_invalid', 'Missing required parameters', ['status' => 400]);
        }

        $result = TMP_Repository::approve_request_and_cascade_reject($request_id, $member_id, $meeting_id, $role_name);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    // ── Members ────────────────────────────────────────────────────────────────

    public static function members() {
        return rest_ensure_response(TMP_Repository::members());
    }

    public static function save_member(WP_REST_Request $request) {
        $member = TMP_Repository::save_member($request->get_json_params());
        if (is_wp_error($member)) return $member;
        return rest_ensure_response($member);
    }

    public static function delete_member(WP_REST_Request $request) {
        return rest_ensure_response(['deleted' => TMP_Repository::delete_member((int) $request['id'])]);
    }

    public static function import_members(WP_REST_Request $request) {
        $files = $request->get_file_params();
        if (empty($files['file']['tmp_name'])) {
            return new WP_Error('tmp_missing_file', 'Upload a CSV file.', ['status' => 400]);
        }

        $default_password = (string) $request->get_param('default_password');
        if (strlen($default_password) < 8) {
            return new WP_Error('tmp_weak_password', 'Default password must be at least 8 characters.', ['status' => 400]);
        }

        $handle = fopen($files['file']['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('tmp_file_open_failed', 'Could not read the uploaded CSV.', ['status' => 400]);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('tmp_empty_csv', 'The CSV appears to be empty.', ['status' => 400]);
        }

        $indexes  = self::csv_indexes($header);
        $required = ['Customer ID', 'Name', 'Email'];
        foreach ($required as $column) {
            if (!isset($indexes[$column])) {
                fclose($handle);
                return new WP_Error('tmp_missing_column', "Missing required column: {$column}.", ['status' => 400]);
            }
        }

        $created_users = 0;
        $updated_users = 0;
        $imported      = 0;
        $skipped       = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row)) continue;

            $customer_id = self::csv_value($row, $indexes, 'Customer ID');
            $name        = self::csv_value($row, $indexes, 'Name');
            $email       = sanitize_email(self::csv_value($row, $indexes, 'Email'));

            if (!$customer_id || !$name || !$email) {
                $skipped[] = $customer_id ?: $email ?: 'Unknown row';
                continue;
            }

            $user = get_user_by('login', $customer_id);

            if (!$user) {
                $user_id = wp_insert_user([
                    'user_login'   => $customer_id,
                    'user_pass'    => $default_password,
                    'user_email'   => $email,
                    'display_name' => $name,
                    'first_name'   => $name,
                    'role'         => 'tm_member',
                ]);

                if (is_wp_error($user_id)) {
                    $skipped[] = "{$customer_id}: " . $user_id->get_error_message();
                    continue;
                }
                $created_users++;
            } else {
                $user_id = $user->ID;
                wp_update_user(['ID' => $user_id, 'user_email' => $email, 'display_name' => $name]);
                (new WP_User($user_id))->add_role('tm_member');
                $updated_users++;
            }

            $credential        = strtoupper(self::csv_value($row, $indexes, 'Credentials'));
            $pathways_enrolled = self::csv_value($row, $indexes, 'Pathways Enrolled');
            $parsed            = self::parse_credential($credential, $pathways_enrolled);

            TMP_Repository::upsert_imported_member([
                'user_id'           => $user_id,
                'customer_id'       => $customer_id,
                'full_name'         => $name,
                'email'             => $email,
                'phone'             => self::normalize_phone(self::csv_value($row, $indexes, 'Mobile Phone')),
                'pathway'           => $parsed['pathway'],
                'level'             => $parsed['level'],
                'state'             => self::csv_value($row, $indexes, 'Status (*)') ?: 'Active',
                'paid_until'        => self::normalize_date(self::csv_value($row, $indexes, 'Paid Until')),
                'pathways_enrolled' => $pathways_enrolled,
            ]);

            $imported++;
        }

        fclose($handle);

        return rest_ensure_response([
            'imported_members' => $imported,
            'created_users'    => $created_users,
            'updated_users'    => $updated_users,
            'skipped'          => $skipped,
        ]);
    }

    // ── Meetings ────────────────────────────────────────────────────────────────

    public static function meetings() {
        return rest_ensure_response(TMP_Repository::meetings());
    }

    public static function save_meeting(WP_REST_Request $request) {
        $meeting = TMP_Repository::save_meeting($request->get_json_params());
        if (is_wp_error($meeting)) return $meeting;
        return rest_ensure_response($meeting);
    }

    public static function delete_meeting(WP_REST_Request $request) {
        return rest_ensure_response(['deleted' => TMP_Repository::delete_meeting((int) $request['id'])]);
    }

    public static function get_all_requests() {
        $meetings = TMP_Repository::get_all_pending_requests();
        return rest_ensure_response(['meetings' => $meetings]);
    }

    public static function get_meeting_suggestions(WP_REST_Request $request) {
        return rest_ensure_response(TMP_Repository::get_suggestions((int) $request['id']));
    }

    // ── Assignments ─────────────────────────────────────────────────────────────

    public static function save_assignment(WP_REST_Request $request) {
        $assignment = TMP_Repository::save_assignment($request->get_json_params());
        if (is_wp_error($assignment)) return $assignment;
        return rest_ensure_response($assignment);
    }

    public static function delete_assignment(WP_REST_Request $request) {
        return rest_ensure_response(['deleted' => TMP_Repository::delete_assignment((int) $request['id'])]);
    }

    public static function get_assignment_conflicts(WP_REST_Request $request) {
        return rest_ensure_response(TMP_Repository::get_conflicting_requests((int) $request['id']));
    }

    public static function approve_all_recommended_requests(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $meeting_id = isset($params['meeting_id']) ? (int) $params['meeting_id'] : null;
        $result = TMP_Repository::approve_all_recommended($meeting_id);
        return rest_ensure_response($result);
    }

    public static function approve_conflict_resolved(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $member_id = (int) ($params['member_id'] ?? 0);
        $meeting_id = (int) ($params['meeting_id'] ?? 0);
        $selected_role = sanitize_text_field($params['selected_role'] ?? '');

        if (!$member_id || !$meeting_id || !$selected_role) {
            return new WP_Error('tmp_invalid', 'Missing required parameters', ['status' => 400]);
        }

        $result = TMP_Repository::approve_conflict_resolved($member_id, $meeting_id, $selected_role);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    // ── Enrolment ────────────────────────────────────────────────────────────────

    public static function enrol(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $member = TMP_Repository::save_member([
            'full_name'     => $params['name']  ?? '',
            'email'         => $params['email'] ?? '',
            'phone'         => $params['phone'] ?? '',
            'pathway'       => $params['path']  ?? 'Presentation Mastery',
            'officer_notes' => 'Enrolment Application Goals: ' . ($params['goals'] ?? 'None'),
            'state'         => 'New member',
        ]);
        if (is_wp_error($member)) return $member;
        return rest_ensure_response(['message' => 'Application received.']);
    }

    // ── Role gate settings ────────────────────────────────────────────────────────

    public static function get_role_gates() {
        // Merge stored settings with defaults so new roles appear automatically
        $defaults = TMP_Repository::default_gate_levels();
        $stored = (array) get_option('tmp_role_gate_levels', []);
        $merged = array_merge($defaults, $stored); // Stored values override defaults
        return rest_ensure_response($merged);
    }

    public static function save_role_gates(WP_REST_Request $request) {
        $gates = $request->get_json_params();
        if (!is_array($gates)) {
            return new WP_Error('tmp_invalid', 'Expected an object of pattern => level.', ['status' => 400]);
        }
        $clean = [];
        foreach ($gates as $pattern => $level) {
            $clean[sanitize_text_field($pattern)] = max(0, min(5, absint($level)));
        }
        update_option('tmp_role_gate_levels', $clean);
        return rest_ensure_response($clean);
    }

    // ── Requirement overrides ─────────────────────────────────────────────────────

    public static function create_requirement_override(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_unauthorized', 'Not linked to a member.', ['status' => 401]);
        }
        $params  = $request->get_json_params();
        $level   = absint($params['level'] ?? 0);
        $req_key = sanitize_text_field($params['req_key'] ?? '');
        $note    = sanitize_text_field($params['note'] ?? '');
        if (!$level || !$req_key) {
            return new WP_Error('tmp_invalid', 'Level and req_key are required.', ['status' => 400]);
        }
        $id = TMP_Repository::create_requirement_override($member['id'], $level, $req_key, $note);
        return rest_ensure_response(['id' => $id]);
    }

    public static function delete_requirement_override(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_unauthorized', 'Not linked to a member.', ['status' => 401]);
        }
        $deleted = TMP_Repository::delete_requirement_override((int) $request['id'], $member['id']);
        return rest_ensure_response(['deleted' => $deleted]);
    }

    public static function get_member_requirement_overrides(WP_REST_Request $request) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . TMP_Repository::overrides_table() . " WHERE member_id = %d ORDER BY level ASC, id ASC",
            (int) $request['id']
        ), ARRAY_A);
        return rest_ensure_response($rows);
    }

    // ── CSV helpers ───────────────────────────────────────────────────────────────

    private static function csv_indexes($header) {
        $indexes = [];
        foreach ($header as $index => $name) {
            $name = trim((string) $name);
            // Strip UTF-8 BOM that Excel/TI portal adds to the first column
            if ($index === 0) {
                $name = ltrim($name, "\xEF\xBB\xBF");
            }
            $indexes[$name] = $index;
        }
        return $indexes;
    }

    private static function csv_value($row, $indexes, $column) {
        if (!isset($indexes[$column])) return '';
        return trim((string) ($row[$indexes[$column]] ?? ''));
    }

    private static function parse_credential($credential, $pathways_enrolled = '') {
        $paths = [
            'DL' => 'Dynamic Leadership',
            'EC' => 'Effective Coaching',
            'EH' => 'Engaging Humor',
            'IP' => 'Innovative Planning',
            'MS' => 'Motivational Strategies',
            'PI' => 'Persuasive Influence',
            'PM' => 'Presentation Mastery',
            'SR' => 'Strategic Relationships',
            'TC' => 'Team Collaboration',
            'VC' => 'Visionary Communication',
        ];

        // DTM is a special designation, not a pathway-level code
        if (strtoupper($credential) === 'DTM') {
            return ['pathway' => 'Distinguished Toastmaster', 'level' => 5];
        }

        if (!$credential || !preg_match('/^([A-Z]{2})([1-5])$/', $credential, $matches)) {
            // Enrolled in a pathway but no completed level yet → Level 0
            if (strtolower(trim($pathways_enrolled)) === 'yes') {
                return ['pathway' => 'Enrolled — Pathway TBD', 'level' => 0];
            }
            return ['pathway' => 'No pathway registered', 'level' => 1];
        }

        $code    = $matches[1];
        $level   = (int) $matches[2];
        $pathway = $paths[$code] ?? null;

        if (!$pathway) {
            if (strtolower(trim($pathways_enrolled)) === 'yes') {
                return ['pathway' => 'Enrolled — Pathway TBD', 'level' => 0];
            }
            return ['pathway' => 'No pathway registered', 'level' => 1];
        }

        return ['pathway' => $pathway, 'level' => $level];
    }

    private static function normalize_date($value) {
        if (!$value) return null;
        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : null;
    }

    private static function normalize_phone($value) {
        if (!$value) return '';
        if (stripos($value, 'E+') !== false) {
            $value = sprintf('%.0f', (float) $value);
        }
        return sanitize_text_field($value);
    }
}
