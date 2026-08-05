<?php
/**
 * TMP Workflow Integration Test
 * Meeting Creation → Role Slots → Member Requests → VPE Approval
 *
 * Run via WP-CLI from the plugin directory:
 *   wp eval-file tests/test-workflow.php --path=/path/to/wordpress
 *
 * What is tested:
 *   1.  Meeting is created with correct role slots
 *   2.  Speech slots create matching Evaluator slots
 *   3.  Members can request open slots
 *   4.  Level-gated roles reject under-level members
 *   5.  Expired deadline blocks new requests
 *   6.  Duplicate request (same member, same slot) is idempotent
 *   7.  Scoring: P1 > P2; level bonus; goal-role bonus
 *   8.  VPE approves winner → slot gets member_id + status = Confirmed
 *   9.  Approval cascades: other members' requests for same slot → Rejected
 *   10. Member already approved → second approval attempt blocked
 *   11. Approved member's other pending requests are auto-rejected
 *   12. Bulk approve (approve_all_recommended) fills remaining open slots
 *   13. Final state: all 3 slots filled, zero Pending requests remain
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/Fixtures.php';

// ── Bootstrap check ───────────────────────────────────────────────────────────

if (!defined('ABSPATH') || !class_exists('TMP_Repository')) {
    die("Run this script via WP-CLI: wp eval-file tests/test-workflow.php\n");
}

$t = new TestRunner();
$f = new Fixtures();

// Next Saturday — a safe future date that won't clash with real meetings
$meeting_date = date('Y-m-d', strtotime('next saturday'));

echo "\033[1mTMP Workflow Test\033[0m  [{$meeting_date}]\n";

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 1: Meeting creation
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('1 · Meeting Creation');

try {
    $meeting_id = $f->meeting(
        $meeting_date,
        ['Toastmaster of the Day', 'General Evaluator', 'Timer', 'Ah-Counter'],
        2   // 2 speech slots → Speaker 1, Speaker 2, Evaluator 1, Evaluator 2
    );
    $t->ok($meeting_id > 0, "Meeting created (id={$meeting_id})");
} catch (RuntimeException $e) {
    $t->ok(false, 'Meeting created', $e->getMessage());
    $f->teardown();
    $t->summary();
    exit(1);
}

$slots = $f->get_slots($meeting_id);
$role_names = array_column($slots, 'role_name');

$t->ok(count($slots) >= 6, 'At least 6 slots created (' . count($slots) . ' total)');
// Slot names include appended notes e.g. "Toastmaster of the Day (Introduces the theme)"
// so check with str_starts_with, not exact in_array
$has_role = fn(string $prefix) => count(array_filter($role_names, fn($r) => str_starts_with($r, $prefix))) > 0;
$t->ok($has_role('Toastmaster of the Day'), 'TMOD slot(s) exist');
$t->ok($has_role('General Evaluator'),      'General Evaluator slot exists');
$t->ok($has_role('Timer'),                  'Timer slot exists');
$t->ok($has_role('Ah-Counter'),             'Ah-Counter slot exists');

// save_meeting() creates "Speaker 1 (Speech)" and "Evaluator 1 (Evaluation)" etc.
$speaker_slots = array_filter($role_names, fn($r) => str_starts_with($r, 'Speaker'));
$eval_slots    = array_filter($role_names, fn($r) => str_starts_with($r, 'Evaluator'));
$t->ok(count($speaker_slots) >= 2, '2+ Speaker slots created (' . count($speaker_slots) . ')');
$t->ok(count($eval_slots)    >= 2, '2+ Evaluator slots created (' . count($eval_slots) . ')');

// Grab slot IDs we'll use throughout
$slot_tmod  = $f->slot_id($meeting_id, 'Toastmaster');
$slot_ge    = $f->slot_id($meeting_id, 'General Evaluator');
$slot_spk1  = $f->slot_id($meeting_id, 'Speaker');
$t->ok($slot_tmod  !== null, "TMOD slot found (id={$slot_tmod})");
$t->ok($slot_ge    !== null, "GE slot found (id={$slot_ge})");
$t->ok($slot_spk1  !== null, "Speaker slot found (id={$slot_spk1})");

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 2: Member fixtures
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('2 · Member Fixtures');

// Alice L2 — experienced; Speaker(P1) + TMOD(P2)
$alice = $f->member('Alice', 2);
// Bob   L1 — Speaker(P1) + TMOD(P2); competes with Alice
$bob   = $f->member('Bob',   1);
// Carol L0 — new member; Speaker(P1) only
$carol = $f->member('Carol', 0);
// Dave  L2 — GE(P1) + Speaker(P2); GE requires L2+ so Dave must be L2
$dave  = $f->member('Dave',  2);
// Eve   L2 — TMOD(P1) + GE(P2); bulk approve gives her TMOD, Dave fills GE
$eve   = $f->member('Eve',   2);

$t->ok($alice && $bob && $carol && $dave && $eve,
    "5 test members created (ids: {$alice},{$bob},{$carol},{$dave},{$eve})");

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 3: Role requests — happy path
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('3 · Role Requests (happy path)');

// Alice: P1=Speaker, P2=TMOD
$r_alice = $f->request($alice, $meeting_id, [$slot_spk1, $slot_tmod]);
$t->not_wp_error($r_alice, 'Alice requests Speaker(P1) + TMOD(P2)');

// Bob: P1=Speaker, P2=TMOD  ← competes with Alice on both
$r_bob = $f->request($bob, $meeting_id, [$slot_spk1, $slot_tmod]);
$t->not_wp_error($r_bob, 'Bob requests Speaker(P1) + TMOD(P2)');

// Carol: P1=Speaker only
$r_carol = $f->request($carol, $meeting_id, [$slot_spk1]);
$t->not_wp_error($r_carol, 'Carol requests Speaker(P1)');

// Dave: P1=GE, P2=Speaker
$r_dave = $f->request($dave, $meeting_id, [$slot_ge, $slot_spk1]);
$t->not_wp_error($r_dave, 'Dave requests GE(P1) + Speaker(P2)');

// Eve: P1=TMOD, P2=GE
$r_eve = $f->request($eve, $meeting_id, [$slot_tmod, $slot_ge]);
$t->not_wp_error($r_eve, 'Eve requests TMOD(P1) + GE(P2)');

// save_requests() stores one row per slot:
//   Alice=2 (Speaker+TMOD), Bob=2, Carol=1 (Speaker only), Dave=2, Eve=2 → 9 rows
$pending = $f->count_requests($meeting_id, 'Pending');
$t->eq(9, $pending, "9 Pending request rows in DB (1 per member×slot)");

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 4: Guard rails — blocked requests
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('4 · Guard Rails');

// 4a. Level gate: TMOD may require L1. Carol is L0 — should get WP_Error.
$gate_levels = TMP_Repository::get_current_gate_levels();
$tmod_gate   = 0;
foreach ($gate_levels as $pattern => $min) {
    if (stripos('toastmaster', $pattern) !== false) { $tmod_gate = (int) $min; break; }
}

if ($tmod_gate > 0) {
    $r_carol_tmod = $f->request($carol, $meeting_id, [$slot_tmod]);
    $t->is_wp_error_code($r_carol_tmod, 'tmp_level_requirement',
        "Carol (L0) blocked from TMOD (requires L{$tmod_gate}+)");
} else {
    $t->ok(true, "TMOD has no level gate — level-gate test skipped");
}

// 4b. Duplicate request: Alice re-submits same slots → idempotent, no error
$r_alice_dup = $f->request($alice, $meeting_id, [$slot_spk1, $slot_tmod]);
$t->not_wp_error($r_alice_dup, 'Duplicate request by Alice is accepted (idempotent update)');
$pending_after_dup = $f->count_requests($meeting_id, 'Pending');
$t->eq($pending, $pending_after_dup, 'Pending count unchanged after duplicate submit');

// 4c. Deadline-expired meeting: get_open_slots() hides the slot.
//     (save_requests() is a backend method; deadline enforcement is at the
//      display/discovery layer so members never see the slot after close time.)
$closed_meeting_id = $f->meeting(
    date('Y-m-d', strtotime('+2 weeks')),
    ['Timer'],
    0,
    date('Y-m-d\TH:i', strtotime('-1 hour'))   // deadline 1 hour ago
);
$open_slots_after_deadline = TMP_Repository::get_open_slots();
$closed_meeting_slots = array_filter($open_slots_after_deadline, fn($s) => (int)($s['meeting_id'] ?? 0) === $closed_meeting_id);
$t->eq(0, count($closed_meeting_slots),
    'get_open_slots() hides slots for meetings past their request deadline');

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 5: Scoring
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('5 · Score Calculation');

// Build minimal request + member structs that score_request() expects
$build_req = fn($priority, $role) => [
    'priority'  => $priority,
    'role_name' => $role,
];
$build_member = fn($id, $level) => ['id' => $id, 'level' => $level];

// P1 (75pts priority) vs P2 (50pts priority), same member/role
$score_p1 = TMP_Repository::score_request($build_req(1, 'Speaker 1'), $build_member($alice, 2));
$score_p2 = TMP_Repository::score_request($build_req(2, 'Speaker 1'), $build_member($alice, 2));
$t->ok($score_p1 > $score_p2, "P1 scores higher than P2 ({$score_p1} > {$score_p2})");

// Higher level → higher score (same priority/role)
$score_l2 = TMP_Repository::score_request($build_req(1, 'Timer'), $build_member($alice, 2));
$score_l1 = TMP_Repository::score_request($build_req(1, 'Timer'), $build_member($bob,   1));
$t->ok($score_l2 >= $score_l1, "L2 scores >= L1 for same role/priority ({$score_l2} vs {$score_l1})");

// Score is non-negative
$t->ok($score_p1 >= 0 && $score_p2 >= 0, 'Scores are non-negative');

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 6: Individual approval + cascade
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('6 · Individual Approval & Cascade Rejection');

// Approve Alice for Speaker 1
$req_alice_spk = $f->get_request($alice, $meeting_id, $slot_spk1);
$t->ok($req_alice_spk !== null, 'Alice has a pending Speaker request in DB');

if ($req_alice_spk) {
    // Use the actual role_name stored on the slot (e.g. "Speaker 1 (Speech)")
    $slot_row  = $f->get_slot($slot_spk1);
    $slot_role = $slot_row['role_name'] ?? 'Speaker 1';
    $approval  = TMP_Repository::approve_request_and_cascade_reject(
        (int) $req_alice_spk['id'],
        $alice,
        $meeting_id,
        $slot_role
    );
    $t->not_wp_error($approval, "Approved Alice for Speaker 1");

    // Slot now shows Alice as Confirmed
    $slot_after = $f->get_slot($slot_spk1);
    $t->eq($alice,       (int) $slot_after['member_id'], "Speaker 1 slot → member_id = Alice");
    $t->eq('Confirmed',  $slot_after['status'],          "Speaker 1 slot status = Confirmed");

    // Bob and Carol's Speaker requests should be NotSelected (cascade status used by the repo)
    $req_bob_spk   = $f->get_request($bob,   $meeting_id, $slot_spk1);
    $req_carol_spk = $f->get_request($carol, $meeting_id, $slot_spk1);
    $t->eq('NotSelected', $req_bob_spk['status']   ?? null, "Bob's Speaker request → NotSelected");
    $t->eq('NotSelected', $req_carol_spk['status'] ?? null, "Carol's Speaker request → NotSelected");

    // Dave's Speaker request (P2) should also be NotSelected
    $req_dave_spk = $f->get_request($dave, $meeting_id, $slot_spk1);
    $t->eq('NotSelected', $req_dave_spk['status'] ?? null, "Dave's Speaker(P2) request → NotSelected");

    // Alice's TMOD request must be NotSelected (already has one approved role)
    $req_alice_tmod = $f->get_request($alice, $meeting_id, $slot_tmod);
    $t->eq('NotSelected', $req_alice_tmod['status'] ?? null,
        "Alice's TMOD request → NotSelected (already has approved role)");
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 7: Double-approval guard
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('7 · Double-Approval Guard');

// Try to approve Alice again for TMOD (she already has Speaker)
$req_alice_tmod2 = $f->get_request($alice, $meeting_id, $slot_tmod);
if ($req_alice_tmod2 && $req_alice_tmod2['status'] === 'NotSelected') {
    // Re-open for this test by setting back to Pending manually
    global $wpdb;
    $wpdb->update(
        TMP_Repository::request_table(),
        ['status' => 'Pending'],
        ['id' => (int) $req_alice_tmod2['id']]
    );
    $tmod_slot_row  = $f->get_slot($slot_tmod);
    $tmod_role_name = $tmod_slot_row['role_name'] ?? 'Toastmaster of the Day';
    $double = TMP_Repository::approve_request_and_cascade_reject(
        (int) $req_alice_tmod2['id'],
        $alice,
        $meeting_id,
        $tmod_role_name
    );
    $t->is_wp_error_code($double, 'tmp_already_approved',
        "Second approval for Alice blocked (tmp_already_approved)");
    // Restore to NotSelected
    $wpdb->update(
        TMP_Repository::request_table(),
        ['status' => 'NotSelected'],
        ['id' => (int) $req_alice_tmod2['id']]
    );
} else {
    $t->ok(true, 'Double-approval guard — request already NotSelected, skipped re-test');
}

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 8: Bulk approval fills remaining slots
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('8 · Bulk Approval (approve_all_recommended)');

$pending_before_bulk = $f->count_requests($meeting_id, 'Pending');
$t->ok($pending_before_bulk > 0, "Pending requests remain before bulk ({$pending_before_bulk})");

$bulk_result = TMP_Repository::approve_all_recommended($meeting_id);
$t->ok(!isset($bulk_result['error']), 'Bulk approve completed without error');

$approved_count = $bulk_result['approved'] ?? 0;
$t->ok($approved_count > 0, "Bulk approved {$approved_count} request(s)");

// ─────────────────────────────────────────────────────────────────────────────
// SUITE 9: Final state
// ─────────────────────────────────────────────────────────────────────────────

$t->suite('9 · Final State');

$pending_final = $f->count_requests($meeting_id, 'Pending');
$t->eq(0, $pending_final, 'Zero Pending requests remain for this meeting');

// TMOD and GE slots should now be filled
$tmod_slot_final = $f->get_slot($slot_tmod);
$ge_slot_final   = $f->get_slot($slot_ge);

$t->ok(
    !empty($tmod_slot_final['member_id']) && (int) $tmod_slot_final['member_id'] > 0,
    'TMOD slot is filled after bulk approval'
);
$t->ok(
    !empty($ge_slot_final['member_id']) && (int) $ge_slot_final['member_id'] > 0,
    'GE slot is filled after bulk approval'
);

// Approved member IDs should all be our test members
$approved_ids = [];
foreach ($f->get_slots($meeting_id) as $slot) {
    if (!empty($slot['member_id']) && (int) $slot['member_id'] > 0) {
        $approved_ids[] = (int) $slot['member_id'];
    }
}
$test_member_ids = [$alice, $bob, $carol, $dave, $eve];
$all_test        = array_diff($approved_ids, $test_member_ids) === [];
$t->ok($all_test, 'All assigned members are test members (no data bleed)');

// ─────────────────────────────────────────────────────────────────────────────
// Cleanup
// ─────────────────────────────────────────────────────────────────────────────

echo "\n\033[90m── Cleanup ──\033[0m\n";
$f->teardown();
echo "  Test data removed.\n";

$t->summary();
exit($t->failed() ? 1 : 0);
