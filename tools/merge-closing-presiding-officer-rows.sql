-- ============================================================================
-- Product change (2026-08-10): merge the three closing Presiding Officer
-- rows ("Address and Guest Feedback" 5m, "Guest Feedback and
-- Announcements" 5m, "Concludes the meeting" 1m) into a single row,
-- "Closing Remarks, Feedback and Announcements", 6 minutes. Mirrors the
-- corrected seed data in includes/class-tmp-activator.php
-- (migrate_v250_seed_agenda_template()).
--
-- Reuses the first row's id/sort_order (renames + retimes it in place) and
-- deletes the other two, so no renumbering of surrounding rows is needed.
-- Safe to run multiple times.
-- ============================================================================

SET @template_id = (SELECT id FROM wp_tmp_agenda_template WHERE is_default = 1 LIMIT 1);

-- 1. Rename/retime "Address and Guest Feedback" -> the merged row.
UPDATE wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
SET ti.segment_label = 'Closing Remarks, Feedback and Announcements',
    ti.default_duration_minutes = 6
WHERE ti.template_id = @template_id
  AND rc.role_key = 'presiding_officer'
  AND ti.segment_label = 'Address and Guest Feedback';

-- 2. Delete the other two, now-redundant rows.
DELETE ti FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE ti.template_id = @template_id
  AND rc.role_key = 'presiding_officer'
  AND ti.segment_label IN ('Guest Feedback and Announcements', 'Concludes the meeting');

-- Verify: should show exactly one merged row, 6 minutes.
SELECT ti.id, ti.sort_order, ti.segment_label, ti.default_duration_minutes
FROM wp_tmp_agenda_template_items ti
JOIN wp_tmp_role_catalog rc ON rc.id = ti.role_id
WHERE ti.template_id = @template_id AND rc.role_key = 'presiding_officer'
ORDER BY ti.sort_order;
