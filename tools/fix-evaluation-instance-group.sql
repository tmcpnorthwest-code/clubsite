-- ============================================================================
-- Fixes a bug in the original migrate_v250_seed_agenda_template() seed data:
-- the "Evaluation" template row was tagged instance_group='speech_block',
-- which caused expand_agenda_template() to interleave it with the TMOD-intro
-- and Speech rows (producing "intro1,intro2,intro3,intro4,speech1,speech2..."
-- instead of the correct "intro1,speech1,intro2,speech2,...", with
-- Evaluations appearing later after Table Topics as originally designed).
--
-- This corrects the already-seeded row in wp_tmp_agenda_template_items to
-- instance_group='eval_block', matching the corrected seed data in
-- includes/class-tmp-activator.php (migrate_v250_seed_agenda_template()).
-- Safe to run multiple times.
-- ============================================================================

UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
SET ti.instance_group = 'eval_block'
WHERE rc.role_key = 'evaluator'
  AND ti.segment_label = 'Evaluation'
  AND ti.instance_group = 'speech_block';

-- Verify: should show instance_group = 'eval_block'.
SELECT ti.id, rc.role_key, ti.segment_label, ti.instance_group
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE rc.role_key = 'evaluator' AND ti.segment_label = 'Evaluation';
