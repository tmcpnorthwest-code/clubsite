-- ============================================================================
-- Phase-1 dry-run: classify existing role_name strings against role_catalog.
-- Written for MariaDB 10.0.5+ (confirmed target: 11.8.8-MariaDB).
--
-- WRITES NOTHING. Pure SELECT/report. Safe to run directly against
-- production (read-only), or against a mysqldump restored locally.
--
-- Mirrors TMP_Repository::backfill_classify_role_name() in PHP:
--   1. segment_label = trailing "(...)" text, if present
--   2. base = role_name with ALL parenthetical groups stripped + trimmed
--      (matches get_base_role_name()'s preg_replace('/\s*\(.*?\)\s*/', '', ...))
--   3. numbered roles ("Speaker 2", "Evaluator 1", "Ad Hoc Speaker 3",
--      "Fun Session 2") match by prefix + instance number
--   4. everything else matches by exact case-insensitive display_name
--   5. "Break", "Break (Networking)", etc. match the 'break' catalog row
--
-- MariaDB note: REGEXP_SUBSTR here is the 2-argument form (no occurrence/
-- match-type params like MySQL 8's version) and REGEXP_REPLACE has no
-- inline 'i' flag argument — case-insensitivity is done by LOWER()-ing
-- both sides before pattern matching instead.
--
-- MariaDB gotcha (verified 2026-08-10 against real data): REGEXP_SUBSTR()
-- returns an EMPTY STRING '', not NULL, when the pattern doesn't match a
-- non-NULL input. Every "num_prefix_lower"/"num_suffix" column below is
-- wrapped in NULLIF(..., '') for exactly this reason -- without it, "IS
-- NULL" branches meant to catch non-numbered roles (SAA, Timer, TMOD,
-- Table Topics Master, etc.) never fire, and the exact-match CTEs silently
-- match zero rows even though the strings are byte-identical. Do not
-- remove the NULLIF() calls.
--
-- USAGE
--   1. Replace `wp_` below with your actual $wpdb->prefix if different
--      (find/replace wp_tmp_ -> yourprefix_tmp_ throughout this file).
--   2. Run this file BEFORE ever calling the REST endpoint with
--      dry_run:false — review every row in the unmatched result sets first.
--   3. This assumes migrate_v250_seed_role_catalog() has already run (the
--      wp_tmp_role_catalog table is populated) — activate/update the plugin
--      once first if wp_tmp_role_catalog is empty.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Sanity check: catalog must be seeded before this script means anything.
-- ----------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM wp_tmp_role_catalog) AS catalog_row_count,
    IF((SELECT COUNT(*) FROM wp_tmp_role_catalog) = 0,
       'STOP: role_catalog is empty - activate/update the plugin first so migrate_v250_seed_role_catalog() runs.',
       'OK: catalog is seeded, proceed.'
    ) AS status;

-- ============================================================================
-- 1) wp_tmp_role_assignments
--    (numbered-slot logic applies: "Speaker 2", "Evaluator 1", etc.)
-- ============================================================================

-- 1a. Summary counts (matched vs. unmatched)
WITH classified AS (
    SELECT
        a.id,
        a.role_name,
        TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
    FROM wp_tmp_role_assignments a
    WHERE a.role_id IS NULL
),
classified2 AS (
    SELECT
        c.*,
        NULLIF(LOWER(REGEXP_SUBSTR(c.base, '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)')), '') AS num_prefix_lower,
        NULLIF(REGEXP_SUBSTR(c.base, '[0-9]+$'), '') AS num_suffix
    FROM classified c
),
matched_numbered AS (
    SELECT c.id, rc.id AS role_id, rc.role_key
    FROM classified2 c
    JOIN wp_tmp_role_catalog rc
      ON c.num_prefix_lower IS NOT NULL
     AND c.num_suffix IS NOT NULL
     AND LOWER(rc.display_name) = c.num_prefix_lower
),
matched_exact AS (
    SELECT c.id, rc.id AS role_id, rc.role_key
    FROM classified2 c
    JOIN wp_tmp_role_catalog rc
      ON c.num_prefix_lower IS NULL
     AND LOWER(rc.display_name) = LOWER(c.base)
),
matched_break AS (
    SELECT c.id, rc.id AS role_id, rc.role_key
    FROM classified2 c
    JOIN wp_tmp_role_catalog rc ON rc.role_key = 'break'
    WHERE c.num_prefix_lower IS NULL
      AND LOWER(c.base) LIKE 'break%'
      AND NOT EXISTS (
          SELECT 1 FROM wp_tmp_role_catalog rc2 WHERE LOWER(rc2.display_name) = LOWER(c.base)
      )
),
all_matched AS (
    SELECT id, role_id, role_key FROM matched_numbered
    UNION SELECT id, role_id, role_key FROM matched_exact
    UNION SELECT id, role_id, role_key FROM matched_break
)
SELECT
    'role_assignments' AS source_table,
    (SELECT COUNT(*) FROM classified2) AS total_unclassified_rows,
    (SELECT COUNT(DISTINCT id) FROM all_matched) AS matched,
    (SELECT COUNT(*) FROM classified2 c WHERE c.id NOT IN (SELECT id FROM all_matched)) AS unmatched;

-- 1b. Unmatched sample (role_name strings with no catalog match at all) -- review these
SELECT c.id, c.role_name, c.base
FROM (
    SELECT
        a.id,
        a.role_name,
        TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
    FROM wp_tmp_role_assignments a
    WHERE a.role_id IS NULL
) c
WHERE NOT EXISTS (
    SELECT 1 FROM wp_tmp_role_catalog rc
    WHERE LOWER(rc.display_name) = LOWER(REGEXP_SUBSTR(c.base, '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)'))
       OR LOWER(rc.display_name) = LOWER(c.base)
       OR (rc.role_key = 'break' AND LOWER(c.base) LIKE 'break%')
)
ORDER BY c.id
LIMIT 50;

-- 1c. Per-role breakdown of what WOULD be matched (final eyeball check)
SELECT
    COALESCE(rc.role_key, '(no catalog match)') AS role_key,
    COUNT(*) AS row_count
FROM wp_tmp_role_assignments a
LEFT JOIN wp_tmp_role_catalog rc ON (
    LOWER(rc.display_name) = LOWER(REGEXP_SUBSTR(
        TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')),
        '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)'
    ))
    OR LOWER(rc.display_name) = LOWER(TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')))
    OR (rc.role_key = 'break' AND LOWER(TRIM(REGEXP_REPLACE(a.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' '))) LIKE 'break%')
)
WHERE a.role_id IS NULL
GROUP BY role_key
ORDER BY row_count DESC;

-- ============================================================================
-- 2) wp_tmp_member_requests
--    Post-v170 rows store a generic role name (e.g. "Speaker") with no
--    parenthetical -- but see the note below for two categories of older
--    rows that don't follow that rule.
-- ============================================================================

-- NOTE: the v170 migration's backfill regex ('/\s+\d+(\s+\(.*\))?$/') only
-- strips a trailing number, optionally followed by a parenthetical -- for
-- singular roles with NO number (e.g. an assignment's "Sergeant at Arms
-- (Starts meeting)"), that regex matches nothing, so the FULL composite
-- string was written into role_name verbatim for those older rows. Also,
-- some very old rows have role_name = NULL outright (pre-dating v170
-- entirely). Both cases need the same paren-stripping as section 1's
-- role_assignments handling, not a flat exact match, to classify correctly.
SELECT
    'member_requests' AS source_table,
    COUNT(*) AS total_unclassified_rows,
    SUM(CASE WHEN rc.id IS NOT NULL THEN 1 ELSE 0 END) AS matched,
    SUM(CASE WHEN r.role_name IS NULL THEN 1 ELSE 0 END) AS null_role_name,
    SUM(CASE WHEN rc.id IS NULL AND r.role_name IS NOT NULL THEN 1 ELSE 0 END) AS unmatched
FROM wp_tmp_member_requests r
LEFT JOIN wp_tmp_role_catalog rc
  ON LOWER(rc.display_name) = LOWER(TRIM(REGEXP_REPLACE(r.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')))
WHERE r.role_id IS NULL;

-- Rows with role_name = NULL -- pre-v170 data, never touched by that
-- migration's join-based backfill (it only updates rows already matched
-- to an existing assignment_id). These need a manual decision: either
-- leave role_id NULL permanently (harmless -- old rows, request flow has
-- moved on) or, if still relevant, manually assign a role_id.
SELECT r.id, r.meeting_id, r.member_id, r.assignment_id, r.status, r.created_at
FROM wp_tmp_member_requests r
WHERE r.role_id IS NULL AND r.role_name IS NULL
ORDER BY r.id
LIMIT 50;

-- Rows with a non-NULL role_name that still doesn't match any catalog
-- entry after paren-stripping -- review these individually.
SELECT r.id, r.role_name,
       TRIM(REGEXP_REPLACE(r.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')) AS base
FROM wp_tmp_member_requests r
LEFT JOIN wp_tmp_role_catalog rc
  ON LOWER(rc.display_name) = LOWER(TRIM(REGEXP_REPLACE(r.role_name, '[[:space:]]*\\([^()]*\\)[[:space:]]*', ' ')))
WHERE r.role_id IS NULL AND r.role_name IS NOT NULL AND rc.id IS NULL
ORDER BY r.id
LIMIT 50;

-- ============================================================================
-- 3) wp_tmp_participation_history
--    CORRECTED: history rows DO carry an instance number ("Speaker 1",
--    "Evaluator 3") -- get_base_role_name() (called at write time in
--    save_assignment()) only strips the parenthetical segment, NOT the
--    trailing number, so the same numbered-slot matching as section 1
--    is required here too. An earlier version of this script assumed
--    history was already fully base-stripped; verified wrong against
--    real production data (2026-08-10) -- do not reintroduce that
--    assumption.
-- ============================================================================

WITH h_classified AS (
    SELECT
        h.id,
        h.role_name,
        TRIM(h.role_name) AS base,
        NULLIF(LOWER(REGEXP_SUBSTR(TRIM(h.role_name), '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)')), '') AS num_prefix_lower,
        NULLIF(REGEXP_SUBSTR(TRIM(h.role_name), '[0-9]+$'), '') AS num_suffix
    FROM wp_tmp_participation_history h
    WHERE h.role_id IS NULL
),
h_matched_numbered AS (
    SELECT c.id, rc.id AS role_id, rc.role_key
    FROM h_classified c
    JOIN wp_tmp_role_catalog rc
      ON c.num_prefix_lower IS NOT NULL
     AND c.num_suffix IS NOT NULL
     AND LOWER(rc.display_name) = c.num_prefix_lower
),
h_matched_exact AS (
    SELECT c.id, rc.id AS role_id, rc.role_key
    FROM h_classified c
    JOIN wp_tmp_role_catalog rc
      ON c.num_prefix_lower IS NULL
     AND LOWER(rc.display_name) = LOWER(c.base)
),
h_all_matched AS (
    SELECT id, role_id, role_key FROM h_matched_numbered
    UNION SELECT id, role_id, role_key FROM h_matched_exact
)
SELECT
    'participation_history' AS source_table,
    (SELECT COUNT(*) FROM h_classified) AS total_unclassified_rows,
    (SELECT COUNT(DISTINCT id) FROM h_all_matched) AS matched,
    (SELECT COUNT(*) FROM h_classified c WHERE c.id NOT IN (SELECT id FROM h_all_matched)) AS unmatched;

-- 3b. Unmatched sample -- review these
-- (self-contained statement -- CTEs from the query above don't carry over)
SELECT c.id, c.role_name, c.base
FROM (
    SELECT
        h.id,
        h.role_name,
        TRIM(h.role_name) AS base,
        NULLIF(LOWER(REGEXP_SUBSTR(TRIM(h.role_name), '^(Speaker|Evaluator|Ad Hoc Speaker|Fun Session)')), '') AS num_prefix_lower
    FROM wp_tmp_participation_history h
    WHERE h.role_id IS NULL
) c
WHERE NOT EXISTS (
    SELECT 1 FROM wp_tmp_role_catalog rc
    WHERE (c.num_prefix_lower IS NOT NULL AND LOWER(rc.display_name) = c.num_prefix_lower)
       OR (c.num_prefix_lower IS NULL AND LOWER(rc.display_name) = LOWER(c.base))
)
ORDER BY c.id
LIMIT 50;

-- (3c diagnostic removed -- root cause confirmed as the REGEXP_SUBSTR
-- empty-string-vs-NULL gotcha documented at the top of this file, fixed
-- via NULLIF() throughout. The byte-identical hex dump comparison that
-- diagnosed it is no longer needed once you've re-run 3a/3b post-fix.)

-- ============================================================================
-- Next step: after reviewing every unmatched row above --
--   - if a row is a legitimate historical role missing from the catalog,
--     add it as a new wp_tmp_role_catalog row (via a follow-up migration
--     or a manual INSERT) and re-run this script,
--   - if it's a stray/hand-edited string (recall the old free-text
--     "edit agenda line" admin input), it's fine to leave role_id NULL --
--     the old role_name column remains authoritative for that row.
-- Only once unmatched is at or near zero should you call:
--   POST /toastmasters/v1/admin/backfill-role-ids
--   { "dry_run": false, "confirm": true }
-- ============================================================================
