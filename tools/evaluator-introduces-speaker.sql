-- ============================================================================
-- Product change (2026-08-10): the speech-block's opening line changes from
-- a TMOD-combined announcement ("Toastmaster of the Day / Introduces
-- Evaluator N and Speaker N") to the Evaluator's own introduction
-- ("Evaluator N / Introduces speaker"). This matches the pattern that
-- save_meeting()'s edit-path (the "add more speech slots" logic) already
-- used before this rewrite — the Evaluator personally introduces the
-- speaker they'll be evaluating, not TMOD. Mirrors the corrected seed data
-- in includes/class-tmp-activator.php (migrate_v250_seed_agenda_template()).
--
-- Changes the existing 'tmod / Introduces Evaluator and Speaker' row (the
-- template row, not per-meeting instances) to 'evaluator / Introduces
-- speaker', keeping its position (sort_order) in the speech_block group —
-- expand_agenda_template() will now generate "Evaluator N (Introduces
-- speaker)" before each "Speaker N (Speech)" instead of the old TMOD line.
--
-- Safe to run multiple times. Does NOT touch any existing meeting's
-- role_assignments rows — only future rebuilds/new meetings are affected.
-- ============================================================================

SET @template_id = (SELECT id FROM wp_tmp_agenda_template WHERE is_default = 1 LIMIT 1);
SET @evaluator_role_id = (SELECT id FROM wp_tmp_role_catalog WHERE role_key = 'evaluator' LIMIT 1);

UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
SET ti.role_id = @evaluator_role_id,
    ti.segment_label = 'Introduces speaker',
    ti.default_duration_minutes = 2
WHERE ti.template_id = @template_id
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Introduces Evaluator and Speaker'
  AND ti.instance_group = 'speech_block';

-- Verify: should show role_key = 'evaluator', segment_label = 'Introduces speaker'.
SELECT ti.id, ti.sort_order, rc.role_key, ti.segment_label, ti.instance_group, ti.default_duration_minutes
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE ti.template_id = @template_id AND ti.instance_group = 'speech_block'
ORDER BY ti.sort_order;
