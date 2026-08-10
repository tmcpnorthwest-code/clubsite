-- ============================================================================
-- Product change (2026-08-10): remove the "TMOD (Theme interlude)" row that
-- was gated on table_topics_master, appearing right after the Table Topics
-- Master's session. TTM's session now flows straight into "Table Topics
-- Evaluator (Table Topics Session Evaluation)" with no TMOD line in
-- between. Mirrors the corrected seed data in includes/class-tmp-
-- activator.php (migrate_v250_seed_agenda_template()).
--
-- Note: the earlier "Introduces Table Topics Master" removal (see
-- remove-ttm-intro-line.sql) already handled the intro side; this handles
-- the interlude AFTER the TTM session. There was never a separate "TMOD
-- introduces Table Topics Evaluator" row in the seed data, so nothing
-- further needs deleting there.
--
-- Safe to run multiple times. Does NOT touch any existing meeting's
-- role_assignments rows — only future rebuilds/new meetings are affected.
-- ============================================================================

DELETE ti FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Theme interlude'
  AND ti.requires_role_key = 'table_topics_master';

-- Verify: should return 0 rows.
SELECT ti.id, ti.segment_label, ti.requires_role_key
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Theme interlude'
  AND ti.requires_role_key = 'table_topics_master';
