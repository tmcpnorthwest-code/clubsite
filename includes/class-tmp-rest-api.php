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

        register_rest_route('toastmasters/v1', '/me/change-password', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'change_password'],
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

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)/reset-password', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'reset_member_password'],
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

        // ── Rebuild agenda ────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/rebuild-agenda', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'rebuild_agenda'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Agenda reorder ────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/agenda-order', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'reorder_agenda'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Notify assigned members ───────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/notify-members', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'notify_assigned_members'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Publish agenda ────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/publish', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'toggle_publish_agenda'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/meetings/published-agenda', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_published_agenda'],
            'permission_callback' => '__return_true',
        ]);

        // ── Timer defaults ────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/settings/timing-rules', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_timing_rules'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_timing_rules'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        // ── Club defaults (venue, etc.) ────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/settings/club', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_club_settings'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_club_settings'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
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

        // ── New Member Spotlight ──────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/settings/new-member-spotlight', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_spotlight_setting'],
                'permission_callback' => [__CLASS__, 'can_manage_members'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_spotlight_setting'],
                'permission_callback' => [__CLASS__, 'can_manage_members'],
            ],
        ]);

        // ── Pathways level progress ────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/me/level-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_level_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/level-up-request', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'submit_level_up_request'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/me/level-up-requests', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_my_level_up_requests'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/mentor/mentee-alerts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_mentee_alerts'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('toastmasters/v1', '/vpe/members/level-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_vpe_level_summary'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/vpe/level-up-requests', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_vpe_level_up_requests'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/vpe/level-up-requests/(?P<id>\d+)/review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'review_level_up_request'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)/level-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_member_level_status'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/members/(?P<id>\d+)/pathway-offset', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'save_pathway_offset'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
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

        // ── Public dashboard (unauthenticated) ─────────────────────────────────
        register_rest_route('toastmasters/v1', '/public/recognition', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_public_recognition'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('toastmasters/v1', '/public/meeting-summary', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_public_meeting_summary'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('toastmasters/v1', '/public/role-diversity', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_public_role_diversity'],
            'permission_callback' => '__return_true',
        ]);

        // ── Voting (public read, authenticated write) ───────────────────────────
        register_rest_route('toastmasters/v1', '/voting/nominees/(?P<meeting_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_vote_nominees'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('toastmasters/v1', '/voting/vote', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'cast_vote'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('toastmasters/v1', '/voting/tt-speaker', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'add_tt_speaker'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/voting/tt-speaker/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'remove_tt_speaker'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/voting/results/(?P<meeting_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_vote_results'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/voting/refresh-nominees/(?P<meeting_id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'refresh_vote_nominees'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── SAA attendance ─────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/me/saa-meeting', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_saa_meeting'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/saa-attendance', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'save_saa_attendance'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // ── Meeting pulse (public) ─────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/pulse', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_meeting_pulse'],
            'permission_callback' => '__return_true',
        ]);

        // ── Meeting wrap-up ────────────────────────────────────────────────────
        register_rest_route('toastmasters/v1', '/meetings/(?P<id>\d+)/wrap-up', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_meeting_wrap_up'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_meeting_wrap_up'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        register_rest_route('toastmasters/v1', '/voting/open-poll/(?P<meeting_id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'open_poll'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/voting/declare-winners/(?P<meeting_id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'declare_winners'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        // ── Recognition — TM of Month / Quarter ───────────────────────────────
        register_rest_route('toastmasters/v1', '/recognition/scores', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_recognition_scores'],
            'permission_callback' => [__CLASS__, 'can_manage_meetings'],
        ]);

        register_rest_route('toastmasters/v1', '/recognition/awards', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_recognition_awards'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'declare_recognition_award'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        register_rest_route('toastmasters/v1', '/recognition/awards/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [__CLASS__, 'update_recognition_award'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [__CLASS__, 'delete_recognition_award'],
                'permission_callback' => [__CLASS__, 'can_manage_meetings'],
            ],
        ]);

        // Mentor ratings — submitted by the logged-in mentee
        register_rest_route('toastmasters/v1', '/mentor-ratings', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'save_mentor_rating'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_my_mentor_rating'],
                'permission_callback' => 'is_user_logged_in',
            ],
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
        $data = $request->get_json_params();

        // Capture old level before save so we can warn on level bump for L1-L3
        $readiness_warnings = null;
        if (!empty($data['id']) && !empty($data['level'])) {
            $existing = TMP_Repository::get_member((int) $data['id']);
            $old_lvl  = (int)($existing['level'] ?? 0);
            $new_lvl  = (int) $data['level'];
            if ($new_lvl > $old_lvl && $old_lvl >= 1 && $old_lvl <= 3) {
                $status = TMP_Repository::get_member_full_level_status((int) $data['id']);
                if ($status['system_verdict'] === 'incomplete') {
                    $readiness_warnings = $status['verdict_detail'];
                }
            }
        }

        $member = TMP_Repository::save_member($data);
        if (is_wp_error($member)) return $member;

        $response = $member;
        if ($readiness_warnings !== null) {
            $response['readiness_warnings'] = $readiness_warnings;
        }
        return rest_ensure_response($response);
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
                'level_completed'   => $parsed['level_completed'],
                'state'             => self::normalize_member_state(self::csv_value($row, $indexes, 'Status (*)')),
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

    public static function rebuild_agenda(WP_REST_Request $request) {
        $result = TMP_Repository::rebuild_meeting_agenda((int) $request['id']);
        return rest_ensure_response(array_merge(['success' => true], $result));
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

    // ── Recognition handlers ──────────────────────────────────────────────────

    public static function get_recognition_scores(WP_REST_Request $request) {
        $period_start = sanitize_text_field($request->get_param('period_start') ?? '');
        $period_end   = sanitize_text_field($request->get_param('period_end')   ?? '');
        if (!$period_start || !$period_end) {
            return new WP_Error('tmp_invalid', 'period_start and period_end are required.', ['status' => 400]);
        }
        return rest_ensure_response(TMP_Repository::compute_recognition_scores($period_start, $period_end));
    }

    public static function get_recognition_awards(WP_REST_Request $request) {
        $type  = $request->get_param('period_type') ? sanitize_text_field($request->get_param('period_type')) : null;
        $limit = min(100, max(1, (int) ($request->get_param('limit') ?: 20)));
        return rest_ensure_response(TMP_Repository::get_recognition_awards($type, $limit));
    }

    public static function declare_recognition_award(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $result = TMP_Repository::declare_recognition_award($params);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function update_recognition_award(WP_REST_Request $request) {
        $id     = (int) $request['id'];
        $params = $request->get_json_params();
        if (isset($params['display_on_homepage'])) {
            TMP_Repository::update_recognition_award_homepage($id, $params['display_on_homepage']);
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function delete_recognition_award(WP_REST_Request $request) {
        $deleted = TMP_Repository::delete_recognition_award((int) $request['id']);
        return rest_ensure_response(['deleted' => $deleted]);
    }

    public static function save_mentor_rating(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_unauthorized', 'Not linked to a member.', ['status' => 401]);
        }
        if (empty($member['mentor_id'])) {
            return new WP_Error('tmp_no_mentor', 'You do not have a mentor assigned.', ['status' => 400]);
        }
        $params               = $request->get_json_params();
        $params['mentee_id']  = $member['id'];
        $params['mentor_id']  = $member['mentor_id'];
        $result = TMP_Repository::save_mentor_rating($params);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response($result);
    }

    public static function get_my_mentor_rating(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_unauthorized', 'Not linked to a member.', ['status' => 401]);
        }
        $period_start = sanitize_text_field($request->get_param('period_start') ?? '');
        $period_end   = sanitize_text_field($request->get_param('period_end')   ?? '');
        $rating = $period_start && $period_end
            ? TMP_Repository::get_mentor_rating_for_period($member['id'], $period_start, $period_end)
            : null;
        return rest_ensure_response([
            'has_mentor'   => !empty($member['mentor_id']),
            'mentor_name'  => $member['mentor_name'] ?? null,
            'mentor_id'    => $member['mentor_id']   ?? null,
            'existing_rating' => $rating,
        ]);
    }

    // ── Public dashboard handlers ─────────────────────────────────────────────────

    public static function get_public_recognition(WP_REST_Request $request) {
        $limit = min(50, max(1, (int) ($request->get_param('limit') ?: 20)));
        return rest_ensure_response([
            'level_ups' => TMP_Repository::get_recent_level_ups($limit),
            'awards'    => TMP_Repository::get_homepage_recognition_awards(),
        ]);
    }

    public static function get_public_meeting_summary(WP_REST_Request $request) {
        $meeting_id = $request->get_param('meeting_id') ? absint($request->get_param('meeting_id')) : null;
        $summary    = TMP_Repository::get_meeting_summary($meeting_id);
        if (!$summary) {
            return new WP_Error('tmp_no_meeting', 'No completed meeting found.', ['status' => 404]);
        }
        return rest_ensure_response($summary);
    }

    public static function get_public_role_diversity(WP_REST_Request $request) {
        $limit = min(20, max(1, (int) ($request->get_param('limit') ?: 5)));
        return rest_ensure_response([
            'leaders' => TMP_Repository::get_role_diversity_leaders($limit),
        ]);
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
            return ['pathway' => 'Distinguished Toastmaster', 'level' => 5, 'level_completed' => 5];
        }

        if (!$credential || !preg_match('/^([A-Z]{2})([1-5])$/', $credential, $matches)) {
            if (strtolower(trim($pathways_enrolled)) === 'yes') {
                return ['pathway' => 'Enrolled — Pathway TBD', 'level' => 0, 'level_completed' => 0];
            }
            return ['pathway' => 'No pathway registered', 'level' => 0, 'level_completed' => 0];
        }

        $code    = $matches[1];
        $completed = (int) $matches[2];
        $pathway = $paths[$code] ?? null;

        if (!$pathway) {
            if (strtolower(trim($pathways_enrolled)) === 'yes') {
                return ['pathway' => 'Enrolled — Pathway TBD', 'level' => 0, 'level_completed' => 0];
            }
            return ['pathway' => 'No pathway registered', 'level' => 0, 'level_completed' => 0];
        }

        // If level N is completed, member is working on level N+1 (or stays at N if already at max)
        $working_level = min($completed + 1, 5);
        return ['pathway' => $pathway, 'level' => $working_level, 'level_completed' => $completed];
    }

    private static function normalize_member_state($ti_state) {
        // Only map values confirmed to exist in TI CSV exports for this club.
        // Add entries here if a new TI status appears in a future import.
        $map = [
            'paidmember'   => 'Active',
            'unpaidmember' => 'Inactive',
            'active'       => 'Active',   // manually-added members
            'inactive'     => 'Inactive', // manually-added members
            'resigned'     => 'Resigned', // manually-added members
        ];
        $key = strtolower(trim((string) $ti_state));
        return $map[$key] ?? ($ti_state ?: 'Active');
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

    // ── Voting handlers ────────────────────────────────────────────────────────

    public static function get_vote_nominees(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('meeting_id');

        // Auto-populate main/aux nominees from role assignments on first fetch
        $existing = TMP_Repository::get_vote_nominees($meeting_id);
        if (empty($existing['main_role']) && empty($existing['aux_role'])) {
            TMP_Repository::populate_vote_nominees($meeting_id);
        }

        $nominees = TMP_Repository::get_vote_nominees($meeting_id);
        $meeting  = TMP_Repository::get_meeting($meeting_id);
        $poll_open = $meeting ? (bool) $meeting['poll_open'] : false;

        return rest_ensure_response([
            'meeting_id'  => $meeting_id,
            'voting_open' => $poll_open,
            'poll_open'   => $poll_open,
            'nominees'    => $nominees,
        ]);
    }

    public static function cast_vote(WP_REST_Request $req) {
        $meeting_id  = (int) $req->get_param('meeting_id');
        $nominee_id  = (int) $req->get_param('nominee_id');

        if (!$meeting_id || !$nominee_id) {
            return new WP_Error('missing_params', 'meeting_id and nominee_id are required', ['status' => 400]);
        }

        // Prefer a client-supplied device token (avoids shared-IP conflicts on same WiFi).
        // Fall back to IP+UA only when the client sends nothing.
        $client_token = sanitize_text_field($req->get_param('voter_token') ?? '');
        if ($client_token && strlen($client_token) >= 16 && strlen($client_token) <= 128) {
            $token = hash('sha256', $client_token . '|' . $meeting_id);
        } else {
            $ip    = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
            $ua    = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
            $token = hash('sha256', $ip . '|' . $ua . '|' . $meeting_id);
        }

        $result = TMP_Repository::cast_vote(
            $meeting_id,
            $nominee_id,
            $token,
            is_user_logged_in() ? get_current_user_id() : null
        );

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'already_voted' ? 409 : 400;
            return new WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => $status]);
        }

        // Return updated counts so UI can refresh without a separate fetch
        $nominees = TMP_Repository::get_vote_nominees($meeting_id);
        return rest_ensure_response(['success' => true, 'nominees' => $nominees]);
    }

    public static function add_tt_speaker(WP_REST_Request $req) {
        $meeting_id   = (int) $req->get_param('meeting_id');
        $display_name = sanitize_text_field($req->get_param('display_name'));
        $member_id    = $req->get_param('member_id') ? (int) $req->get_param('member_id') : null;

        if (!$meeting_id || !$display_name) {
            return new WP_Error('missing_params', 'meeting_id and display_name are required', ['status' => 400]);
        }

        $id       = TMP_Repository::add_tt_speaker($meeting_id, $display_name, $member_id);
        $nominees = TMP_Repository::get_vote_nominees($meeting_id);
        return rest_ensure_response(['success' => true, 'nominee_id' => $id, 'nominees' => $nominees]);
    }

    public static function remove_tt_speaker(WP_REST_Request $req) {
        $nominee_id = (int) $req->get_param('id');
        $result     = TMP_Repository::remove_tt_speaker($nominee_id);

        if (is_wp_error($result)) {
            $status = $result->get_error_code() === 'has_votes' ? 409 : 404;
            return new WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => $status]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function get_vote_results(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('meeting_id');
        return rest_ensure_response(TMP_Repository::get_vote_results($meeting_id));
    }

    public static function refresh_vote_nominees(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('meeting_id');
        TMP_Repository::populate_vote_nominees($meeting_id);
        $nominees = TMP_Repository::get_vote_nominees($meeting_id);
        return rest_ensure_response(['success' => true, 'nominees' => $nominees]);
    }

    public static function open_poll(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('meeting_id');
        $open       = (bool) $req->get_param('open');
        TMP_Repository::set_poll_open($meeting_id, $open);
        return rest_ensure_response(['success' => true, 'poll_open' => $open]);
    }

    public static function declare_winners(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('meeting_id');
        $results    = TMP_Repository::declare_winners($meeting_id);
        return rest_ensure_response(['success' => true, 'results' => $results]);
    }

    // ── SAA attendance handlers ────────────────────────────────────────────────

    public static function get_saa_meeting() {
        $data = TMP_Repository::get_saa_meeting();
        if (!$data) {
            return new WP_Error('no_saa_meeting', 'No SAA role found for today', ['status' => 404]);
        }
        return rest_ensure_response($data);
    }

    public static function save_saa_attendance(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('id');
        if (!$meeting_id) {
            return new WP_Error('missing_id', 'Meeting ID required', ['status' => 400]);
        }
        TMP_Repository::save_saa_attendance($meeting_id, $req->get_json_params());
        return rest_ensure_response(['saved' => true]);
    }

    // ── Wrap-up handlers ───────────────────────────────────────────────────────

    public static function get_meeting_pulse() {
        $cache_key = 'tmp_meeting_pulse';
        $summary   = get_transient($cache_key);
        if ($summary === false) {
            $summary = TMP_Repository::get_meeting_summary();
            if ($summary) {
                set_transient($cache_key, $summary, 60); // cache 60s
            }
        }
        if (!$summary) {
            return new WP_Error('no_meeting', 'No past meeting found', ['status' => 404]);
        }
        return rest_ensure_response($summary);
    }

    public static function get_meeting_wrap_up(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('id');
        $data       = TMP_Repository::get_wrap_up_data($meeting_id);
        if (!$data) {
            return new WP_Error('not_found', 'Meeting not found', ['status' => 404]);
        }
        return rest_ensure_response($data);
    }

    public static function save_meeting_wrap_up(WP_REST_Request $req) {
        $meeting_id = (int) $req->get_param('id');
        $body       = $req->get_json_params();

        if (!$meeting_id) {
            return new WP_Error('missing_id', 'Meeting ID required', ['status' => 400]);
        }

        TMP_Repository::save_wrap_up($meeting_id, $body);
        delete_transient('tmp_meeting_pulse'); // bust cache so home page reflects updated data immediately
        $summary = TMP_Repository::get_meeting_summary($meeting_id);
        return rest_ensure_response(['success' => true, 'summary' => $summary]);
    }

    public static function toggle_publish_agenda(WP_REST_Request $request) {
        global $wpdb;
        $id       = (int) $request['id'];
        $meetings = $wpdb->prefix . 'tmp_meetings';

        $meeting = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_published FROM {$meetings} WHERE id = %d", $id
        ), ARRAY_A);
        if (!$meeting) {
            return new WP_Error('not_found', 'Meeting not found', ['status' => 404]);
        }

        $new_state = ((int) $meeting['is_published']) ? 0 : 1;
        if ($new_state === 1) {
            // Only one meeting can be published at a time
            $wpdb->query("UPDATE {$meetings} SET is_published = 0");
        }
        $wpdb->update($meetings, ['is_published' => $new_state], ['id' => $id]);
        return rest_ensure_response(['is_published' => $new_state]);
    }

    public static function notify_assigned_members(WP_REST_Request $request) {
        $test_email = sanitize_email($request->get_param('test_email') ?? '');
        $result = TMP_Repository::notify_assigned_members((int) $request['id'], $test_email ?: null);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function get_published_agenda(WP_REST_Request $request) {
        return rest_ensure_response(TMP_Repository::get_published_agenda());
    }

    public static function reorder_agenda(WP_REST_Request $request) {
        $meeting_id  = (int) $request->get_param('id');
        $body        = $request->get_json_params();
        $ordered_ids = $body['order'] ?? [];
        if (empty($ordered_ids) || !is_array($ordered_ids)) {
            return new WP_Error('invalid_order', 'order must be a non-empty array', ['status' => 400]);
        }
        TMP_Repository::reorder_agenda($meeting_id, $ordered_ids);
        return rest_ensure_response(['success' => true]);
    }

    public static function get_club_settings() {
        return rest_ensure_response([
            'default_venue'    => get_option('tmp_default_venue', ''),
            'default_maps_url' => get_option('tmp_default_maps_url', ''),
        ]);
    }

    public static function save_club_settings(WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (isset($body['default_venue'])) {
            update_option('tmp_default_venue', sanitize_text_field($body['default_venue']));
        }
        if (isset($body['default_maps_url'])) {
            update_option('tmp_default_maps_url', esc_url_raw($body['default_maps_url']));
        }
        return rest_ensure_response(['success' => true]);
    }

    public static function get_timing_rules() {
        return rest_ensure_response(TMP_Repository::get_timing_defaults());
    }

    public static function save_timing_rules(WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            return new WP_Error('invalid_data', 'Expected array of timing rules', ['status' => 400]);
        }
        $sanitized = [];
        foreach ($body as $rule) {
            if (empty($rule['key'])) continue;
            $sanitized[] = [
                'key'    => sanitize_key($rule['key']),
                'label'  => sanitize_text_field($rule['label'] ?? ''),
                'green'  => absint($rule['green']  ?? 0),
                'yellow' => absint($rule['yellow'] ?? 0),
                'red'    => absint($rule['red']    ?? 0),
            ];
        }
        update_option('tmp_timing_rules', wp_json_encode($sanitized));
        return rest_ensure_response(['success' => true]);
    }

    // ── Pathways level progress callbacks ──────────────────────────────────────

    public static function get_my_level_status() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }
        return rest_ensure_response(TMP_Repository::get_member_full_level_status($member['id']));
    }

    public static function submit_level_up_request(WP_REST_Request $request) {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }
        $note   = sanitize_textarea_field($request->get_json_params()['note'] ?? '');
        $result = TMP_Repository::submit_level_up_request($member['id'], $note);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(['id' => $result]);
    }

    public static function get_my_level_up_requests() {
        global $wpdb;
        $member = TMP_Repository::current_member();
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }
        $table = $wpdb->prefix . 'tmp_level_up_requests';
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT id, from_level, to_level, status, member_note, system_verdict, vpe_note, created_at, reviewed_at
               FROM {$table}
              WHERE member_id = %d
              ORDER BY created_at DESC
              LIMIT 10",
            $member['id']
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function get_mentee_alerts() {
        $member = TMP_Repository::current_member();
        if (!$member) {
            return rest_ensure_response([]);
        }
        return rest_ensure_response(TMP_Repository::get_mentor_mentee_alerts($member['id']));
    }

    public static function get_vpe_level_summary() {
        global $wpdb;
        $members_table = $wpdb->prefix . 'tmp_members';
        $members = $wpdb->get_results(
            "SELECT id, full_name, pathway, level
               FROM {$members_table}
              WHERE level BETWEEN 1 AND 3
              ORDER BY level ASC, full_name ASC",
            ARRAY_A
        );

        $result = [];
        foreach ($members as $m) {
            $m_id   = (int) $m['id'];
            $status = TMP_Repository::get_member_full_level_status($m_id);
            $sp     = $status['speech_progress'];
            $roles_unmet = count(array_filter($status['role_gaps'], fn($g) => !$g['met']));

            if ($sp !== null && $sp['done'] === 0 && $roles_unmet === count($status['role_gaps'])) {
                $traffic = 'stuck';
            } elseif ($status['ready_to_advance']) {
                $traffic = 'ready';
            } else {
                $traffic = 'in_progress';
            }

            $result[] = [
                'member_id'        => $m_id,
                'name'             => $m['full_name'],
                'pathway'          => $m['pathway'],
                'level'            => (int) $m['level'],
                'speech_done'      => $sp ? $sp['done']   : null,
                'speech_needed'    => $sp ? $sp['needed'] : null,
                'roles_unmet'      => $roles_unmet,
                'roles_total'      => count($status['role_gaps']),
                'ready_to_advance' => $status['ready_to_advance'],
                'traffic_light'    => $traffic,
            ];
        }
        return rest_ensure_response($result);
    }

    public static function get_member_level_status(WP_REST_Request $request) {
        $member = TMP_Repository::get_member((int) $request['id']);
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }
        return rest_ensure_response(TMP_Repository::get_member_full_level_status((int) $request['id']));
    }

    public static function get_vpe_level_up_requests() {
        global $wpdb;
        $table   = $wpdb->prefix . 'tmp_level_up_requests';
        $members = $wpdb->prefix . 'tmp_members';
        $rows = $wpdb->get_results(
            "SELECT r.id, r.member_id, r.from_level, r.to_level, r.status,
                    r.member_note, r.evidence, r.system_verdict, r.created_at,
                    m.full_name, m.pathway
               FROM {$table} r
               JOIN {$members} m ON m.id = r.member_id
              WHERE r.status = 'pending'
              ORDER BY r.created_at ASC",
            ARRAY_A
        );

        $result = [];
        foreach ($rows as $row) {
            $evidence = json_decode($row['evidence'], true);
            $result[] = [
                'id'             => (int) $row['id'],
                'member_id'      => (int) $row['member_id'],
                'member_name'    => $row['full_name'],
                'pathway'        => $row['pathway'],
                'from_level'     => (int) $row['from_level'],
                'to_level'       => (int) $row['to_level'],
                'member_note'    => $row['member_note'],
                'system_verdict' => $row['system_verdict'],
                'evidence'       => $evidence,
                'created_at'     => $row['created_at'],
            ];
        }
        return rest_ensure_response($result);
    }

    public static function review_level_up_request(WP_REST_Request $request) {
        $body       = $request->get_json_params();
        $action     = $body['action'] ?? '';
        $note       = sanitize_textarea_field($body['note'] ?? '');
        $request_id = (int) $request['id'];
        $vpe_id     = get_current_user_id();

        if ($action === 'approve') {
            $ok = TMP_Repository::approve_level_up_request($request_id, $vpe_id, $note);
        } elseif ($action === 'deny') {
            $ok = TMP_Repository::deny_level_up_request($request_id, $vpe_id, $note);
        } else {
            return new WP_Error('tmp_invalid_action', 'Action must be approve or deny.', ['status' => 400]);
        }

        if (!$ok) {
            return new WP_Error('tmp_not_found', 'Request not found or already reviewed.', ['status' => 404]);
        }
        return rest_ensure_response(['ok' => true]);
    }

    public static function save_pathway_offset(WP_REST_Request $request) {
        $body   = $request->get_json_params();
        $m_id   = (int) $request['id'];
        $level  = (int)($body['level']  ?? 0);
        $offset = (int)($body['offset'] ?? 0);
        $notes  = sanitize_text_field($body['notes'] ?? '');

        if (!TMP_Repository::get_member($m_id)) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }
        if ($level < 1 || $level > 3) {
            return new WP_Error('tmp_invalid_level', 'Offset is only valid for levels 1–3.', ['status' => 400]);
        }

        global $wpdb;
        $ok = TMP_Repository::set_pathway_offset($m_id, $level, $offset, $notes);
        if (!$ok) {
            return new WP_Error('tmp_db_error', 'Could not save offset: ' . $wpdb->last_error, ['status' => 500]);
        }
        return rest_ensure_response(['ok' => true]);
    }

    public static function get_spotlight_setting() {
        $raw = get_option('tmp_new_member_spotlight', null);
        return rest_ensure_response($raw ? json_decode($raw, true) : null);
    }

    public static function save_spotlight_setting(WP_REST_Request $request) {
        $body      = $request->get_json_params();
        $member_id = (int) ($body['member_id'] ?? 0);
        $blurb     = sanitize_textarea_field($body['blurb'] ?? '');
        $photo_url = esc_url_raw($body['photo_url'] ?? '');
        $active    = !empty($body['active']);

        if ($member_id && !TMP_Repository::get_member($member_id)) {
            return new WP_Error('invalid_member', 'Member not found', ['status' => 400]);
        }

        update_option('tmp_new_member_spotlight', wp_json_encode(compact('member_id', 'blurb', 'photo_url', 'active')));
        return rest_ensure_response(['ok' => true]);
    }

    // ── Password management ────────────────────────────────────────────────────

    public static function change_password(WP_REST_Request $request) {
        $body             = $request->get_json_params();
        $current_password = $body['current_password'] ?? '';
        $new_password     = $body['new_password']     ?? '';

        if (strlen($new_password) < 8) {
            return new WP_Error('tmp_invalid', 'New password must be at least 8 characters.', ['status' => 400]);
        }

        $user = wp_get_current_user();
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            return new WP_Error('tmp_invalid', 'Current password is incorrect.', ['status' => 400]);
        }

        wp_set_password($new_password, $user->ID);
        return rest_ensure_response(['success' => true]);
    }

    public static function reset_member_password(WP_REST_Request $request) {
        $body         = $request->get_json_params();
        $new_password = $body['new_password'] ?? '';

        if (strlen($new_password) < 8) {
            return new WP_Error('tmp_invalid', 'Password must be at least 8 characters.', ['status' => 400]);
        }

        $member = TMP_Repository::get_member(absint($request->get_param('id')));
        if (!$member) {
            return new WP_Error('tmp_not_found', 'Member not found.', ['status' => 404]);
        }

        $wp_user_id = (int) ($member['user_id'] ?? 0);
        if (!$wp_user_id) {
            return new WP_Error('tmp_not_found', 'No WordPress account linked to this member.', ['status' => 404]);
        }

        wp_set_password($new_password, $wp_user_id);
        return rest_ensure_response(['success' => true]);
    }
}
