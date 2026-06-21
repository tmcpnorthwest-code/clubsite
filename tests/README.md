# TMP Plugin Tests

Integration tests that run directly against the WordPress database via WP-CLI.
No mocking — real DB rows, real repository methods.

## Prerequisites

- WP-CLI installed and configured for this WordPress site
- Plugin active (`toastmasters-portal` in wp-content/plugins/)

## Run

```bash
# From the plugin directory (c:\Toast Masters\clubsite\)
wp eval-file tests/test-workflow.php --path="C:/path/to/wordpress"
```

Exit code 0 = all green. Exit code 1 = failures (listed at the bottom).

## What is tested

`test-workflow.php` covers the full meeting → request → approval pipeline:

| Suite | What |
|-------|------|
| 1 | Meeting created with correct role + evaluator slots |
| 2 | Test member fixtures (5 members, levels 0–2) |
| 3 | Members submit role requests (happy path) |
| 4 | Guard rails: level gate, idempotent re-submit, deadline slot hiding |
| 5 | Score calculation: priority weight, level bonus |
| 6 | VPE approves one winner → slot confirmed, others cascade-rejected |
| 7 | Double-approval blocked (member already has a role) |
| 8 | Bulk approval fills remaining open slots |
| 9 | Final state: zero pending, all 3 key slots filled |

## Test isolation

All test data is prefixed `[TEST]` in member names and the meeting theme.
`Fixtures::teardown()` deletes all created rows at the end of the run.

If the script crashes before teardown, clean up manually:
```sql
DELETE FROM wp_tmp_role_requests  WHERE meeting_id IN (SELECT id FROM wp_tmp_meetings WHERE theme LIKE '[TEST]%');
DELETE FROM wp_tmp_role_assignments WHERE meeting_id IN (SELECT id FROM wp_tmp_meetings WHERE theme LIKE '[TEST]%');
DELETE FROM wp_tmp_meetings WHERE theme LIKE '[TEST]%';
DELETE FROM wp_tmp_members  WHERE full_name LIKE '[TEST]%';
```
