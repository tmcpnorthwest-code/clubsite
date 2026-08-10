-- ============================================================================
-- Product change (2026-08-10): the default agenda template should NOT have
-- a standalone "Toastmaster of the Day (Introduces Table Topics Master)"
-- line — go straight from Timer's Report into the Table Topics Master's
-- session. This mirrors the corrected seed data in
-- includes/class-tmp-activator.php (migrate_v250_seed_agenda_template()).
--
-- Deletes the already-seeded row from wp_tmp_agenda_template_items on the
-- default template. Safe to run multiple times (no-op if already removed).
-- Does NOT touch any existing meeting's role_assignments rows — only future
-- rebuilds/new meetings will stop generating this line.
-- ============================================================================

DELETE ti FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Introduces Table Topics Master';

-- Verify: should return 0 rows.
SELECT ti.id, ti.segment_label
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1 AND rc.role_key = 'tmod' AND ti.segment_label = 'Introduces Table Topics Master';
