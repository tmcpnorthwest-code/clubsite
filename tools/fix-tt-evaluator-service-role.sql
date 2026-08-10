-- ============================================================================
-- Fixes a discrepancy caught while migrating is_service_role() to
-- role_catalog: the legacy string-based is_service_role() (used for
-- TM-of-the-Month/Quarter recognition scoring) treats Table Topics
-- Evaluator as a service role but NOT Table Topics Speaker. The initial
-- role_catalog seed data (migrate_v250_seed_role_catalog()) had both set
-- to is_service_role=0, which would have silently changed scoring for any
-- member who's done the TT Evaluator role. Confirmed correct values
-- 2026-08-10: table_topics_evaluator=1, table_topics_speaker stays 0.
-- Safe to run multiple times.
-- ============================================================================

UPDATE wp_tmp_role_catalog
SET is_service_role = 1
WHERE role_key = 'table_topics_evaluator';

-- Verify.
SELECT role_key, display_name, is_service_role
FROM wp_tmp_role_catalog
WHERE role_key IN ('table_topics_evaluator', 'table_topics_speaker');
