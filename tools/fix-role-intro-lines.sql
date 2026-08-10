-- ============================================================================
-- Product change (2026-08-10): role-player introductions no longer have a
-- separate "TMOD introduces X" line before each role player's own segment.
-- Instead each role player (Timer, Ah-Counter, Grammarian, General
-- Evaluator) gets a single row that covers both being introduced and
-- explaining their role. "Introduces the theme" also extends from 2 to 4
-- minutes. Mirrors the corrected seed data in includes/class-tmp-
-- activator.php (migrate_v250_seed_agenda_template()).
--
-- Safe to run multiple times. Does NOT touch any existing meeting's
-- role_assignments rows — only future rebuilds/new meetings are affected.
-- ============================================================================

-- 1. Remove the 4 standalone "TMOD introduces <role player>" rows.
DELETE ti FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label IN ('Introduces Timer', 'Introduces Ah-Counter', 'Introduces Grammarian', 'Introduces General Evaluator');

-- 2. "Introduces the theme" -> 4 minutes (was 2).
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
SET ti.default_duration_minutes = 4
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Introduces the theme';

-- Verify: should return 0 rows (the 4 intro lines are gone).
SELECT ti.id, ti.segment_label
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key = 'tmod'
  AND ti.segment_label IN ('Introduces Timer', 'Introduces Ah-Counter', 'Introduces Grammarian', 'Introduces General Evaluator');

-- Verify: should show default_duration_minutes = 4.
SELECT ti.id, ti.segment_label, ti.default_duration_minutes
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1 AND rc.role_key = 'tmod' AND ti.segment_label = 'Introduces the theme';
