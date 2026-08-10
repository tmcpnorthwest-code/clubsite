-- ============================================================================
-- Product change (2026-08-10): move "Break (Networking)" from before the
-- prepared speeches to after them — i.e. after the post-speech-block
-- "Timer (Report)" row and before "Table Topics Master (Table Topics
-- Session)". Mirrors the corrected seed data ordering in includes/class-
-- tmp-activator.php (migrate_v250_seed_agenda_template()).
--
-- This is a sort_order change, not a content change — reassigns
-- sort_order to every default-template row so Break's position shifts
-- without disturbing the relative order of everything else. Safe to run
-- multiple times (idempotent — recomputes the same final order each time).
-- ============================================================================

-- Step 1: pull the default template's current row order, but with the
-- Break row's position swapped to just after the post-speech "Timer
-- (Report)" row (which itself sits right after the speech_block group).
SET @template_id = (SELECT id FROM wp_tmp_agenda_template WHERE is_default = 1 LIMIT 1);

-- Verify template_id resolved before proceeding.
SELECT @template_id AS template_id;

-- Step 2: renumber. Uses a ranking trick: give every row its current
-- relative rank, but push the Break row's rank to sit immediately after
-- the last "Timer" row whose segment_label = 'Report' that appears BEFORE
-- Table Topics Master in original order (i.e. the post-speech-block Timer
-- Report, not the later post-Table-Topics Timer Report).
--
-- Simpler and less fragile than reasoning about ranks in SQL: since this
-- is a one-time reorder of a known, small (~35 row) template, do it as an
-- explicit two-step move instead of a generic re-sort.
--
-- 2a. Find Break's current sort_order and the post-speech-block Timer
--     Report's sort_order (the first "timer"/"Report" row that comes
--     BEFORE any "table_topics_master" row).
SELECT
    (SELECT ti.sort_order FROM wp_tmp_agenda_template_items ti
     JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
     WHERE ti.template_id = @template_id AND rc.role_key = 'break' LIMIT 1) AS break_sort_order,
    (SELECT ti.sort_order FROM wp_tmp_agenda_template_items ti
     JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
     WHERE ti.template_id = @template_id AND rc.role_key = 'timer' AND ti.segment_label = 'Report'
     ORDER BY ti.sort_order ASC LIMIT 1) AS first_timer_report_sort_order;

-- 2b. Move Break to sit just after that first Timer Report row: give Break
-- a sort_order between the first Timer Report row and whatever comes next
-- (the original speech-block start), then shift everything that was
-- between Break's old position and the Timer Report row back by one slot
-- (-1 step, using the existing *10 spacing so no collisions occur).
--
-- Concretely: every row whose sort_order is > break_sort_order AND <=
-- first_timer_report_sort_order moves down by 10 (fills the gap Break
-- leaves behind); Break itself gets first_timer_report_sort_order + 5
-- (lands immediately after Timer Report, before Table Topics Master,
-- without needing to touch any other row's order).
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN (
    SELECT
        (SELECT ti2.sort_order FROM wp_tmp_agenda_template_items ti2
         JOIN wp_tmp_role_catalog rc2 ON rc2.id = ti2.role_id
         WHERE ti2.template_id = @template_id AND rc2.role_key = 'break' LIMIT 1) AS break_sort_order,
        (SELECT ti2.sort_order FROM wp_tmp_agenda_template_items ti2
         JOIN wp_tmp_role_catalog rc2 ON rc2.id = ti2.role_id
         WHERE ti2.template_id = @template_id AND rc2.role_key = 'timer' AND ti2.segment_label = 'Report'
         ORDER BY ti2.sort_order ASC LIMIT 1) AS first_timer_report_sort_order
) bounds ON ti.template_id = @template_id
SET ti.sort_order = ti.sort_order - 10
WHERE rc.role_key != 'break'
  AND ti.sort_order > bounds.break_sort_order
  AND ti.sort_order <= bounds.first_timer_report_sort_order;

UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN (
    SELECT
        (SELECT ti2.sort_order FROM wp_tmp_agenda_template_items ti2
         JOIN wp_tmp_role_catalog rc2 ON rc2.id = ti2.role_id
         WHERE ti2.template_id = @template_id AND rc2.role_key = 'timer' AND ti2.segment_label = 'Report'
         ORDER BY ti2.sort_order ASC LIMIT 1) AS first_timer_report_sort_order
) bounds ON ti.template_id = @template_id
SET ti.sort_order = bounds.first_timer_report_sort_order + 5
WHERE rc.role_key = 'break';

-- Verify: full ordered list — Break should now appear right after the
-- first "Timer (Report)" row and right before "Table Topics Master
-- (Table Topics Session)".
SELECT ti.sort_order, rc.role_key, ti.segment_label
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE ti.template_id = @template_id
ORDER BY ti.sort_order;
