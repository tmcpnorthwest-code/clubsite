# Pathways Code Gaps vs TI Guidelines

**Source:** d3toastmasters.org (effective Oct 2025)
**Audit date:** 2026-06-18
**Files reviewed:** `class-tmp-repository.php`, `class-tmp-activator.php`

---

## Gap 1 — L5 Missing Optional "Choose One" Role Requirement

**File:** `includes/class-tmp-repository.php` — `get_level_requirements()`

**Current L5 entry:**
```php
5 => [
    ['type' => 'role', 'roles' => ['Toastmaster of the Day'], 'min' => 2, ...],
    ['type' => 'role', 'roles' => ['General Evaluator'],      'min' => 2, ...],
    ['type' => 'role', 'roles' => ['Evaluator'],              'min' => 2, ...],
    ['type' => 'presentation', 'series' => 'Successful Club Series', ...],
    ['type' => 'presentation', 'series' => 'Leadership Excellence Series', ...],
],
```

**What's missing:**
TI mandates a fourth role requirement at L5: at least one from {TT Speaker, TT Master, Ah-Counter, Timer, Grammarian, Club Mentor, Specialized Role, or repeat above}. This optional group is completely absent from the level progress checker.

Note: Club Mentor is a non-meeting mentorship role with no current system tracking (see Gap 3). Exclude it from the `role_or` array until tracking is in place.

**Fix:** Add to the L5 array:
```php
['type' => 'role_or', 'roles' => ['Table Topics Speaker', 'Table Topics Master', 'Ah-Counter', 'Timer', 'Grammarian', 'Specialized Role'], 'min' => 1, 'label' => 'One additional role (TT Speaker, TTM, Ah-Counter, Timer, Grammarian, or Specialized Role)'],
```

---

## Gap 2 — 'Table Topics Speaker' Not in `get_standard_roles()`

**File:** `includes/class-tmp-repository.php` — `get_standard_roles()`

**Current:** `Table Topics Speaker` is referenced in `get_level_requirements()` (L1 mandatory, L3 option, L4 option, L5 option) but is **not listed in `get_standard_roles()`**, meaning:
- It cannot be added to a meeting agenda as a named slot.
- VPE cannot assign a member to it.
- The participation_history record cannot be created via normal workflow.

**Fix:** Add to `get_standard_roles()`:
```php
'Table Topics Speaker' => 'TT Speaker',
```
Also support 1–2 TT Speaker slots on an agenda for members who formally request to give table topics for progression credit.

---

## Gap 3 — No Tracking for Non-Meeting Mentorship Roles (Introductory Mentor, Club Mentor)

**Introductory Mentor** counts toward the L3 optional role group. **Club Mentor** counts toward the L5 optional role group. Neither is a meeting role — they are VPE-assigned mentorship roles that happen outside the meeting agenda flow. Neither can be recorded in `participation_history` via the normal assignment workflow.

**Current state:** No tracking mechanism exists for either role. The L3 optional group in `get_level_requirements()` references `Introductory Mentor`, so when computing a member's L3 gap it will never be satisfied through existing data pathways.

**Fix options (pick one):**
1. Allow VPE to manually log a non-meeting participation record — add a "Log mentorship activity" action on the member profile that writes to `participation_history` with a null or placeholder `meeting_id`.
2. Add a separate `wp_tmp_mentorship_log` table and extend `get_member_level_gaps()` to check it alongside `participation_history`.

Until a fix is in place, `get_level_requirements()` for L3 should note that Introductory Mentor gap-satisfaction requires manual VPE override. For L5, Club Mentor should not be listed in the `role_or` array (per Gap 1) since there is no way to track it.

---

## Gap 4 — `get_next_role_recommendations()` Incomplete for L3/L4

**File:** `includes/class-tmp-repository.php` — `get_next_role_recommendations()` (around line 1023)

**Current L3 recommendation** only surfaces TMOD and Successful Club Series presentation. Missing: the L3 optional group (TTM / TT Speaker). Introductory Mentor should not be surfaced here as it is not a meeting role.

**Current L4 recommendation** only surfaces GE and Better Speaker Series. Missing: TMOD (mandatory at L4), Evaluator (mandatory at L4), and the optional group (TT Speaker / TTM / Specialized Role).

**Fix:** Update each `elseif ($level === N)` block to reflect the full mandatory + optional lists from `get_level_requirements()` rather than a hand-coded partial list. Skip non-meeting roles (Introductory Mentor) in the meeting-role recommendations.

---

## Gap 5 — L1 Ordering Constraint Not Surfaced in Progress Tracking

**Status:** Partially implemented.

`check_suitability()` correctly blocks Ice Breaker if TT Speaker hasn't been done. However `get_member_level_gaps()` does not flag the ordering — if a member has done neither, the gap report only shows "Table Topics Speaker" as missing with no note that it must precede Ice Breaker.

**Fix (low priority):** In the gap result for L1 TT Speaker, add `'ordering_note' => 'Must be completed before your Ice Breaker speech'` and surface it in the frontend gap display.

---

## Summary Table

| # | Gap | Severity | File | Status |
|---|---|---|---|---|
| 1 | L5 optional role group missing from `get_level_requirements()` | High | `class-tmp-repository.php:136` | Not fixed |
| 2 | 'Table Topics Speaker' not in `get_standard_roles()` | High | `class-tmp-repository.php:84` | Not fixed |
| 3 | No tracking for Introductory Mentor (L3) and Club Mentor (L5) — non-meeting roles | Medium | No tracking table/mechanism exists | Not fixed |
| 4 | `get_next_role_recommendations()` incomplete for L3/L4 | Medium | `class-tmp-repository.php:~1023` | Not fixed |
| 5 | L1 ordering note missing from gap report | Low | `class-tmp-repository.php:156` | Partial |

---

## What Is Correctly Implemented

- `level_at_completion` recorded per participation history row — level-scoped tracking works ✓
- `presentation_series` column in both `role_assignments` and `participation_history` ✓
- L1 Ice Breaker blocked by `check_suitability()` until TT Speaker done ✓
- `get_level_requirements()` structurally correct for L1–L4 (except L5 optional group) ✓
- `get_member_level_gaps()` uses `role_or` logic correctly ✓
- TMOD gate of L2+ is correct (member working in L3 has `level_completed = 2`) ✓
- GE gate of L2+ is a conscious club choice (allows broader exposure; `level_at_completion` ensures GE done at wrong level won't satisfy L4 requirement) ✓
- Ah-Counter name normalized to 'Ah-Counter' (hyphen) ✓
- Mentor eligibility gating (L2+, active, paid) already enforced in member assignment ✓
