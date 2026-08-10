-- ============================================================================
-- Diagnostic: why did the 16 member_requests rows (role_name IS NULL) not
-- get backfilled by manual-backfill-remaining-rows.sql section 2? That
-- script's UPDATE only touches rows where a matching wp_tmp_role_assignments
-- row exists via assignment_id. This checks whether that assumption held.
-- ============================================================================

SELECT
    r.id AS request_id,
    r.assignment_id,
    CASE WHEN a.id IS NULL THEN 'ASSIGNMENT ROW MISSING' ELSE 'assignment exists' END AS assignment_status,
    a.role_name AS assignment_role_name,
    a.role_id AS assignment_role_id
FROM wp_tmp_member_requests r
LEFT JOIN wp_tmp_role_assignments a ON a.id = r.assignment_id
WHERE r.role_id IS NULL
  AND r.role_name IS NULL
ORDER BY r.id;
