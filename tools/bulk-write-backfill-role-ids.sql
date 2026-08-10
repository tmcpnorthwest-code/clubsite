-- ============================================================================
-- Bulk WRITE: sets role_id (and, for role_assignments, segment_label /
-- instance_number) on every remaining row where role_id IS NULL, using the
-- exact same classification rules as TMP_Repository::backfill_classify_role_name()
-- / the dry-run script -- numbered-role prefix match first, then exact
-- display_name match, then the 'break' special case. Only writes rows that
-- classify as an unambiguous single match; anything else is left untouched
-- (never guesses).
--
-- This is the SQL equivalent of:
--   POST /toastmasters/v1/admin/backfill-role-ids
--   { "dry_run": false, "confirm": true }
--
-- Safe to run any time -- every UPDATE is scoped by "role_id IS NULL", so
-- rows already backfilled (via the REST endpoint, the dry-run classifier,
-- or manual-backfill-remaining-rows.sql) are simply skipped, not re-written.
--
-- Given the 2026-08-10 dry-run results, this should affect 0 rows in
-- role_assignments and participation_history (already fully backfilled)
-- and 0 rows in member_requests (the remaining 16 have orphaned
-- assignment_id references with no recoverable role -- see
-- manual-backfill-remaining-rows.sql's section 2 note). Re-run any time
-- after adding new meetings/data to catch newly-created unclassified rows.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) wp_tmp_role_assignments -- also writes segment_label / instance_number.
-- ----------------------------------------------------------------------------

-- 1a. Numbered roles: "Speaker 2", "Evaluator 1", "Ad Hoc Speaker 3", "Fun Session 2".
UPDATE wp_tmp_role_assignments a
JOIN (
    SELECT
        x.id,
        rc.id AS matched_role_id,
        CAST(REGEXP_SUBSTR(x.base, '[0-9]+$') AS UNSIGNED) AS matched_instance,
        REGEXP_REPLACE(x.role_name, '^.*\\(([^()]*)\\)\\s*$', '\\1') AS matched_segment
    FROM (
        SELECT
            id,
            role_name,
            TRIM(REGEXP_REPLACE(role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
        FROM wp_tmp_role_assignments
        WHERE role_id IS NULL
    ) x
    JOIN wp_tmp_role_catalog rc
      ON LOWER(rc.display_name) = LOWER(NULLIF(REGEXP_SUBSTR(x.base, '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)'), ''))
     AND NULLIF(REGEXP_SUBSTR(x.base, '[0-9]+$'), '') IS NOT NULL
) m ON m.id = a.id
SET a.role_id         = m.matched_role_id,
    a.instance_number  = m.matched_instance,
    a.segment_label    = CASE WHEN a.role_name REGEXP '\\([^()]*\\)[[:space:]]*$' THEN m.matched_segment ELSE NULL END
WHERE a.role_id IS NULL;

-- 1b. Exact display_name match (non-numbered roles: SAA, TMOD, Timer, etc.)
UPDATE wp_tmp_role_assignments a
JOIN (
    SELECT
        x.id,
        rc.id AS matched_role_id,
        REGEXP_REPLACE(x.role_name, '^.*\\(([^()]*)\\)\\s*$', '\\1') AS matched_segment
    FROM (
        SELECT
            id,
            role_name,
            TRIM(REGEXP_REPLACE(role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
        FROM wp_tmp_role_assignments
        WHERE role_id IS NULL
    ) x
    JOIN wp_tmp_role_catalog rc ON LOWER(rc.display_name) = LOWER(x.base)
) m ON m.id = a.id
SET a.role_id      = m.matched_role_id,
    a.segment_label = CASE WHEN a.role_name REGEXP '\\([^()]*\\)[[:space:]]*$' THEN m.matched_segment ELSE NULL END
WHERE a.role_id IS NULL;

-- 1c. "Break", "Break (Networking)", etc. -- only if not already caught above.
UPDATE wp_tmp_role_assignments a
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'break'
SET a.role_id      = rc.id,
    a.segment_label = CASE WHEN a.role_name REGEXP '\\([^()]*\\)[[:space:]]*$'
                            THEN REGEXP_REPLACE(a.role_name, '^.*\\(([^()]*)\\)\\s*$', '\\1')
                            ELSE NULL END
WHERE a.role_id IS NULL
  AND LOWER(TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' '))) LIKE 'break%';

-- ----------------------------------------------------------------------------
-- 2) wp_tmp_member_requests -- role_id only (this table has no
--    segment_label/instance_number columns; requests store the generic
--    role type).
-- ----------------------------------------------------------------------------

-- 2a. Numbered roles.
UPDATE wp_tmp_member_requests r
JOIN (
    SELECT x.id, rc.id AS matched_role_id
    FROM (
        SELECT id, TRIM(REGEXP_REPLACE(role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
        FROM wp_tmp_member_requests
        WHERE role_id IS NULL AND role_name IS NOT NULL
    ) x
    JOIN wp_tmp_role_catalog rc
      ON LOWER(rc.display_name) = LOWER(NULLIF(REGEXP_SUBSTR(x.base, '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)'), ''))
     AND NULLIF(REGEXP_SUBSTR(x.base, '[0-9]+$'), '') IS NOT NULL
) m ON m.id = r.id
SET r.role_id = m.matched_role_id
WHERE r.role_id IS NULL;

-- 2b. Exact display_name match.
UPDATE wp_tmp_member_requests r
JOIN (
    SELECT x.id, rc.id AS matched_role_id
    FROM (
        SELECT id, TRIM(REGEXP_REPLACE(role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
        FROM wp_tmp_member_requests
        WHERE role_id IS NULL AND role_name IS NOT NULL
    ) x
    JOIN wp_tmp_role_catalog rc ON LOWER(rc.display_name) = LOWER(x.base)
) m ON m.id = r.id
SET r.role_id = m.matched_role_id
WHERE r.role_id IS NULL;

-- 2c. "Break" variants.
UPDATE wp_tmp_member_requests r
JOIN wp_tmp_role_catalog rc ON rc.role_key = 'break'
SET r.role_id = rc.id
WHERE r.role_id IS NULL
  AND r.role_name IS NOT NULL
  AND LOWER(TRIM(REGEXP_REPLACE(r.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' '))) LIKE 'break%';

-- (role_name IS NULL rows are intentionally never touched here -- see
-- manual-backfill-remaining-rows.sql section 2 for why the 16 known such
-- rows can't be recovered.)

-- ----------------------------------------------------------------------------
-- 3) wp_tmp_participation_history -- role_id only (history rows carry an
--    instance number in the string but no separate column for it).
-- ----------------------------------------------------------------------------

-- 3a. Numbered roles: "Speaker 1", "Evaluator 3", etc.
UPDATE wp_tmp_participation_history h
JOIN (
    SELECT x.id, rc.id AS matched_role_id
    FROM (
        SELECT id, TRIM(role_name) AS base
        FROM wp_tmp_participation_history
        WHERE role_id IS NULL
    ) x
    JOIN wp_tmp_role_catalog rc
      ON LOWER(rc.display_name) = LOWER(NULLIF(REGEXP_SUBSTR(x.base, '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)'), ''))
     AND NULLIF(REGEXP_SUBSTR(x.base, '[0-9]+$'), '') IS NOT NULL
) m ON m.id = h.id
SET h.role_id = m.matched_role_id
WHERE h.role_id IS NULL;

-- 3b. Exact display_name match.
UPDATE wp_tmp_participation_history h
JOIN (
    SELECT x.id, rc.id AS matched_role_id
    FROM (
        SELECT id, TRIM(role_name) AS base
        FROM wp_tmp_participation_history
        WHERE role_id IS NULL
    ) x
    JOIN wp_tmp_role_catalog rc ON LOWER(rc.display_name) = LOWER(x.base)
) m ON m.id = h.id
SET h.role_id = m.matched_role_id
WHERE h.role_id IS NULL;

-- ----------------------------------------------------------------------------
-- Final verification -- run after the above. Expect:
--   role_assignments:      0 unmatched
--   participation_history: 0 unmatched
--   member_requests:       16 unmatched (role_name IS NULL, orphaned
--                           assignment_id, documented and accepted)
-- ----------------------------------------------------------------------------
SELECT 'role_assignments' AS tbl, COUNT(*) AS still_unmatched FROM wp_tmp_role_assignments WHERE role_id IS NULL
UNION ALL
SELECT 'participation_history', COUNT(*) FROM wp_tmp_participation_history WHERE role_id IS NULL
UNION ALL
SELECT 'member_requests', COUNT(*) FROM wp_tmp_member_requests WHERE role_id IS NULL;
