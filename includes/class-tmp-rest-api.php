<?php

if (!defined('ABSPATH')) {
    exit;
}

class TMP_REST_API {
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    public static function routes() {
        register_rest_route('toastmasters/v1', '/me', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'me'),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ));

        register_rest_route('toastmasters/v1', '/members', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'members'),
                'permission_callback' => array(__CLASS__, 'can_view_all_members'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'save_member'),
                'permission_callback' => array(__CLASS__, 'can_manage_members'),
            ),
        ));

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_member'),
            'permission_callback' => array(__CLASS__, 'can_manage_members'),
        ));

        register_rest_route('toastmasters/v1', '/members/import', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'import_members'),
            'permission_callback' => array(__CLASS__, 'can_manage_members'),
        ));

        register_rest_route('toastmasters/v1', '/meetings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'meetings'),
                'permission_callback' => array(__CLASS__, 'can_manage_meetings'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(__CLASS__, 'save_meeting'),
                'permission_callback' => array(__CLASS__, 'can_manage_meetings'),
            ),
        ));

        register_rest_route('toastmasters/v1', '/assignments', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'save_assignment'),
            'permission_callback' => array(__CLASS__, 'can_manage_meetings'),
        ));

        register_rest_route('toastmasters/v1', '/assignments/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array(__CLASS__, 'delete_assignment'),
            'permission_callback' => array(__CLASS__, 'can_manage_meetings'),
        ));

        register_rest_route('toastmasters/v1', '/enrol', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'enrol'),
            'permission_callback' => function () {
                return true;
            },
        ));
    }

    public static function can_manage_members() {
        return current_user_can('tmp_manage_members');
    }

    public static function can_view_all_members() {
        return current_user_can('tmp_view_all_members') || current_user_can('tmp_manage_members');
    }

    public static function can_manage_meetings() {
        return current_user_can('tmp_manage_meetings');
    }

    public static function me() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_member_not_found', 'No Toastmasters member record is linked to this WordPress account email.', array('status' => 404));
        }

        return rest_ensure_response($member);
    }

    public static function members() {
        return rest_ensure_response(TMP_Repository::members());
    }

    public static function save_member(WP_REST_Request $request) {
        $member = TMP_Repository::save_member($request->get_json_params());
        if (is_wp_error($member)) {
            return $member;
        }

        return rest_ensure_response($member);
    }

    public static function delete_member(WP_REST_Request $request) {
        return rest_ensure_response(array('deleted' => TMP_Repository::delete_member((int) $request['id'])));
    }

    public static function enrol(WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        $data = array(
            'full_name' => $params['name'] ?? '',
            'email' => $params['email'] ?? '',
            'phone' => $params['phone'] ?? '',
            'pathway' => $params['path'] ?? 'Presentation Mastery',
            'officer_notes' => 'Enrolment Application Goals: ' . ($params['goals'] ?? 'None'),
            'state' => 'New member',
        );

        $member = TMP_Repository::save_member($data);
        if (is_wp_error($member)) {
            return $member;
        }
        return rest_ensure_response(array('message' => 'Application received.'));
    }

    public static function import_members(WP_REST_Request $request) {
        $files = $request->get_file_params();
        if (empty($files['file']['tmp_name'])) {
            return new WP_Error('tmp_missing_file', 'Upload a CSV file.', array('status' => 400));
        }

        $default_password = (string) $request->get_param('default_password');
        if (strlen($default_password) < 8) {
            return new WP_Error('tmp_weak_password', 'Default password must be at least 8 characters.', array('status' => 400));
        }

        $handle = fopen($files['file']['tmp_name'], 'r');
        if (!$handle) {
            return new WP_Error('tmp_file_open_failed', 'Could not read the uploaded CSV.', array('status' => 400));
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('tmp_empty_csv', 'The CSV appears to be empty.', array('status' => 400));
        }

        $indexes = self::csv_indexes($header);
        $required = array('Customer ID', 'Name', 'Email');
        foreach ($required as $column) {
            if (!isset($indexes[$column])) {
                fclose($handle);
                return new WP_Error('tmp_missing_column', "Missing required column: {$column}.", array('status' => 400));
            }
        }

        $created_users = 0;
        $updated_users = 0;
        $imported_members = 0;
        $skipped = array();

        while (($row = fgetcsv($handle)) !== false) {
            if (!array_filter($row)) {
                continue;
            }

            $customer_id = self::csv_value($row, $indexes, 'Customer ID');
            $name = self::csv_value($row, $indexes, 'Name');
            $email = sanitize_email(self::csv_value($row, $indexes, 'Email'));

            if (!$customer_id || !$name || !$email) {
                $skipped[] = $customer_id ?: $email ?: 'Unknown row';
                continue;
            }

            $user = get_user_by('login', $customer_id);
            if (!$user && username_exists($customer_id)) {
                $user = get_user_by('login', $customer_id);
            }

            if (!$user) {
                $user_id = wp_insert_user(array(
                    'user_login' => $customer_id,
                    'user_pass' => $default_password,
                    'user_email' => $email,
                    'display_name' => $name,
                    'first_name' => $name,
                    'role' => 'tm_member',
                ));

                if (is_wp_error($user_id)) {
                    $skipped[] = "{$customer_id}: " . $user_id->get_error_message();
                    continue;
                }

                $created_users++;
            } else {
                $user_id = $user->ID;
                wp_update_user(array(
                    'ID' => $user_id,
                    'user_email' => $email,
                    'display_name' => $name,
                ));
                $user_obj = new WP_User($user_id);
                $user_obj->add_role('tm_member');
                $updated_users++;
            }

            $credential = strtoupper(self::csv_value($row, $indexes, 'Credentials'));
            $parsed = self::parse_credential($credential);

            TMP_Repository::upsert_imported_member(array(
                'user_id' => $user_id,
                'customer_id' => $customer_id,
                'full_name' => $name,
                'email' => $email,
                'phone' => self::normalize_phone(self::csv_value($row, $indexes, 'Mobile Phone')),
                'pathway' => $parsed['pathway'],
                'level' => $parsed['level'],
                'state' => self::csv_value($row, $indexes, 'Status (*)') ?: 'Active',
                'paid_until' => self::normalize_date(self::csv_value($row, $indexes, 'Paid Until')),
                'pathways_enrolled' => self::csv_value($row, $indexes, 'Pathways Enrolled'),
                'current_project' => $parsed['project'],
                'mentor' => '',
                'next_action' => $parsed['next_action'],
                'officer_notes' => $parsed['notes'],
            ));

            $imported_members++;
        }

        fclose($handle);

        return rest_ensure_response(array(
            'imported_members' => $imported_members,
            'created_users' => $created_users,
            'updated_users' => $updated_users,
            'skipped' => $skipped,
        ));
    }

    private static function csv_indexes($header) {
        $indexes = array();
        foreach ($header as $index => $name) {
            $indexes[trim((string) $name)] = $index;
        }
        return $indexes;
    }

    private static function csv_value($row, $indexes, $column) {
        if (!isset($indexes[$column])) {
            return '';
        }

        return trim((string) ($row[$indexes[$column]] ?? ''));
    }

    private static function parse_credential($credential) {
        $paths = array(
            'DL' => 'Dynamic Leadership',
            'EH' => 'Engaging Humor',
            'MS' => 'Motivational Strategies',
            'PM' => 'Presentation Mastery',
            'PI' => 'Persuasive Influence',
            'VC' => 'Visionary Communication',
        );

        if (!$credential || !preg_match('/^([A-Z]{2})([1-5])$/', $credential, $matches)) {
            return array(
                'pathway' => 'No pathway registered',
                'level' => 1,
                'project' => 'Pathway not registered',
                'next_action' => 'Help member register for a Pathway.',
                'notes' => 'Imported with blank or unrecognized credential.',
            );
        }

        $code = $matches[1];
        $level = (int) $matches[2];
        $pathway = $paths[$code] ?? 'No pathway registered';

        if ($pathway === 'No pathway registered') {
            return array(
                'pathway' => $pathway,
                'level' => 1,
                'project' => "Unrecognized credential {$credential}",
                'next_action' => 'Review imported credential and assign the correct Pathway.',
                'notes' => "Imported with unrecognized credential {$credential}.",
            );
        }

        return array(
            'pathway' => $pathway,
            'level' => $level,
            'project' => "Credential {$credential}",
            'next_action' => "Continue {$pathway} Level {$level}.",
            'notes' => "Imported from credential {$credential}.",
        );
    }

    private static function normalize_date($value) {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return null;
        }

        return gmdate('Y-m-d', $timestamp);
    }

    private static function normalize_phone($value) {
        if (!$value) {
            return '';
        }

        if (stripos($value, 'E+') !== false) {
            $value = sprintf('%.0f', (float) $value);
        }

        return sanitize_text_field($value);
    }

    public static function meetings() {
        return rest_ensure_response(TMP_Repository::meetings());
    }

    public static function save_meeting(WP_REST_Request $request) {
        $meeting = TMP_Repository::save_meeting($request->get_json_params());
        if (is_wp_error($meeting)) {
            return $meeting;
        }

        return rest_ensure_response($meeting);
    }

    public static function save_assignment(WP_REST_Request $request) {
        $assignment = TMP_Repository::save_assignment($request->get_json_params());
        if (is_wp_error($assignment)) {
            return $assignment;
        }

        return rest_ensure_response($assignment);
    }

    public static function delete_assignment(WP_REST_Request $request) {
        return rest_ensure_response(array('deleted' => TMP_Repository::delete_assignment((int) $request['id'])));
    }
}
