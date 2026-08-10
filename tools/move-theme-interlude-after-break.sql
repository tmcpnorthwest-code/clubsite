-- ============================================================================
-- Product change (2026-08-10): move the "TMOD (Theme interlude)" row that
-- follows the prepared speeches to AFTER Break, immediately before Table
-- Topics Master — bridging from the break back into Table Topics, instead
-- of sitting before the post-speech Timer Report. Mirrors the corrected
-- seed data ordering in includes/class-tmp-activator.php
-- (migrate_v250_seed_agenda_template()).
--
-- New order: ...Speech N -> Timer (Report) -> Break (Networking) ->
-- Theme interlude -> Table Topics Master...
--
-- Confirmed via the 2026-08-10 verify output that current sort_orders are:
-- Theme interlude=180, Timer Report=190, Break=195, Table Topics Master=220.
-- This is a targeted sort_order swap, not a generic re-sort. Safe to run
-- multiple times.
-- ============================================================================

SET @template_id = (SELECT id FROM wp_tmp_agenda_template WHERE is_default = 1 LIMIT 1);

-- Timer (Report) moves into the old Theme interlude slot (180).
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
SET ti.sort_order = 180
WHERE ti.template_id = @template_id
  AND rc.role_key = 'timer'
  AND ti.segment_label = 'Report'
  AND ti.sort_order = 190;

-- Theme interlude moves to sit between Break (195) and Table Topics
-- Master (220).
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
SET ti.sort_order = 210
WHERE ti.template_id = @template_id
  AND rc.role_key = 'tmod'
  AND ti.segment_label = 'Theme interlude'
  AND ti.sort_order = 180;

-- Verify: full ordered list — should read ...Speech, Timer (Report),
-- Break (Networking), Theme interlude, Table Topics Master...
SELECT ti.sort_order, rc.role_key, ti.segment_label
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE ti.template_id = @template_id
ORDER BY ti.sort_order;
