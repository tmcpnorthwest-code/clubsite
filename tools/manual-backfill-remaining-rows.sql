-- ============================================================================
-- Manual backfill for the small number of rows the automatic classifier
-- (TMP_Repository::backfill_role_ids() / dry-run-backfill-role-ids.sql)
-- correctly left unmatched because they need a human judgment call, not a
-- pattern match. Reviewed and decided 2026-08-10:
--
--   1. 7 role_assignments/participation_history rows from a one-off
--      contest/guest-speaker meeting, hand-typed via the old free-text
--      agenda editor instead of using the standard role picker. They map
--      1:1 onto existing catalog roles (ad_hoc_speaker, fun_session, tmod)
--      -- Ad Hoc Speaker and Fun Session are the correct role to pick for
--      a guest speaker / fun session going forward too (already seeded as
--      optional template segments -- see migrate_v250_seed_agenda_template
--      rows with role_key IN ('ad_hoc_speaker','fun_session')). These 7
--      rows are just legacy wording for the same concept.
--   2. 16 member_requests rows with role_name IS NULL, predating the v170
--      migration (which only backfilled rows where role_name was NULL by
--      copying from the linked assignment -- these 16 either had no
--      assignment_id at the time or were missed). All 16 are already
--      resolved (Approved/NotSelected) from 2026-06. Backfilling via their
--      assignment_id's role_name, same logic v170 itself used.
--
-- Run this ONCE, after confirming the dry-run report matches what's
-- described above. Every UPDATE is scoped by primary key id, so re-running
-- is harmless (rows already updated just get set to the same value again).
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1a. role_assignments -- remap the 6 free-text rows to their real role.
-- ----------------------------------------------------------------------------

UPDATE wp_tmp_role_assignments a
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'tmod'
SET a.role_id = rc.id
WHERE a.id IN (1819, 1820, 1838) -- "TMOD introduces the segments", "TMOD introduces Timer",
                                  -- "TMOD introduces and welcomes Guest Speaker"
  AND a.role_id IS NULL;

UPDATE wp_tmp_role_assignments a
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'ad_hoc_speaker'
SET a.role_id = rc.id,
    a.segment_label = 'Guest Speech'
WHERE a.id IN (1824, 1835) -- "Opening Keynote Speech", "Closing Keynote Speech"
  AND a.role_id IS NULL;

UPDATE wp_tmp_role_assignments a
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'fun_session'
SET a.role_id = rc.id,
    a.segment_label = 'Organizes fun session'
WHERE a.id = 1843 -- "Fun Activities"
  AND a.role_id IS NULL;

-- Verify: should return 0 rows.
SELECT id, role_name, role_id FROM wp_tmp_role_assignments WHERE id IN (1819, 1820, 1824, 1835, 1838, 1843) AND role_id IS NULL;

-- ----------------------------------------------------------------------------
-- 1b. participation_history -- same "Opening Keynote Speech" row.
-- ----------------------------------------------------------------------------

UPDATE wp_tmp_participation_history h
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'ad_hoc_speaker'
SET h.role_id = rc.id
WHERE h.id = 67
  AND h.role_id IS NULL;

-- Verify: should return 0 rows.
SELECT id, role_name, role_id FROM wp_tmp_participation_history WHERE id = 67 AND role_id IS NULL;

-- ----------------------------------------------------------------------------
-- 2. member_requests -- 16 rows with role_name IS NULL, all resolved
--    (Approved/NotSelected) requests from 2026-06, predating v170.
--
--    RESOLVED 2026-08-10: all 16 rows' assignment_id (30, 33, 34, 35, 36,
--    56, 75, 92, 93) point at wp_tmp_role_assignments rows that NO LONGER
--    EXIST -- confirmed via diagnose-member-requests-null.sql. These are
--    early meetings whose agendas were later rebuilt via
--    rebuild_meeting_agenda() (which deletes and regenerates all
--    assignment rows for a meeting), orphaning the old assignment_id
--    references. There is no remaining data source to recover the
--    intended role from -- guessing from meeting_id/member_id/timing
--    would be exactly the unsafe inference this backfill is designed to
--    avoid. Left permanently role_id = NULL; role_name stays NULL too
--    (unchanged from before). All 16 requests are already resolved
--    (Approved/NotSelected) and have no live functional dependency.
--
--    The UPDATE below is kept for reference/idempotency but will affect
--    0 rows given the above -- do not "fix" it to guess a role.
-- ----------------------------------------------------------------------------

-- Preview before writing: shows what each of the 16 would be set to.
SELECT
    r.id AS request_id,
    r.assignment_id,
    a.role_name AS assignment_role_name,
    a.role_id AS assignment_role_id,
    rc.role_key AS resolved_role_key
FROM wp_tmp_member_requests r
JOIN wp_tmp_role_assignments a ON a.id = r.assignment_id
LEFT JOIN wp_tmp_role_catalog rc ON rc.id = a.role_id
WHERE r.role_id IS NULL
  AND r.role_name IS NULL
ORDER BY r.id;

-- If a.role_id is NULL for any row above, do not run the UPDATE below for
-- that row -- it means the assignment itself is one of the two rows fixed
-- in section 1a, or is a genuinely different unmatched case; resolve that
-- one manually first (its role_key will show once section 1a's UPDATEs
-- above have run in the same session).

UPDATE wp_tmp_member_requests r
JOIN wp_tmp_role_assignments a ON a.id = r.assignment_id
SET r.role_id = a.role_id
WHERE r.role_id IS NULL
  AND r.role_name IS NULL
  AND a.role_id IS NOT NULL;

-- Verify: should return 0 rows (or only rows whose linked assignment
-- genuinely has no role_id -- investigate any that remain).
SELECT r.id, r.assignment_id, r.status, r.created_at
FROM wp_tmp_member_requests r
WHERE r.role_id IS NULL AND r.role_name IS NULL;
