-- ============================================================================
-- Product change (2026-09-11): add a "Explains role" intro line for the
-- Active Listener role player, matching Timer/Ah-Counter/Grammarian/General
-- Evaluator, and tighten Grammarian's intro line from 3 to 2 minutes.
-- Mirrors the corrected seed data in includes/class-tmp-activator.php
-- (migrate_v250_seed_agenda_template()).
--
-- Safe to run multiple times (the INSERT is guarded). Does NOT touch any
-- existing meeting's role_assignments rows — only future rebuilds/new
-- meetings are affected.
-- ============================================================================

-- 1. Grammarian "Explains role and Word/Phrase of the Day" -> 2 minutes (was 3).
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
SET ti.default_duration_minutes = 2
WHERE t.is_default = 1
  AND rc.role_key = 'grammarian'
  AND ti.segment_label = 'Explains role and Word/Phrase of the Day';

-- 2. Insert "Active Listener - Explains role" right after Grammarian's intro
--    line, before General Evaluator's. Uses Grammarian's sort_order + 1 to
--    slot it in without renumbering the rest of the template.
INSERT INTO wp_tmp_agenda_template_items
    (template_id, role_id, segment_label, instance_group, sort_order,
     is_optional, requires_role_key, default_duration_minutes,
     default_timer_minutes, repeat_per_speech, created_at, updated_at)
SELECT
    g.template_id,
    al.id,
    'Explains role',
    NULL,
    g.sort_order + 1,
    0,
    NULL,
    2,
    NULL,
    0,
    NOW(),
    NOW()
FROM wp_tmp_agenda_template_items g
JOIN wp_tmp_role_catalog grc ON grc.id = g.role_id AND grc.role_key = 'grammarian'
JOIN wp_tmp_agenda_template t ON t.id = g.template_id AND t.is_default = 1
JOIN wp_tmp_role_catalog al ON al.role_key = 'active_listener'
WHERE g.segment_label = 'Explains role and Word/Phrase of the Day'
  AND NOT EXISTS (
      SELECT 1 FROM wp_tmp_agenda_template_items ti2
      JOIN wp_tmp_role_catalog rc2 ON rc2.id = ti2.role_id
      WHERE ti2.template_id = g.template_id
        AND rc2.role_key = 'active_listener'
        AND ti2.segment_label = 'Explains role'
  );

-- Verify: Grammarian intro should show 2 minutes.
SELECT ti.id, ti.segment_label, ti.default_duration_minutes
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1 AND rc.role_key = 'grammarian' AND ti.segment_label = 'Explains role and Word/Phrase of the Day';

-- Verify: exactly one Active Listener "Explains role" row, sorted right after Grammarian's intro.
SELECT ti.id, ti.sort_order, rc.role_key, ti.segment_label, ti.default_duration_minutes
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
JOIN wp_tmp_agenda_template t ON t.id = ti.template_id
WHERE t.is_default = 1
  AND rc.role_key IN ('grammarian', 'active_listener', 'general_evaluator')
ORDER BY ti.sort_order;
