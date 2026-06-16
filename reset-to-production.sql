-- ============================================================
-- Toastmasters Portal — Production Reset Script
-- ============================================================
-- KEEPS:  wp_tmp_members (all imported member data)
-- CLEARS: all meetings, assignments, requests, history,
--         votes, attendance, offsets, level-up requests,
--         recognition, and spotlight option
--
-- Run this in phpMyAdmin (SQL tab) or MySQL CLI.
-- Replace "wp_" with your actual table prefix if different.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE wp_tmp_meetings;
TRUNCATE TABLE wp_tmp_role_assignments;
TRUNCATE TABLE wp_tmp_member_requests;
TRUNCATE TABLE wp_tmp_participation_history;
TRUNCATE TABLE wp_tmp_req_overrides;
TRUNCATE TABLE wp_tmp_level_up_history;
TRUNCATE TABLE wp_tmp_vote_nominees;
TRUNCATE TABLE wp_tmp_votes;
TRUNCATE TABLE wp_tmp_attendance;
TRUNCATE TABLE wp_tmp_win_history;
TRUNCATE TABLE wp_tmp_mentor_ratings;
TRUNCATE TABLE wp_tmp_recognition_awards;
TRUNCATE TABLE wp_tmp_pathway_offsets;
TRUNCATE TABLE wp_tmp_level_up_requests;

SET FOREIGN_KEY_CHECKS = 1;

-- Clear the spotlight (content state, not a setting)
DELETE FROM wp_options WHERE option_name = 'tmp_new_member_spotlight';

-- ============================================================
-- Done. Members, settings, and role gate config are preserved.
-- ============================================================
