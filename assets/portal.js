(function () {
  if (!window.TMPortal) {
    return;
  }

  const levels = [
    "Level 1 - Ice Breaker and evaluations",
    "Level 2 - Communication style",
    "Level 3 - Build knowledge and skills",
    "Level 4 - Manage projects and advanced skills",
    "Level 5 - Demonstrate expertise",
  ];

  let refreshVPE = () => {};

  const qs  = (sel, root = document) => root ? root.querySelector(sel) : null;
  const qsa = (sel, root = document) => root ? Array.from(root.querySelectorAll(sel)) : [];
  const esc = (v) => String(v || "").replace(/[&<>"']/g, (c) => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));

  function roleSort(roleName) {
    const n = (roleName || "").replace(/\s*\(.*?\)\s*/g, "").trim().toLowerCase();
    const sm = n.match(/^speaker\s*(\d+)$/);
    const em = n.match(/^evaluator\s*(\d+)$/);
    if (sm) return 100 + (parseInt(sm[1], 10) - 1) * 2;
    if (em) return 101 + (parseInt(em[1], 10) - 1) * 2;
    if (n.includes("table topics master"))  return 90;
    if (n.includes("table topics speaker")) return 91;
    if (n.includes("table topics"))         return 92;
    if (n.includes("presiding officer"))    return 0;
    if (n === "saa" || n.includes("sergeant at arms")) return 1;
    if (n.includes("toastmaster"))          return 2;
    if (n.includes("timer"))                return 3;
    if (n.includes("ah counter"))           return 4;
    if (n.includes("grammarian"))           return 5;
    if (n.includes("general evaluator"))    return 6;
    return 50;
  }

  function formatTime(totalMinutes) {
    const h = Math.floor(totalMinutes / 60) % 24;
    const m = totalMinutes % 60;
    return `${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}`;
  }

  function generatePrintView(meeting) {
    const w = window.open("", "_blank");
    if (!w) { alert("Please allow pop-ups for this site to print the agenda."); return; }
    const [h, m] = (meeting.start_time || "18:30:00").split(":").map(Number);
    let t = h * 60 + m;
    const rows = (meeting.assignments || []).map((a) => {
      const start = formatTime(t);
      const dur   = Number(a.duration || 0);
      t += dur;
      return `<tr><td>${start}</td><td>${dur}m</td><td>${formatTime(t)}</td><td><strong>${esc(a.role_name)}</strong></td><td>${esc(a.member_name || "Unassigned")}</td><td>${esc(a.speech_title || "")}</td></tr>`;
    }).join("");

    w.document.write(`<html><head><title>Agenda - ${esc(meeting.meeting_date)}</title>
      <style>body{font-family:Segoe UI,sans-serif;padding:50px;color:#333}h1{color:#004165;border-bottom:2px solid #004165;padding-bottom:10px}
      .d{margin-bottom:30px;background:#f9f9f9;padding:20px;border-radius:5px;border-left:5px solid #004165}
      table{width:100%;border-collapse:collapse}th{background:#004165;color:#fff;padding:12px;text-align:left}
      td{border-bottom:1px solid #ddd;padding:12px;vertical-align:top}</style></head>
      <body><h1>Toastmasters Meeting Agenda</h1>
      <div class="d"><strong>Date:</strong> ${esc(meeting.meeting_date)} | <strong>Theme:</strong> ${esc(meeting.theme)} | <strong>Venue:</strong> ${esc(meeting.venue || "TBD")}</div>
      <table><thead><tr><th>Start</th><th>Dur</th><th>End</th><th>Role</th><th>Member</th><th>Notes</th></tr></thead><tbody>${rows}</tbody></table>
      <p style="margin-top:40px;white-space:pre-wrap;color:#555;font-style:italic">${esc(meeting.agenda_notes || "")}</p>
      <script>setTimeout(()=>{window.focus();window.print();window.onafterprint=()=>window.close();},500)<\/script></body></html>`);
    w.document.close();
  }

  async function api(path, options = {}) {
    const base = TMPortal.restUrl.replace(/\/$/, "");  // strip trailing slash WordPress adds
    let url = `${base}${path}`;
    if (!options.method || options.method === "GET") {
      url += (url.includes("?") ? "&" : "?") + "_=" + Date.now();
    }
    const res  = await fetch(url, { ...options, headers: { "Content-Type": "application/json", "X-WP-Nonce": TMPortal.nonce, ...(options.headers || {}) } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  function formData(form) {
    const fd = new FormData(form);
    const d  = {};
    for (const [k, v] of fd.entries()) {
      if (k.endsWith("[]")) {
        const ck = k.slice(0, -2);
        if (!d[ck]) d[ck] = [];
        d[ck].push(v);
      } else {
        d[k] = v;
      }
    }
    return d;
  }

  function fillForm(form, rec) {
    Object.entries(rec).forEach(([k, v]) => {
      if (!form.elements[k]) return;
      let val = v || "";
      if (form.elements[k].type === "datetime-local" && val) val = val.replace(" ", "T").substring(0, 16);
      if (form.elements[k].type === "checkbox") form.elements[k].checked = !!val;
      else form.elements[k].value = val;
    });
  }

  function clearForm(form) {
    form.reset();
    if (form.elements.id) form.elements.id.value = "";
  }

  // ===========================================================================
  // MEMBER DASHBOARD
  // ===========================================================================

  function paidUntilDisplay(member) {
    if (member.is_exempt_from_unpaid_block) return { text: "Exempt (New Member)", color: "var(--tmp-teal)" };
    if (!member.paid_until) return { text: "Not on file", color: "var(--tmp-muted)" };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const due   = new Date(member.paid_until);
    const days  = Math.round((due - today) / 86400000);
    const fmt   = due.toLocaleDateString("en-AU", { day: "numeric", month: "short", year: "numeric" });
    if (days < 0)   return { text: `Expired ${fmt}`, color: "#c62828" };
    if (days <= 30) return { text: `Due ${fmt} (${days}d)`, color: "#ef6c00" };
    return { text: `Paid until ${fmt}`, color: "#2e7d32" };
  }

  async function updateMemberDashboard() {
    const root = qs("[data-tmp-member-dashboard]");
    if (!root) return;

    try {
      const member = await api("/me");
      const level  = Number(member.level || 1);
      const pct    = Math.max(20, Math.min(100, level * 20));

      const setField = (sel, val) => { const el = qs(sel, root); if (el) el.textContent = val; };
      setField("[data-tmp-member-name]",    member.full_name);
      setField("[data-tmp-member-summary]", `${member.pathway} - Level ${level}`);
      setField("[data-tmp-progress]",       `${pct}%`);
      const bar = qs("[data-tmp-progress-bar]", root); if (bar) bar.style.width = `${pct}%`;
      setField("[data-tmp-state]",          member.state || "Active");
      setField("[data-tmp-project]",        member.current_project || "Not assigned");
      setField("[data-tmp-next-action]",    member.next_action || "No next action recorded.");
      const paid = paidUntilDisplay(member);
      const paidEl = qs("[data-tmp-paid-until]", root);
      if (paidEl) { paidEl.textContent = paid.text; paidEl.style.color = paid.color; }

      const levelsEl = qs("[data-tmp-levels]", root);
      if (levelsEl) levelsEl.innerHTML = levels.map((lbl, i) => {
        const n   = i + 1;
        const cls = n < level ? "tmp-done" : n === level ? "tmp-active" : "";
        return `<li class="${cls}">${esc(lbl)}</li>`;
      }).join("");

      // Define which milestones are visible at each level
      const milestonesByLevel = {
        0: ['joined', 'orientation'],
        1: ['joined', 'orientation', 'first_role', 'icebreaker_draft', 'icebreaker_delivered', 'level1_completed'],
        2: ['joined', 'orientation', 'first_role', 'icebreaker_draft', 'icebreaker_delivered', 'level1_completed'],
        3: ['joined', 'orientation', 'first_role', 'icebreaker_draft', 'icebreaker_delivered', 'level1_completed'],
        4: ['joined', 'orientation', 'first_role', 'icebreaker_draft', 'icebreaker_delivered', 'level1_completed'],
        5: ['joined', 'orientation', 'first_role', 'icebreaker_draft', 'icebreaker_delivered', 'level1_completed'],
      };
      const visibleMilestones = milestonesByLevel[level] || milestonesByLevel[5];

      qsa("[data-m]", root).forEach((el) => {
        const key = el.dataset.m;
        const isVisible = visibleMilestones.includes(key);
        el.style.display = isVisible ? "" : "none";
        if (isVisible && member.milestones && member.milestones[key]) {
          el.classList.add("tmp-done");
          el.title = `Completed: ${member.milestones[key]}`;
        }
      });

      // Add note about TI website for pathways details
      const milestonesPanel = qs("[data-tmp-milestones]", root);
      if (milestonesPanel && !qs("[data-tmp-ti-note]", milestonesPanel)) {
        const note = document.createElement("p");
        note.setAttribute("data-tmp-ti-note", "");
        note.style.cssText = "margin-top:12px;padding:10px;background:#f0f7ff;border-left:4px solid #1976d2;font-size:12px;color:#333;";
        note.innerHTML = `For detailed information about Pathways levels and requirements, visit <a href="https://www.toastmasters.org/pathways" target="_blank" rel="noopener" style="color:#1976d2;font-weight:bold;text-decoration:none;">Toastmasters Pathways Overview &rarr;</a>`;
        milestonesPanel.appendChild(note);
      }

      // ── Mentor card — shown only for Level 0 and Level 1 members ──────────
      // Levels 2+ have completed mentorship and no longer need the mentor card
      const mentorCard = qs("[data-tmp-mentor-card]", root);
      if (mentorCard) mentorCard.style.display = level > 1 ? "none" : "";

      const mentorInfo = qs("[data-tmp-mentor-info]", root);
      if (mentorInfo) {
        if (member.mentor_id && member.mentor_name) {
          mentorInfo.innerHTML = `
            <dl class="tmp-profile-list">
              <div><dt>Name</dt><dd><strong>${esc(member.mentor_name)}</strong></dd></div>
              <div><dt>Pathway</dt><dd>${esc(member.mentor_pathway || "—")}</dd></div>
              <div><dt>Level</dt><dd>Level ${esc(member.mentor_level || "—")}</dd></div>
              ${member.mentor_email ? `<div><dt>Contact</dt><dd><a href="mailto:${esc(member.mentor_email)}">${esc(member.mentor_email)}</a></dd></div>` : ""}
            </dl>`;
        } else {
          mentorInfo.innerHTML = `<p style="color:var(--tmp-muted)">No mentor assigned yet. Speak to your VP Education.</p>`;
        }
      }

      // ── Mentorship checklist ───────────────────────────────────────────────
      const checklistEl = qs("[data-tmp-mentorship-checklist]", root);
      if (checklistEl) {
        const MC_STEPS = [
          { label: "Mentor Assigned"       },
          { label: "Orientation Complete"  },
          { label: "Ice Breaker Delivered" },
          { label: "Level 1 Complete"      },
          { label: "Mentorship Closed"     },
        ];
        // Maps stage key → index of the last COMPLETED step (-1 = nothing done yet)
        const stageToStep = {
          no_mentor:            -1,
          assigned:              0,
          orientation_complete:  1,
          icebreaker_delivered:  2,
          level1_complete:       3,
          closed:                4,
        };
        const currentStep = stageToStep[member.mentorship_stage] ?? -1;
        checklistEl.innerHTML = `
          <p style="font-size:0.78rem;font-weight:800;text-transform:uppercase;color:var(--tmp-muted);margin:0 0 10px;">Mentor Program</p>
          <ol class="tmp-mentor-checklist">${MC_STEPS.map((s, i) => {
            const done   = i <= currentStep;
            const active = i === currentStep + 1;
            const cls    = done ? "tmp-mc-done" : active ? "tmp-mc-active" : "tmp-mc-pending";
            return `<li class="tmp-mc-item ${cls}"><span class="tmp-mc-dot"></span>${esc(s.label)}</li>`;
          }).join("")}</ol>`;
      }

      // ── Level journey ─────────────────────────────────────────────────────
      const renderJourney = async () => {
        const journeyData = await api("/me/level-gaps").catch(() => null);
        const journeyEl   = qs("[data-tmp-level-journey]", root);
        if (!journeyEl || !journeyData) return;
        const { level: lvl, gaps } = journeyData;
        if (!gaps || gaps.length === 0) {
          journeyEl.innerHTML = `<p style="color:var(--tmp-muted)">No specific role requirements found for Level ${lvl}.</p>`;
          return;
        }
        const metCount   = gaps.filter((g) => g.met).length;
        const totalCount = gaps.length;
        const allMet     = metCount === totalCount;
        journeyEl.innerHTML = `
          <p style="margin-bottom:10px;font-size:13px;">
            <strong>${metCount} of ${totalCount}</strong> requirements met at Level ${lvl}.
            ${allMet ? ' <span style="color:#2e7d32;font-weight:bold;">✓ All done — ready to level up!</span>' : ''}
          </p>
          <div class="tmp-table-wrap">
            <table class="tmp-table">
              <thead><tr><th>Requirement</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>${gaps.map((g) => {
                const reqKey = g.type === "presentation" ? g.series : (g.roles || []).join("|");
                let actionHtml;
                if (g.manual_override) {
                  actionHtml = `<span style="color:#ef6c00;font-size:11px;">Manually marked</span>
                    <button class="tmp-small-button" style="margin-left:5px;font-size:10px;" data-undo-override="${esc(g.override_id)}">Undo</button>`;
                } else if (g.met) {
                  actionHtml = `<span style="color:var(--tmp-muted);font-size:11px;">Recorded</span>`;
                } else {
                  actionHtml = `<button class="tmp-small-button" data-mark-done="${esc(reqKey)}" data-mark-level="${esc(lvl)}">Mark as done</button>`;
                }
                return `<tr style="background:${g.met ? "#f1f8e9" : "#fff8e1"}" data-req-key="${esc(reqKey)}">
                  <td data-label="Requirement">${esc(g.label)}</td>
                  <td data-label="Progress">${g.done} / ${g.needed}</td>
                  <td data-label="Status"><span class="tmp-tag" style="background:${g.met ? "#2e7d32" : "#ef6c00"};color:#fff;">${g.met ? "✓ Done" : "Needed"}</span></td>
                  <td data-label="Action">${actionHtml}</td>
                </tr>`;
              }).join("")}
              </tbody>
            </table>
          </div>`;

      };
      root._renderJourney = renderJourney;
      await renderJourney();

      // ── Active Requests (unified: all upcoming requests with live status) ─────
      const pendingData = await api("/me/pending-requests").catch(() => ({ requests: [] }));
      root._memberRequests = pendingData.requests; // Used by duplicate-request guard below

      // Badge: count of genuinely undecided requests
      const pendingCount = pendingData.requests.filter((r) => r.status === "Pending").length;
      const badgeEl = qs("[data-tmp-meeting-badge]", root);
      if (badgeEl) { badgeEl.textContent = pendingCount; badgeEl.style.display = pendingCount > 0 ? "inline-flex" : "none"; }

      // Group requests by meeting for display
      const byMeeting = {};
      for (const r of pendingData.requests) {
        if (!byMeeting[r.meetingId]) {
          byMeeting[r.meetingId] = { meetingDate: r.meetingDate, meetingTheme: r.meetingTheme, deadline: r.deadline, requests: [] };
        }
        byMeeting[r.meetingId].requests.push(r);
      }

      const arEl = qs("[data-tmp-active-requests]", root);
      if (arEl) {
        const meetingGroups = Object.values(byMeeting);
        if (meetingGroups.length === 0) {
          arEl.innerHTML = "<p>You have no active role requests.</p>";
        } else {
          const nowTs = new Date();
          let html = "";
          for (const mtg of meetingGroups) {
            const deadlinePassed = mtg.deadline && new Date(mtg.deadline) <= nowTs;
            const deadlineNote   = mtg.deadline
              ? (deadlinePassed
                  ? `<span style="font-size:11px;color:var(--tmp-burgundy);">Request window closed</span>`
                  : `<span style="font-size:11px;color:#2e7d32;">Open until ${esc(mtg.deadline.slice(0, 10))}</span>`)
              : "";
            html += `<div style="margin-bottom:16px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <strong>${esc(mtg.meetingDate)} — ${esc(mtg.meetingTheme)}</strong>
                ${deadlineNote}
              </div>
              <div class="tmp-table-wrap"><table class="tmp-table">
                <thead><tr><th>Role</th><th>Priority</th><th>Status</th><th>Reason</th><th>Action</th></tr></thead>
                <tbody>${mtg.requests.map((r) => {
                  const statusColor = r.status === "Approved" ? "#2e7d32" : r.status === "NotSelected" ? "#c62828" : "#757575";
                  const statusIcon  = r.status === "Approved" ? "✓" : r.status === "NotSelected" ? "✗" : "⏳";
                  const statusLabel = r.status === "Approved" ? "Approved" : r.status === "NotSelected" ? "Not Selected" : "Pending";
                  const reasonHtml  = r.reason ? `<span style="color:#555;font-size:12px;">${esc(r.reason)}</span>` : "—";
                  const canCancel   = r.status === "Pending" && !deadlinePassed;
                  const actionHtml  = canCancel
                    ? `<button class="tmp-small-button tmp-danger" data-cancel-request="${esc(r.requestId)}">Cancel</button>`
                    : "—";
                  return `<tr>
                    <td data-label="Role">${esc(r.roleName)}</td>
                    <td data-label="Priority"><span class="tmp-tag" style="background:#f5f5f5">P${esc(r.priority)}</span></td>
                    <td data-label="Status"><span class="tmp-tag" style="background:${statusColor};color:#fff;font-weight:bold;">${statusIcon} ${statusLabel}</span></td>
                    <td data-label="Reason">${reasonHtml}</td>
                    <td data-label="Action">${actionHtml}</td>
                  </tr>`;
                }).join("")}
                </tbody>
              </table></div>
            </div>`;
          }
          arEl.innerHTML = html;
        }
      }

      // Clear the old separate post-deadline section — merged into the view above
      const prEl = qs("[data-tmp-pending-requests]", root);
      if (prEl) prEl.innerHTML = "";

      // ── Request history ────────────────────────────────────────────────────
      const history = await api("/me/requests/history").catch(() => []);
      const rhEl = qs("[data-tmp-request-history]", root); if (rhEl) rhEl.innerHTML = history.length
        ? `<div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>${history.map((r) => {
              const statusColor = r.request_status === "Approved" ? "#2e7d32" : r.request_status === "NotSelected" ? "#c62828" : "#eee";
              const textColor   = (r.request_status === "Approved" || r.request_status === "NotSelected") ? "#fff" : "#333";
              const label       = r.request_status === "Approved" ? "Approved" : r.request_status === "NotSelected" ? "Not Selected" : "Unprocessed";
              return `<tr><td data-label="Meeting">${esc(r.meeting_date)} - ${esc(r.theme)}</td><td data-label="Role">${esc(r.role_name)}</td>
                <td data-label="Priority"><span class="tmp-tag" style="background:#f5f5f5">P${esc(r.priority)}</span></td>
                <td data-label="Status"><span class="tmp-tag" style="background:${statusColor};color:${textColor};">${label}</span></td></tr>`;
            }).join("")}</tbody></table></div>`
        : "<p>No request history found.</p>";

      // ── Role history ───────────────────────────────────────────────────────
      const roleHistory = await api("/me/participation-history").catch(() => ({}));
      let roleHistoryHtml = "";
      if (Object.keys(roleHistory).length > 0) {
        for (const lvl in roleHistory) {
          roleHistoryHtml += `<h4>Level ${esc(lvl)}</h4>
            <div class="tmp-table-wrap"><table class="tmp-table">
              <thead><tr><th>Role</th><th>Count</th><th>Last Completed</th></tr></thead>
              <tbody>${roleHistory[lvl].map((r) =>
                `<tr><td data-label="Role">${esc(r.role_name)}${r.presentation_series ? `<br><small style="color:var(--tmp-muted)">${esc(r.presentation_series)}</small>` : ""}</td>
                <td data-label="Count">${esc(r.count)}</td><td data-label="Last Completed">${esc(r.last_completed_date)}</td></tr>`
              ).join("")}</tbody></table></div>`;
        }
      } else {
        roleHistoryHtml = "<p>No role history found.</p>";
      }
      const rHistEl = qs("[data-tmp-role-history]", root); if (rHistEl) rHistEl.innerHTML = roleHistoryHtml;

      // ── Open slots ─────────────────────────────────────────────────────────
      const slotsResp  = await api("/meetings/open-slots");
      const memberLevel = Number(slotsResp.member_level || 1);
      const participation = slotsResp.member_participation || {};
      const levelHistory  = participation[memberLevel] || {};

      const slots = ((slotsResp && Array.isArray(slotsResp.slots)) ? slotsResp.slots : [])
        .filter((s) => {
          const lower = s.role_name.toLowerCase();
          if (lower.startsWith("break")) return false;
          // Explicitly include TMOD/Toastmaster in all forms before excluding presiding officer
          if (lower.includes("toastmaster")) return true;
          if (lower.includes("presiding officer")) return false;
          return true;
        })
        .map((s) => {
          const role = s.role_name.toLowerCase();
          let qualified   = true;
          let requirement = "";

          const gates = TMPortal.roleGateLevels || {};
          for (const [pattern, minLevel] of Object.entries(gates)) {
            if (role.includes(pattern)) {
              const gate = Number(minLevel);
              if (gate > 0) {
                qualified   = memberLevel >= gate;
                requirement = `Level ${gate}+ required`;
              }
              break;
            }
          }

          // Defensive: ensure Evaluator X roles are gated correctly even if pattern doesn't match
          if (!role.includes('general evaluator') && role.match(/evaluator/i) && memberLevel < 1) {
            qualified = false;
            requirement = 'Level 1+ required';
          }

          const base = s.role_name.replace(/\s*\(.*?\)\s*/g, "").replace(/\s+\d+$/, "").trim();
          const cooloff = s.cooloff || null;
          if (cooloff && cooloff.in_cooloff) {
            qualified   = false;
            requirement = `Cooloff until ${cooloff.eligible_from}`;
          }

          return { ...s, qualified, requirement, isGoal: !!s.is_goal, cooloff, base };
        });

      const reqForm  = qs("[data-tmp-member-request-form]", root);
      const mSelect  = qs("[data-tmp-req-meeting-select]", reqForm);
      const rSelects = qsa("[data-tmp-req-role-select]", reqForm);

      const reqSection = qs("[data-tmp-request-section]", root);
      if (reqSection) reqSection.style.display = slots.length ? "" : "none";

      root._groupedSlots = slots.reduce((acc, s) => {
        const key = `${s.meeting_date} - ${s.theme}`;
        if (!acc[key]) acc[key] = { id: s.meeting_id, text: key, roles: [] };
        acc[key].roles.push(s);
        return acc;
      }, {});

      if (mSelect) {
        mSelect.innerHTML = '<option value="">Select a meeting...</option>' +
          Object.values(root._groupedSlots).map((g) =>
            `<option value="${esc(g.id)}">${esc(g.text)} (${g.roles.length} roles open)</option>`
          ).join("");
      }

      rSelects.forEach((sel) => { sel.innerHTML = '<option value="">Select a meeting first...</option>'; });

      // ── Recommendations ────────────────────────────────────────────────────
      const recs = await api("/me/recommendations").catch(() => []);
      const recsEl = qs("[data-tmp-recommendations]", root); if (recsEl) recsEl.innerHTML = recs.length
        ? recs.map((r) => `<div class="tmp-rec-item"><strong>${esc(r.title)}</strong><small>${esc(r.type)}</small><p>${esc(r.note)}</p></div>`).join("")
        : "<p>No recommendations today.</p>";

      root._member = member;

      // ── Mentor dashboard (if current user is a mentor) ─────────────────────
      const mentees = await api("/mentor/mentees").catch(() => []);
      if (mentees.length > 0) {
        const mentorDash = qs("[data-tmp-mentor-dashboard]", root);
        if (mentorDash) mentorDash.style.display = "block";
        const menteeList = qs("[data-tmp-mentee-list]", mentorDash);
        const STAGE_LABELS = {
          no_mentor:            { label: "No Mentor",          bg: "#9e9e9e" },
          assigned:             { label: "Mentor Assigned",    bg: "#1565c0" },
          orientation_complete: { label: "Orientation Done",   bg: "#6a1b9a" },
          icebreaker_delivered: { label: "Ice Breaker Done",   bg: "#2e7d32" },
          level1_complete:      { label: "Level 1 Complete",   bg: "#ef6c00" },
          closed:               { label: "Mentorship Closed",  bg: "#424242" },
        };
        if (menteeList) menteeList.innerHTML = `
          <div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Mentee</th><th>Stage</th><th>Participation</th><th>At Risk?</th><th>Level Progress</th><th>Your Next Action</th></tr></thead>
            <tbody>${mentees.map((m) => {
              const gapsMet      = (m.level_gaps || []).filter((g) => g.met).length;
              const gapsTotal    = (m.level_gaps || []).length;
              const gapBadge     = gapsTotal > 0 ? `${gapsMet}/${gapsTotal} L${m.level} reqs` : "";
              const noPathway    = (m.next_action || "").startsWith("Register for a Pathway");
              const stageMeta    = STAGE_LABELS[m.mentorship_stage] || STAGE_LABELS.no_mentor;
              const rowStyle     = m.is_at_risk ? "background:#fff8e1" : noPathway ? "background:#fce4ec" : "";
              const pathwayLabel = noPathway
                ? `<span class="tmp-tag" style="background:#c62828;color:#fff;display:inline-block;margin-top:4px;">Not Enrolled</span>`
                : `<small>${esc(m.pathway)}</small>`;
              const mentorActionHtml = noPathway
                ? `<a href="https://www.toastmasters.org/pathways-overview" target="_blank" rel="noopener" style="color:var(--tmp-burgundy);text-decoration:underline;font-size:0.85rem;">Help register on TI &rarr;</a>`
                : `<small style="color:var(--tmp-muted)">${esc(m.mentor_next_action || "—")}</small>`;
              return `<tr style="${rowStyle}">
                <td data-label="Mentee"><strong>${esc(m.full_name)}</strong><br>${pathwayLabel}</td>
                <td data-label="Stage"><span class="tmp-tag" style="background:${stageMeta.bg};color:#fff;">${esc(stageMeta.label)}</span><br><small style="color:var(--tmp-muted);font-size:0.8rem;margin-top:3px;display:block">${esc(m.next_action || "")}</small></td>
                <td data-label="Participation">${m.recent_participation_count} / ${m.total_recent_meetings_checked}</td>
                <td data-label="At Risk?">${m.is_at_risk ? '<span style="color:red;font-weight:bold;">YES</span>' : "No"}</td>
                <td data-label="Level Progress">${gapBadge ? `<span class="tmp-tag" style="background:${gapsMet === gapsTotal ? "#2e7d32" : "#ef6c00"};color:#fff;">${gapBadge}</span>` : "—"}</td>
                <td data-label="Your Next Action">${mentorActionHtml}</td>
              </tr>`;
            }).join("")}</tbody></table></div>`;
      }

    } catch (err) {
      console.error("Dashboard error:", err);
      root.innerHTML = `<div class="tmp-panel">
        <h2>Dashboard unavailable</h2>
        <p>${esc(err.message)}</p>
        <pre style="font-size:11px;color:#888;white-space:pre-wrap;margin-top:10px">${esc(err.stack || "")}</pre>
      </div>`;
    }
  }

  async function initMemberDashboard() {
    const root = qs("[data-tmp-member-dashboard]");
    if (!root) return;

    const reqForm = qs("[data-tmp-member-request-form]", root);
    const mSelect = reqForm ? qs("[data-tmp-req-meeting-select]", reqForm) : null;

    await updateMemberDashboard();

    // Level journey — Mark as done / Undo (delegated once on stable parent)
    qs("[data-tmp-level-journey-panel]", root)?.addEventListener("click", async (e) => {
      const markBtn = e.target.closest("[data-mark-done]");
      const undoBtn = e.target.closest("[data-undo-override]");

      if (markBtn && !markBtn._pending) {
        const rKey  = markBtn.dataset.markDone;
        const rLvl  = markBtn.dataset.markLevel;
        const row   = markBtn.closest("tr");
        const label = row?.querySelector("td")?.textContent || rKey;
        const note  = prompt(`Mark "${label}" as completed outside this system?\n\nOptional note (e.g. "Completed at district event"):`) ?? false;
        if (note === false) return;
        markBtn._pending = true;
        markBtn.disabled = true;
        markBtn.textContent = "Saving…";
        try {
          await api("/me/requirement-override", { method: "POST", body: JSON.stringify({ level: Number(rLvl), req_key: rKey, note }) });
          if (root._renderJourney) await root._renderJourney();
        } catch (err) {
          alert("Could not save: " + err.message);
          markBtn._pending = false;
          markBtn.disabled = false;
          markBtn.textContent = "Mark as done";
        }
      }

      if (undoBtn && !undoBtn._pending) {
        if (!confirm("Remove this manual override and restore to history-based tracking?")) return;
        undoBtn._pending = true;
        undoBtn.disabled = true;
        try {
          await api(`/me/requirement-override/${undoBtn.dataset.undoOverride}`, { method: "DELETE" });
          if (root._renderJourney) await root._renderJourney();
        } catch (err) {
          alert("Could not undo: " + err.message);
          undoBtn._pending = false;
          undoBtn.disabled = false;
        }
      }
    });

    // Meeting Activity expand/collapse toggle
    const meetingToggle = qs("[data-tmp-meeting-toggle]", root);
    const meetingBody   = qs("[data-tmp-meeting-body]",   root);
    meetingToggle?.addEventListener("click", () => {
      const open = meetingToggle.getAttribute("aria-expanded") === "true";
      meetingToggle.setAttribute("aria-expanded", String(!open));
      if (meetingBody) meetingBody.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", meetingToggle);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
    });

    // Cancel request
    qs("[data-tmp-active-requests]", root)?.addEventListener("click", async (e) => {
      const btn = e.target.closest("[data-cancel-request]");
      if (!btn) return;
      if (confirm("Cancel this role request?")) {
        btn.disabled = true;
        try {
          await api(`/requests/${btn.dataset.cancelRequest}`, { method: "DELETE" });
          await updateMemberDashboard();
          refreshVPE();
        } catch (err) {
          alert(err.message);
          btn.disabled = false;
        }
      }
    });

    // Meeting select → populate role dropdowns with goal/cooloff tags
    mSelect?.addEventListener("change", () => {
      const group = Object.values(root._groupedSlots || {}).find((g) => String(g.id) === mSelect.value);

      // Check if request period has expired
      const now = new Date();
      const deadlineExpired = group?.roles[0]?.requests_close_at && new Date(group.roles[0].requests_close_at) < now;

      // Show deadline info
      const deadlineEl = qs("[data-tmp-deadline-info]", reqForm);
      if (deadlineEl && group?.roles[0]) {
        const closeTime = group.roles[0].requests_close_at;
        if (closeTime) {
          const closeDate = new Date(closeTime);
          const timeRemaining = closeDate - now;
          const hoursLeft = Math.floor(timeRemaining / (1000 * 60 * 60));
          const daysLeft = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));

          if (deadlineExpired) {
            deadlineEl.innerHTML = `<div style="background:#ffebee;border:1px solid #ef5350;border-radius:4px;padding:10px 12px;margin-bottom:12px;color:#c62828;">
              <strong>Request period closed</strong> on ${closeDate.toLocaleString()}. Please contact the VP Education if you'd like to request a role.
            </div>`;
          } else {
            const timeStr = daysLeft > 0 ? `${daysLeft}d ${hoursLeft % 24}h` : `${hoursLeft}h`;
            deadlineEl.innerHTML = `<div style="background:#e3f2fd;border:1px solid #64b5f6;border-radius:4px;padding:10px 12px;margin-bottom:12px;">
              <strong>Requests close:</strong> ${closeDate.toLocaleString()} <span style="color:#1976d2;font-weight:bold;">(${timeStr} remaining)</span>
            </div>`;
          }
        } else {
          deadlineEl.innerHTML = '<div style="background:#f1f8e9;border:1px solid #aed581;border-radius:4px;padding:10px 12px;margin-bottom:12px;">No deadline set</div>';
        }
      }

      // Check if member already has a request for this meeting
      const hasExistingRequest = (root._memberRequests || []).some((r) => String(r.meetingId) === mSelect.value);
      const dupeWarning = qs("[data-tmp-dupe-request-warning]", reqForm);
      if (dupeWarning) {
        if (hasExistingRequest) {
          dupeWarning.innerHTML = `<div style="background:#fff3e0;border:1px solid #ffb74d;border-radius:4px;padding:10px 12px;margin-bottom:12px;color:#e65100;">
            <strong>You already have a request for this meeting.</strong> Cancel it first if you want to request a different role.
          </div>`;
        } else {
          dupeWarning.innerHTML = '';
        }
      }

      // If deadline expired, hide role selection and show message
      const roleSection = qs("[data-tmp-role-selection-section]", reqForm);
      const submitBtn = reqForm?.querySelector("button[type=submit]");
      if (deadlineExpired || hasExistingRequest) {
        if (roleSection) roleSection.style.display = deadlineExpired ? "none" : "";
        if (submitBtn) submitBtn.style.display = deadlineExpired ? "none" : "";
        if (submitBtn && hasExistingRequest) submitBtn.disabled = true;
        return;
      }

      if (roleSection) roleSection.style.display = "";
      if (submitBtn) { submitBtn.style.display = ""; submitBtn.disabled = false; }

      const seen = new Set();
      const unique = [];
      if (group) {
        group.roles.forEach((r) => {
          if (!seen.has(r.base)) {
            seen.add(r.base);
            unique.push({ ...r, display: r.base });
          } else {
            const ex = unique.find((x) => x.display === r.base);
            if (r.isGoal) ex.isGoal = true;
          }
        });
      }

      const qualified = unique.filter((r) => r.qualified).sort((a, b) => roleSort(a.display) - roleSort(b.display));
      const opts = '<option value="">(None)</option>' +
        qualified.map((r) => {
          let label = r.display;
          if (r.isGoal) label += " ⭐ Goal";
          return `<option value="${esc(r.assignment_id)}">${esc(label)}</option>`;
        }).join("");

      const rSelects = qsa("[data-tmp-req-role-select]", reqForm);
      rSelects.forEach((sel) => { sel.innerHTML = opts; });

      // Prevent duplicate role selection across priorities
      const updateDropdownAvailability = () => {
        rSelects.forEach((currentSel) => {
          const selectedInOthers = new Set();
          rSelects.forEach((otherSel) => {
            if (otherSel !== currentSel && otherSel.value) {
              selectedInOthers.add(otherSel.value);
            }
          });

          Array.from(currentSel.options).forEach((opt) => {
            const isDuplicate = opt.value && opt.value !== currentSel.value && selectedInOthers.has(opt.value);
            opt.disabled = isDuplicate;
          });
        });
      };

      rSelects.forEach((sel) => {
        sel.addEventListener("change", updateDropdownAvailability);
      });
      updateDropdownAvailability(); // Initial check

      // Info box: locked roles (unavailable, showing reasons)
      const locked  = unique.filter((r) => !r.qualified);
      const infoBox = qs("[data-tmp-role-info]", reqForm);
      if (infoBox) {
        if (locked.length > 0) {
          infoBox.innerHTML = `<div style="background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:10px 12px;margin-top:10px;">
            <p style="margin:0 0 8px;font-weight:bold;color:#666;font-size:12px;text-transform:uppercase;">Unavailable roles</p>
            ${locked.map((r) => {
              const isCooloff = r.cooloff && r.cooloff.in_cooloff;
              const msg = isCooloff
                ? `<strong>${esc(r.display)}</strong> — in cooloff until ${esc(r.cooloff.eligible_from)}`
                : `<strong>${esc(r.display)}</strong> — ${esc(r.requirement)}`;
              return `<div style="margin-top:6px;font-size:11px;color:#666;">${msg}</div>`;
            }).join("")}
          </div>`;
        } else {
          infoBox.innerHTML = '';
        }
      }
    });

    // Submit role requests
    reqForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const rSelect = qs("[data-tmp-req-role-select]", reqForm);
      if (!rSelect.value || !root._member) return;

      // Check if member already has a request for this meeting
      const meetingId = reqForm.elements.meeting_id.value;
      const hasExistingRequest = (root._memberRequests || []).some((r) => String(r.meetingId) === meetingId);
      if (hasExistingRequest) {
        alert("You already have a request for this meeting. Please cancel it first if you want to request a different role.");
        return;
      }

      const btn = reqForm.querySelector("button");
      btn.disabled = true;
      try {
        const d = formData(reqForm);
        await api("/requests", { method: "POST", body: JSON.stringify({ meeting_id: d.meeting_id, member_id: root._member.id, priorities: d.priorities }) });
        alert("Your prioritized requests have been submitted!");
        await updateMemberDashboard();
        refreshVPE();
      } catch (err) {
        alert(err.message);
      } finally {
        btn.disabled = false;
      }
    });
  }

  // ===========================================================================
  // CLUB ADMIN
  // ===========================================================================

  async function initAdmin() {
    const root = qs("[data-tmp-admin]");
    if (!root) return;

    const importForm   = qs("[data-tmp-import-form]", root);
    const importStatus = qs("[data-tmp-import-status]", root);
    const table        = qs("[data-tmp-member-table]", root);
    const count        = qs("[data-tmp-member-count]", root);
    const membersWrap  = qs(".tmp-table-wrap", root);
    const toggleBtn    = qs("[data-tmp-admin-members-toggle]", root);

    // Collapsed by default
    if (membersWrap) membersWrap.style.display = "none";
    if (!root._adminSort) root._adminSort = { col: "name", dir: "asc" };

    async function render(force = false) {
      if (force || !root._members) root._members = await api("/members");
      const members = root._members;

      const searchTerm   = (qs("[data-tmp-admin-search]", root)?.value || "").toLowerCase();
      const groupKey     = qs("[data-tmp-admin-group-by]", root)?.value || "none";
      const statusFilter = qs("[data-tmp-admin-status]", root)?.value || "all";
      const levelFilter  = qs("[data-tmp-admin-level]", root)?.value || "all";

      const filtered = members.filter((m) =>
        (!searchTerm || m.full_name.toLowerCase().includes(searchTerm) || m.email.toLowerCase().includes(searchTerm)) &&
        (statusFilter === "all" || (statusFilter === "Paid" && m.is_eligible) || (statusFilter === "Unpaid" && !m.is_eligible)) &&
        (levelFilter === "all" || String(m.level) === levelFilter)
      );

      // Sort
      const { col: sortCol, dir: sortDir } = root._adminSort;
      const sorted = [...filtered].sort((a, b) => {
        const mul = sortDir === "asc" ? 1 : -1;
        if (sortCol === "level") {
          const ld = a.level - b.level;
          return mul * (ld !== 0 ? ld : a.full_name.localeCompare(b.full_name));
        }
        return mul * a.full_name.localeCompare(b.full_name);
      });

      if (count) count.textContent = `${sorted.length} ${sorted.length === 1 ? "record" : "records"}`;

      // Update toggle button text
      const isOpen = membersWrap?.style.display !== "none";
      if (toggleBtn) {
        toggleBtn.textContent = isOpen ? `Hide Members (${sorted.length})` : `Show Members (${sorted.length})`;
      }

      // Update sort indicators in static thead
      qs(".tmp-table thead", root)?.querySelectorAll("[data-sort-col]").forEach((th) => {
        const ind = qs(".tmp-sort-ind", th);
        if (ind) ind.textContent = th.dataset.sortCol === sortCol ? (sortDir === "asc" ? "▲" : "▼") : "↕";
      });

      const levelLabel = (lvl) => lvl === 0 ? "Level 0 (Enrolled)" : `Level ${lvl}`;

      const memberToRow = (m) => {
        const inactive = m.recent_participation_count === 0 && m.total_recent_meetings_checked > 0;
        return `<tr>
          <td><strong>${esc(m.full_name)}</strong></td>
          <td>${esc(m.customer_id || "")}</td>
          <td>${esc(m.email)}</td>
          <td>${esc(m.pathway)}</td>
          <td>${levelLabel(m.level)}</td>
          <td>${esc(m.state)}</td>
          <td style="${inactive ? "color:#ef6c00;font-weight:bold;" : ""}">${m.recent_participation_count} / ${m.total_recent_meetings_checked}</td>
          <td>${m.is_exempt_from_unpaid_block ? "Yes" : "No"}</td>
          <td><div class="tmp-row-actions"><button class="tmp-small-button tmp-danger" type="button" data-delete-member="${esc(m.id)}">Delete</button></div></td>
        </tr>`;
      };

      if (groupKey === "none") {
        table.innerHTML = sorted.map(memberToRow).join("");
      } else {
        const groups = sorted.reduce((acc, m) => {
          const key = (groupKey === "level" ? levelLabel(m.level) : m[groupKey]) || "Unassigned";
          if (!acc[key]) acc[key] = [];
          acc[key].push(m);
          return acc;
        }, {});
        table.innerHTML = Object.keys(groups).sort().map((name) =>
          `<tr class="tmp-group-row"><td colspan="9" style="background:#f5f5f5;font-weight:bold;padding:8px;border-bottom:1px solid #ccc;">${esc(name)} (${groups[name].length})</td></tr>` +
          groups[name].map(memberToRow).join("")
        ).join("");
      }
    }

    importForm?.addEventListener("submit", async (ev) => {
      ev.preventDefault();
      importStatus.textContent = "Importing...";
      const res  = await fetch(`${TMPortal.restUrl}/members/import`, { method: "POST", headers: { "X-WP-Nonce": TMPortal.nonce }, body: new FormData(importForm) });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) { importStatus.textContent = data.message || "Import failed."; return; }
      importStatus.textContent = `Imported ${data.imported_members} members. Created ${data.created_users}, updated ${data.updated_users}.`;
      importForm.reset();
      await render(true);
    });

    qs("[data-tmp-admin-search]",   root)?.addEventListener("input",  () => render());
    qs("[data-tmp-admin-group-by]", root)?.addEventListener("change", () => render());
    qs("[data-tmp-admin-status]",   root)?.addEventListener("change", () => render());
    qs("[data-tmp-admin-level]",    root)?.addEventListener("change", () => render());

    // Sort header click handlers (static thead)
    qs(".tmp-table thead", root)?.querySelectorAll("[data-sort-col]").forEach((th) => {
      th.style.cursor = "pointer";
      th.addEventListener("click", () => {
        const col = th.dataset.sortCol;
        if (root._adminSort.col === col) {
          root._adminSort.dir = root._adminSort.dir === "asc" ? "desc" : "asc";
        } else {
          root._adminSort = { col, dir: "asc" };
        }
        render();
      });
    });

    // Toggle handler
    toggleBtn?.addEventListener("click", () => {
      const open = membersWrap?.style.display !== "none";
      if (membersWrap) membersWrap.style.display = open ? "none" : "";
      render();
    });

    table.addEventListener("click", async (ev) => {
      const del = ev.target.closest("[data-delete-member]");
      if (del && confirm("Are you sure you want to delete this member?")) {
        ev.target.closest("tr")?.remove();
        await api(`/members/${del.dataset.deleteMember}`, { method: "DELETE" });
        await render(true);
      }
    });

    await render(true);
  }

  // ===========================================================================
  // VPE DASHBOARD
  // ===========================================================================

  async function initVPEducation() {
    const root = qs("[data-tmp-vpe]");
    if (!root) return;

    const meetingForm    = qs("[data-tmp-meeting-form]", root);
    const assignmentForm = qs("[data-tmp-assignment-form]", root);
    const meetingSelect  = qs("[data-tmp-meeting-select]", root);
    const roleSelect     = qs("[data-tmp-role-select]", root);
    const memberSelect   = qs("[data-tmp-member-select]", root);
    const meetingList    = qs("[data-tmp-meeting-list]", root);
    const meetingCount   = qs("[data-tmp-meeting-count]", root);
    const vpeSearch      = qs("[data-tmp-vpe-search]", root);
    const vpePathway     = qs("[data-tmp-vpe-pathway]", root);
    const vpeLevel       = qs("[data-tmp-vpe-level]", root);
    const vpeMentorFilt  = qs("[data-tmp-vpe-mentor-filter]", root);
    const overviewList   = qs("[data-tmp-vpe-member-list]", root);
    const overviewCount  = qs("[data-tmp-vpe-member-count]", root);
    const cooloffWarning = qs("[data-tmp-cooloff-warning]", assignmentForm);
    const cooloffOverrideWrap = qs("[data-tmp-cooloff-override-wrapper]", assignmentForm);
    const presSeries     = qs("[data-tmp-pres-series-wrapper]", assignmentForm);
    const speechWrapper  = qs("[data-tmp-speech-title-wrapper]", assignmentForm);

    refreshVPE = () => renderMeetings().catch(console.error);

    // -- Members overview + mentor assignment ----------------------------------

    async function renderMembers(force = false) {
      if (force || !root._allMembers) {
        try {
          root._allMembers = await api("/members");
        } catch (err) {
          if (overviewList) overviewList.innerHTML = `<p style="color:var(--tmp-burgundy)">Could not load members: ${esc(err.message)}</p>`;
          return;
        }
      }
      const all = root._allMembers;

      const search      = (vpeSearch?.value || "").toLowerCase();
      const pathway     = vpePathway?.value || "all";
      const levelFilt   = vpeLevel?.value || "all";
      const mentorFilt  = vpeMentorFilt?.value || "all";

      const eligible = (all || []).filter((m) =>
        m.is_eligible &&
        (!search || m.full_name.toLowerCase().includes(search) || m.email.toLowerCase().includes(search)) &&
        (pathway === "all" || m.pathway === pathway) &&
        (levelFilt === "all" || String(m.level) === levelFilt) &&
        (mentorFilt === "all" ||
         (mentorFilt === "none"     && !m.mentor_id && !m.mentor_name) ||
         (mentorFilt === "assigned" && (m.mentor_id  ||  m.mentor_name)))
      );

      if (memberSelect) {
        memberSelect.innerHTML = '<option value="">Unassigned</option>' +
          (all || []).filter((m) => m.is_eligible)
            .map((m) => `<option value="${esc(m.id)}">${esc(m.formatted_name)}</option>`).join("");
      }

      // Unmentored alert — only Level 0 (new members; Level 1+ no longer need a mentor)
      const unmentored = (all || []).filter((m) => m.is_eligible && !m.mentor_id && !m.mentor_name && m.level === 0);
      const alertEl    = qs("[data-tmp-unmentored-alert]", root);
      if (alertEl) {
        alertEl.innerHTML = unmentored.length
          ? `<div style="background:#fff8e1;border:1px solid #ffd54f;border-radius:4px;padding:10px 14px;margin-bottom:12px;font-size:13px;">
              <strong>${unmentored.length} new member${unmentored.length > 1 ? "s have" : " has"} no mentor assigned.</strong>
              Use the Assign Mentor button below to pair them up.
             </div>`
          : "";
      }

      // Sort
      if (!root._vpeSort) root._vpeSort = { col: "name", dir: "asc" };
      const { col: vSortCol, dir: vSortDir } = root._vpeSort;
      const sortedEligible = [...eligible].sort((a, b) => {
        const mul = vSortDir === "asc" ? 1 : -1;
        if (vSortCol === "level") {
          const ld = a.level - b.level;
          return mul * (ld !== 0 ? ld : a.full_name.localeCompare(b.full_name));
        }
        return mul * a.full_name.localeCompare(b.full_name);
      });

      const vpeToggle = qs("[data-tmp-vpe-members-toggle]", root);
      const isOpenVpe = overviewList?.style.display !== "none";
      if (vpeToggle) {
        vpeToggle.textContent = isOpenVpe
          ? `Hide Members (${sortedEligible.length})`
          : `Show Members (${sortedEligible.length})`;
      }

      const sortInd = (col) => col === vSortCol ? (vSortDir === "asc" ? "▲" : "▼") : "↕";
      const levelLabel = (lvl) => lvl === 0 ? "Level 0" : `Level ${lvl}`;

      if (overviewList) {
        const dueMap = Object.fromEntries((root._dueForRoles || []).map((d) => [String(d.id), d]));
        if (overviewCount) overviewCount.textContent = `${sortedEligible.length} member${sortedEligible.length !== 1 ? "s" : ""}`;
        overviewList.innerHTML = sortedEligible.length
          ? `<div class="tmp-table-wrap"><table class="tmp-table">
              <thead><tr>
                <th data-sort-col="name" class="tmp-sortable">Name <span class="tmp-sort-ind">${sortInd("name")}</span></th>
                <th>Pathway</th>
                <th data-sort-col="level" class="tmp-sortable">Level <span class="tmp-sort-ind">${sortInd("level")}</span></th>
                <th>Phone</th>
                <th>Email</th>
                <th>Recent</th>
                <th>Last Role</th>
                <th>Mentor</th>
                <th>Actions</th>
              </tr></thead>
              <tbody>${sortedEligible.map((m) => {
                const inactive = m.recent_participation_count === 0 && m.total_recent_meetings_checked > 0;
                const due = dueMap[String(m.id)];
                const lastRoleCell = due
                  ? `<span class="tmp-tag" style="background:${Number(due.days_since_role) > 28 ? "#b71c1c" : "#ef6c00"};color:#fff;font-size:11px;">${due.days_since_role}d ago</span>`
                  : `<span style="color:var(--tmp-muted);font-size:11px;">—</span>`;
                return `<tr ${inactive ? 'style="background:#fff8e1"' : ""}>
                  <td data-label="Name"><strong>${esc(m.full_name)}</strong>${inactive ? `<br><small style="color:#ef6c00;font-weight:bold">No roles in last ${m.total_recent_meetings_checked} meetings</small>` : ""}</td>
                  <td data-label="Pathway"><small>${esc(m.pathway)}</small></td>
                  <td data-label="Level"><span class="tmp-tag" style="background:#e8eaf6;color:#303f9f;font-size:0.78rem;">${levelLabel(m.level)}</span></td>
                  <td data-label="Phone"><small>${esc(m.phone || "—")}</small></td>
                  <td data-label="Email"><small>${esc(m.email)}</small></td>
                  <td data-label="Recent">${m.recent_participation_count} / ${m.total_recent_meetings_checked}</td>
                  <td data-label="Last Role">${lastRoleCell}</td>
                  <td data-label="Mentor">${esc(m.mentor_name || "—")}</td>
                  <td data-label="Action">${m.level === 0
                    ? `<button class="tmp-small-button" type="button" data-assign-mentor="${esc(m.id)}" data-member-name="${esc(m.full_name)}" data-current-mentor="${esc(m.mentor_id || "")}">
                        ${m.mentor_name ? "Change" : "Assign"} Mentor
                       </button>`
                    : ""}</td>
                </tr>`;
              }).join("")}</tbody></table></div>`
          : "<p>No members match the selected filters.</p>";
      }
    }

    // -- Due for roles (data loaded at init, used by renderMembers + renderRoleStatus) --------

    // -- Meetings list --------------------------------------------------------
    async function renderMeetings(selectedId = null) {
      const meetings = await api("/meetings") || [];
      root._meetings = Array.isArray(meetings) ? meetings : [];

      if (meetingCount) meetingCount.textContent = `${meetings.length} ${meetings.length === 1 ? "meeting" : "meetings"}`;
      meetingSelect.innerHTML  = '<option value="">Select a meeting...</option>' +
        meetings.map((m) => `<option value="${esc(m.id)}">${esc(m.meeting_date)} - ${esc(m.theme)}</option>`).join("");

      renderPendingRequests(root).catch(() => {});
      if (selectedId) meetingSelect.value = selectedId;
      updateRoles();

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting, idx) => {
        const [h, min] = (meeting.start_time || "18:30:00").split(":").map(Number);
        let t = h * 60 + (min || 0);

        // Pure timeline rows — no operational data (status/cooloff/suitability live in Role Status panel)
        const agendaRows = (meeting.assignments || []).map((a) => {
          const start   = formatTime(t);
          const dur     = Number(a.duration || 0);
          t += dur;
          const end     = formatTime(t);
          const isBreak = a.role_name.toLowerCase().startsWith("break");

          if (isBreak) {
            return `<tr style="background:#f5f5f5;">
              <td style="color:var(--tmp-muted);">${start}</td>
              <td style="color:var(--tmp-muted);">${dur}m</td>
              <td style="color:var(--tmp-muted);">${end}</td>
              <td colspan="2" style="color:var(--tmp-muted);font-style:italic;text-align:center;">— Break —</td>
            </tr>`;
          }

          return `<tr>
            <td>${start}</td>
            <td>${dur}m</td>
            <td>${end}</td>
            <td>${esc(a.role_name)}${a.speech_title ? `<br><small style="color:var(--tmp-muted);">${esc(a.speech_title)}</small>` : ""}</td>
            <td>${a.member_name ? esc(a.member_name) : '<em style="color:#ef6c00;">TBA</em>'}</td>
          </tr>`;
        }).join("");

        const totalUsed = t - (h * 60 + (min || 0));
        const limit     = Number(meeting.total_duration || 120);
        const warning   = totalUsed > limit
          ? `<p class="tmp-tag" style="background:#b71c1c;color:#fff;display:block;margin:10px 0;text-align:center;padding:5px;border-radius:4px;">Warning: Agenda (${totalUsed}m) exceeds limit (${limit}m)</p>`
          : "";

        const agendaTable = agendaRows
          ? `<div class="tmp-table-wrap" style="margin-top:12px;">
              <table class="tmp-table">
                <thead><tr><th>Start</th><th>Dur</th><th>End</th><th>Agenda Item</th><th>Member</th></tr></thead>
                <tbody>${agendaRows}</tbody>
              </table>
            </div>`
          : `<p style="color:var(--tmp-muted);margin-top:12px;">No agenda items yet.</p>`;

        // Assignment-readiness badge (role-count, not agenda-item count)
        const assignments = meeting.assignments || [];
        const roleSlots   = assignments.filter((a) => !a.role_name.toLowerCase().startsWith("break"));
        const unassigned  = roleSlots.filter((a) => !a.member_id).length;
        const statusBg    = roleSlots.length === 0 ? "#9e9e9e" : unassigned === 0 ? "#2e7d32" : "#ef6c00";
        const statusLabel = roleSlots.length === 0
          ? "No roles yet"
          : unassigned === 0
            ? "All roles assigned ✓"
            : `${unassigned} role${unassigned > 1 ? "s" : ""} need${unassigned === 1 ? "s" : ""} a member`;

        const defaultOpen = idx === 0;
        const bodyDisplay = defaultOpen ? "block" : "none";
        const ariaExp     = defaultOpen ? "true" : "false";
        const chevronRot  = defaultOpen ? "rotate(90deg)" : "";

        return `<article class="tmp-agenda-card" data-agenda-meeting="${esc(meeting.id)}">
          <button class="tmp-agenda-card-toggle" aria-expanded="${ariaExp}" style="width:100%;background:none;border:none;padding:0;cursor:pointer;text-align:left;">
            <div class="tmp-card-head" style="pointer-events:none;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <h4 style="margin:0;">${esc(meeting.meeting_date)} — ${esc(meeting.theme)}</h4>
                <span class="tmp-tag" style="background:${statusBg};color:#fff;font-size:11px;">${esc(statusLabel)}</span>
              </div>
              <div style="display:flex;align-items:center;gap:6px;">
                <span class="tmp-tag">${esc((meeting.start_time || "18:30").substring(0, 5))}</span>
                ${meeting.requests_close_at ? `<span class="tmp-tag" style="background:#607d8b;color:#fff;">Closes: ${esc(meeting.requests_close_at.substring(0, 16))}</span>` : ""}
                <span class="tmp-chevron" aria-hidden="true" style="transform:${chevronRot};transition:transform 0.2s;">&#9658;</span>
              </div>
            </div>
          </button>
          <div class="tmp-agenda-card-body" style="display:${bodyDisplay};">
            <p style="margin:6px 0 0;color:var(--tmp-muted);font-size:13px;">${esc(meeting.venue || "Venue not set")}${meeting.agenda_notes ? ` · ${esc(meeting.agenda_notes)}` : ""}</p>
            ${warning}
            ${agendaTable}
            <div style="display:flex;gap:10px;margin-top:15px;flex-wrap:wrap;">
              <button class="tmp-button tmp-secondary tmp-small" data-print-agenda="${meeting.id}">Print Agenda</button>
              <button class="tmp-button tmp-danger tmp-small" data-delete-meeting="${meeting.id}">Delete Meeting</button>
            </div>
          </div>
        </article>`;
      }).join("")}</div>`;

      // Keep the Role Status panel in sync with the currently selected meeting
      if (meetingSelect.value) renderRoleStatus(meetingSelect.value);
    }

    // -- Assignment form helpers ----------------------------------------------

    const updateRoles = () => {
      const mid = meetingSelect.value;
      clearForm(assignmentForm);
      assignmentForm.elements.role_name.value = "";
      assignmentForm.elements.meeting_id.value = mid;
      delete assignmentForm._tmp_role_name;
      toggleFieldsByRole("");

      const meeting = (root._meetings || []).find((m) => String(m.id) === mid);

      // Deduplicate agenda rows: strip parenthetical notes → base role name.
      // Keep the first slot per base role so TMOD doesn't appear 4× and Break is hidden.
      const seenBases = new Set();
      const dedupedSlots = [];
      for (const a of (meeting?.assignments || [])) {
        const base = a.role_name.replace(/\s*\(.*?\)\s*/g, "").trim();
        if (base.toLowerCase() === "break" || seenBases.has(base)) continue;
        seenBases.add(base);
        dedupedSlots.push({ id: a.id, base, memberName: a.member_name });
      }

      dedupedSlots.sort((a, b) => roleSort(a.base) - roleSort(b.base));
      let html = '<option value="">-- Select a Slot --</option>';
      html += dedupedSlots.map((s) =>
        `<option value="id:${esc(s.id)}">${esc(s.base)}${s.memberName ? ` (${esc(s.memberName)})` : " (Unassigned)"}</option>`
      ).join("");
      roleSelect.innerHTML = html;
    };

    function roleGateLevel(roleName) {
      const r = (roleName || "").toLowerCase();
      const gates = TMPortal.roleGateLevels || {};
      for (const [pattern, minLevel] of Object.entries(gates)) {
        if (r.includes(pattern)) return Number(minLevel);
      }
      return 0;
    }

    function updateMemberDropdown(roleName) {
      if (!memberSelect || !root._allMembers) return;
      const baseRole = (roleName || "").replace(/\s*\(.*?\)\s*/g, "").replace(/\s+\d+$/, "").trim();
      if (baseRole.toLowerCase() === "break") {
        memberSelect.innerHTML = '<option value="">Break — no member needed</option>';
        memberSelect.disabled  = true;
        return;
      }
      memberSelect.disabled = false;
      const minLevel  = roleGateLevel(roleName);
      const currentId = assignmentForm.elements.id?.value;
      const mid       = meetingSelect.value;
      const meeting   = (root._meetings || []).find((m) => String(m.id) === mid);
      const takenIds  = (meeting?.assignments || [])
        .filter((a) => a.member_id && String(a.id) !== currentId)
        .map((a) => String(a.member_id));

      const eligible   = root._allMembers.filter((m) => m.is_eligible && m.level >= minLevel && !takenIds.includes(String(m.id)));
      const ineligible = roleName ? root._allMembers.filter((m) => m.is_eligible && m.level < minLevel) : [];

      let html = '<option value="">Unassigned</option>';
      html += eligible.map((m) => `<option value="${esc(m.id)}">${esc(m.full_name)} (L${m.level})</option>`).join("");
      if (ineligible.length) {
        html += `<optgroup label="Not eligible — Level ${minLevel}+ required">`;
        html += ineligible.map((m) => `<option disabled value="">${esc(m.full_name)} (L${m.level})</option>`).join("");
        html += `</optgroup>`;
      }
      memberSelect.innerHTML = html;
    }

    function toggleFieldsByRole(roleName) {
      const rLower = (roleName || "").toLowerCase();
      // Speech title: only for Speaker slots (Speaker, Speaker 1, etc.) — not Table Topics Speaker
      if (speechWrapper) speechWrapper.style.display = rLower.startsWith("speaker") ? "block" : "none";
      if (presSeries) presSeries.style.display = rLower.includes("educational presentation") ? "block" : "none";
    }

    function isCooloffRole(roleName) {
      const base = (roleName || "").replace(/\s*\(.*?\)\s*/g, "").replace(/\s+\d+$/, "").trim().toLowerCase();
      return /^speaker$/i.test(base)
        || base.includes("toastmaster")
        || base.includes("general evaluator");
    }

    async function checkCooloffForMember(memberId, roleName) {
      if (!memberId || !roleName || !isCooloffRole(roleName)) {
        if (cooloffWarning) { cooloffWarning.style.display = "none"; cooloffWarning.innerHTML = ""; }
        if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
        return;
      }
      const currentMeetingId = meetingSelect.value;
      const meeting = (root._meetings || []).find((m) => String(m.id) === currentMeetingId);
      if (!meeting) return;

      if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "block";
      if (cooloffWarning) {
        cooloffWarning.style.display = "block";
        cooloffWarning.innerHTML = `<div style="padding:8px;background:#fff3e0;border:1px solid #ffb74d;border-radius:4px;font-size:12px;">
          <strong>Cooloff check:</strong> If this member performed "${esc(roleName)}" within the last ${root._cooloffWeeks || 4} weeks, confirm the override below before saving.
        </div>`;
      }
    }

    // -- Role Status panel ---------------------------------------------------
    // Groups multi-segment roles (e.g. Grammarian Intro + Grammarian Closing = one row),
    // renders an inline member dropdown per role, and handles save + slot removal.
    function renderRoleStatus(meetingId) {
      const panel = qs("[data-tmp-role-status-panel]", root);
      if (!panel) return;
      if (!meetingId) { panel.style.display = "none"; return; }

      const meeting = (root._meetings || []).find((m) => String(m.id) === String(meetingId));
      if (!meeting) { panel.style.display = "none"; return; }

      // Group by base role — collapses multi-segment duplicates (Intro+Closing) into one row
      const roleGroups = {};
      for (const a of (meeting.assignments || [])) {
        if (a.role_name.toLowerCase().startsWith("break")) continue;
        const base = a.role_name.replace(/\s*\(.*?\)\s*/g, "").trim();
        if (!roleGroups[base]) roleGroups[base] = [];
        roleGroups[base].push(a);
      }
      if (!Object.keys(roleGroups).length) { panel.style.display = "none"; return; }

      panel.style.display = "block";

      const dueRoleMap = Object.fromEntries((root._dueForRoles || []).map((d) => [String(d.id), d]));

      const rows = Object.entries(roleGroups)
        .sort(([a], [b]) => roleSort(a) - roleSort(b))
        .map(([baseRole, group]) => {
        const primary = group[0];
        const allIds  = group.map((a) => a.id).join(",");
        const minLevel = roleGateLevel(primary.role_name);

        // Mark members already assigned to OTHER roles in this meeting
        const takenIds = new Set(
          Object.entries(roleGroups)
            .filter(([k]) => k !== baseRole)
            .flatMap(([, g]) => g.filter((a) => a.member_id).map((a) => String(a.member_id)))
        );

        const eligible = (root._allMembers || []).filter((m) => m.is_eligible && m.level >= minLevel);
        const overdueEligible = eligible.filter((m) => dueRoleMap[String(m.id)])
          .sort((a, b) => Number(dueRoleMap[String(b.id)].days_since_role) - Number(dueRoleMap[String(a.id)].days_since_role));
        const regularEligible = eligible.filter((m) => !dueRoleMap[String(m.id)]);

        let opts = '<option value="">— Unassigned —</option>';
        if (overdueEligible.length) {
          opts += `<optgroup label="Haven't had a role recently">` +
            overdueEligible.map((m) => {
              const note = takenIds.has(String(m.id)) ? " (other role)" : "";
              const days = dueRoleMap[String(m.id)].days_since_role;
              return `<option value="${esc(m.id)}" ${String(m.id) === String(primary.member_id) ? "selected" : ""}>${esc(m.full_name)} (L${m.level}) — ${days}d${note}</option>`;
            }).join("") + `</optgroup>`;
        }
        opts += `<optgroup label="All eligible">` +
          regularEligible.map((m) => {
            const note = takenIds.has(String(m.id)) ? " (other role)" : "";
            return `<option value="${esc(m.id)}" ${String(m.id) === String(primary.member_id) ? "selected" : ""}>${esc(m.full_name)} (L${m.level})${note}</option>`;
          }).join("") + `</optgroup>`;

        const notes = [];
        if (primary.status === "Needs replacement") notes.push(`<span class="tmp-tag" style="background:#b71c1c;color:#fff;font-size:10px;">⚠ Needs replacement</span>`);
        if (primary.cooloff_override == 1) notes.push(`<span class="tmp-tag" style="background:#ff9800;color:#fff;font-size:10px;" title="${esc(primary.override_reason || "")}">Cooloff override</span>`);
        if (primary.suitability && !primary.suitability.suitable) notes.push(`<span class="tmp-tag" style="background:#ffebee;color:#b71c1c;font-size:10px;">${esc(primary.suitability.reason)}</span>`);

        return `<tr>
          <td data-label="Role" style="white-space:nowrap;">${esc(baseRole)}</td>
          <td data-label="Member">
            <select data-assign-roles="${esc(allIds)}" style="width:100%;max-width:220px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;">${opts}</select>
          </td>
          <td data-label="Notes" style="font-size:11px;">${notes.join(" ") || "—"}</td>
          <td data-label="Action"><button class="tmp-small-button tmp-danger" type="button" data-delete-roles="${esc(allIds)}" data-role-name="${esc(baseRole)}">Remove slot</button></td>
        </tr>`;
      }).join("");

      panel.innerHTML = `
        <div style="margin-bottom:10px;">
          <strong style="font-size:13px;">${esc(meeting.meeting_date)} — ${esc(meeting.theme)}</strong>
          <span style="font-size:12px;color:var(--tmp-muted);margin-left:8px;">Select a member in any row to assign. Use the form above only for new slots, durations, or speech titles.</span>
        </div>
        <div class="tmp-table-wrap">
          <table class="tmp-table" style="font-size:0.88rem;">
            <thead><tr><th>Role</th><th>Member</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;

      // Register change (assign) and click (remove) once per panel lifetime
      if (!panel._listenersAdded) {
        panel._listenersAdded = true;

        panel.addEventListener("change", async (e) => {
          const sel = e.target.closest("[data-assign-roles]");
          if (!sel) return;
          const ids      = sel.dataset.assignRoles.split(",");
          const memberId = sel.value || null;
          sel.disabled   = true;
          try {
            for (const id of ids) {
              await api("/assignments", { method: "POST", body: JSON.stringify({ id: parseInt(id), member_id: memberId, status: memberId ? "Confirmed" : "Planned" }) });
            }
            await renderMeetings(meetingSelect.value);
            updateMemberDashboard().catch(() => {});
          } catch (err) {
            alert("Failed to assign: " + err.message);
            sel.disabled = false;
          }
        });

        panel.addEventListener("click", async (e) => {
          const btn = e.target.closest("[data-delete-roles]");
          if (!btn) return;
          const roleName = btn.dataset.roleName || "this role";
          if (!confirm(`Remove the "${roleName}" slot from this meeting?\n\nThe agenda item will be deleted. Use this when a role won't be needed for this meeting.`)) return;
          btn.disabled = true;
          try {
            for (const id of btn.dataset.deleteRoles.split(",")) {
              await api(`/assignments/${id}`, { method: "DELETE" });
            }
            await renderMeetings(meetingSelect.value);
            updateMemberDashboard().catch(() => {});
          } catch (err) {
            alert("Failed to remove: " + err.message);
            btn.disabled = false;
          }
        });
      }
    }

    meetingSelect.addEventListener("change", () => {
      updateRoles();
      renderRoleStatus(meetingSelect.value);
    });

    const suggestionsPanel = qs("[data-tmp-role-suggestions]", assignmentForm);

    function hideSuggestions() {
      if (suggestionsPanel) { suggestionsPanel.style.display = "none"; suggestionsPanel.innerHTML = ""; }
    }

    function showSuggestButton(meetingId, roleName) {
      if (!suggestionsPanel || !meetingId || !roleName) return;
      suggestionsPanel.style.display = "block";
      suggestionsPanel.innerHTML = `<button type="button" class="tmp-link-button" data-tmp-fetch-suggestions style="font-size:12px;color:#1976d2;">
        Suggest a member for "${esc(roleName)}" →
      </button>`;
      qs("[data-tmp-fetch-suggestions]", suggestionsPanel)?.addEventListener("click", async () => {
        suggestionsPanel.innerHTML = `<span style="font-size:12px;color:var(--tmp-muted);">Loading suggestions…</span>`;
        try {
          const data  = await api(`/meetings/${meetingId}/suggestions`);
          const suggs = (data.suggestions || []).filter((s) => {
            const base = (roleName || "").replace(/\s*\(.*?\)\s*/g, "").replace(/\s+\d+$/, "").trim().toLowerCase();
            return s.role_name.toLowerCase().includes(base) || base.includes(s.role_name.toLowerCase());
          }).slice(0, 3);
          if (!suggs.length) {
            suggestionsPanel.innerHTML = `<span style="font-size:12px;color:var(--tmp-muted);">No suggestions available for this slot.</span>`;
            return;
          }
          suggestionsPanel.innerHTML = `<div style="background:#f0f7ff;border:1px solid #bbdefb;border-radius:4px;padding:10px 12px;font-size:12px;">
            <p style="margin:0 0 6px;font-weight:bold;color:#01579b;">Suggested members</p>
            ${suggs.map((s) => `<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #e3f2fd;">
              <span><strong>${esc(s.suggested_member_name)}</strong> <small style="color:var(--tmp-muted);">${esc(s.progression_note || "")}</small></span>
              <button type="button" class="tmp-small-button" data-pick-suggested="${esc(s.suggested_member_id)}">Select</button>
            </div>`).join("")}
          </div>`;
          suggestionsPanel.querySelectorAll("[data-pick-suggested]").forEach((btn) => {
            btn.addEventListener("click", () => {
              if (memberSelect) memberSelect.value = btn.dataset.pickSuggested;
              memberSelect?.dispatchEvent(new Event("change"));
              hideSuggestions();
            });
          });
        } catch (err) {
          suggestionsPanel.innerHTML = `<span style="font-size:12px;color:var(--tmp-burgundy);">Error: ${esc(err.message)}</span>`;
        }
      });
    }

    roleSelect.addEventListener("change", () => {
      const val = roleSelect.value;
      const mid = meetingSelect.value;

      if (!val) {
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = mid;
        assignmentForm.elements.role_name.value  = "";
        toggleFieldsByRole("");
        hideSuggestions();
        if (cooloffWarning) { cooloffWarning.style.display = "none"; }
        if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
        return;
      }

      const meeting = (root._meetings || []).find((m) => String(m.id) === mid);
      let roleName  = "";

      if (val.startsWith("id:")) {
        const id   = val.split(":")[1];
        const asgn = meeting?.assignments.find((a) => String(a.id) === id);
        if (asgn) {
          fillForm(assignmentForm, asgn);
          assignmentForm.elements.meeting_id.value = mid;
          roleSelect.value = val;
          roleName = asgn.role_name;
          updateMemberDropdown(roleName);
          if (asgn.member_id) assignmentForm.elements.member_id.value = asgn.member_id;
        }
      } else if (val.startsWith("name:")) {
        const name = val.split(":")[1];
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = mid;
        roleSelect.value = val;
        assignmentForm.elements.role_name.value  = name;
        roleName = name;
      }

      toggleFieldsByRole(roleName);
      updateMemberDropdown(roleName);
      const selMemberId = assignmentForm.elements.member_id?.value;
      if (selMemberId) checkCooloffForMember(selMemberId, roleName);
      // Show inline suggestion button for this slot
      if (roleName && mid) showSuggestButton(mid, roleName);
      else hideSuggestions();
    });

    memberSelect?.addEventListener("change", () => {
      const roleName = assignmentForm.elements.role_name?.value || "";
      checkCooloffForMember(memberSelect.value, roleName);
      // Auto-set status based on whether a member is selected
      const statusSel = assignmentForm.elements.status;
      if (statusSel && !assignmentForm.elements.id?.value) {
        statusSel.value = memberSelect.value ? "Confirmed" : "Planned";
      }
    });

    // Meeting form submit
    meetingForm.addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const btn      = ev.target.querySelector("button[type=submit]");
      const statusEl = qs("[data-tmp-meeting-save-status]", root);
      if (btn) btn.disabled = true;
      try {
        const d    = formData(meetingForm);
        const newM = await api("/meetings", { method: "POST", body: JSON.stringify(d) });
        clearForm(meetingForm);
        // Auto-collapse the form after a successful save
        const formToggle = qs("[data-tmp-meeting-form-toggle]", root);
        const formBody   = qs("[data-tmp-meeting-form-body]", root);
        if (formToggle) { formToggle.setAttribute("aria-expanded", "false"); qs(".tmp-chevron", formToggle).style.transform = ""; }
        if (formBody)   formBody.style.display = "none";
        // Inline success feedback
        if (statusEl) {
          statusEl.textContent = `Meeting for ${esc(d.meeting_date || "new date")} created — roles ready.`;
          statusEl.style.color = "#2e7d32";
          setTimeout(() => { if (statusEl) { statusEl.textContent = ""; } }, 4000);
        }
        await renderMeetings(newM.id);
      } catch (err) {
        if (statusEl) { statusEl.textContent = "Failed: " + err.message; statusEl.style.color = "#c62828"; }
        else alert("Failed to save meeting: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    // Assignment form submit
    assignmentForm.addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const btn = ev.target.querySelector("button[type=submit]");
      if (btn) btn.disabled = true;
      const retainMeetingId = meetingSelect.value;
      try {
        const d = formData(assignmentForm);
        await api("/assignments", { method: "POST", body: JSON.stringify(d) });
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = retainMeetingId;
        toggleFieldsByRole("");
        hideSuggestions();
        if (cooloffWarning) cooloffWarning.style.display = "none";
        if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
        await renderMeetings(retainMeetingId);
        updateRoles();
        renderRoleStatus(retainMeetingId);
      } catch (err) {
        alert("Failed to save assignment: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    // Collapsible meeting form toggle
    qs("[data-tmp-meeting-form-toggle]", root)?.addEventListener("click", (e) => {
      const btn  = e.currentTarget;
      const open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      const body = qs("[data-tmp-meeting-form-body]", root);
      if (body) body.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", btn);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
    });

    // "Customise roles" toggle — shows/hides the checkbox grid
    qs("[data-tmp-customise-roles]", root)?.addEventListener("click", (e) => {
      const btn  = e.currentTarget;
      const grid = qs("[data-tmp-roles-grid]", root);
      if (!grid) return;
      const open = grid.style.display !== "none";
      grid.style.display = open ? "none" : "grid";
      btn.textContent = open ? "Customise roles ▾" : "Use standard template ▴";
    });

    // Auto-fill deadline = meeting date − 3 days at 18:00 when date changes
    meetingForm.elements.meeting_date?.addEventListener("change", () => {
      const dateVal = meetingForm.elements.meeting_date.value;
      if (!dateVal) return;
      const deadlineInput = meetingForm.elements.requests_close_at;
      if (!deadlineInput || deadlineInput.value) return; // don't overwrite if already set
      const d = new Date(dateVal + "T00:00:00");
      d.setDate(d.getDate() - 3);
      const yyyy = d.getFullYear();
      const mm   = String(d.getMonth() + 1).padStart(2, "0");
      const dd   = String(d.getDate()).padStart(2, "0");
      deadlineInput.value = `${yyyy}-${mm}-${dd}T18:00`;
    });

    qs("[data-tmp-clear-meeting]",    root)?.addEventListener("click", () => clearForm(meetingForm));
    qs("[data-tmp-clear-assignment]", root)?.addEventListener("click", () => {
      clearForm(assignmentForm);
      toggleFieldsByRole("");
      hideSuggestions();
      if (cooloffWarning) cooloffWarning.style.display = "none";
      if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
    });

    // Meeting list event delegation
    meetingList.addEventListener("click", async (e) => {
      // Collapsible agenda card toggle
      const cardToggle = e.target.closest(".tmp-agenda-card-toggle");
      if (cardToggle) {
        const open = cardToggle.getAttribute("aria-expanded") === "true";
        cardToggle.setAttribute("aria-expanded", String(!open));
        const body    = cardToggle.nextElementSibling;
        if (body) body.style.display = open ? "none" : "block";
        const chevron = cardToggle.querySelector(".tmp-chevron");
        if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
        return;
      }

      const print        = e.target.closest("[data-print-agenda]");
      const viewConflicts= e.target.closest("[data-view-conflicts]");
      const delMeeting   = e.target.closest("[data-delete-meeting]");
      const approveReq   = e.target.closest("[data-vpe-approve-req]");

      if (delMeeting) {
        if (confirm("Delete this meeting and all its assignments permanently?")) {
          e.target.closest("article")?.remove();
          await api(`/meetings/${delMeeting.dataset.deleteMeeting}`, { method: "DELETE" });
          meetingSelect.value = "";
          updateRoles();
          await renderMeetings();
          updateMemberDashboard().catch(() => {});
        }
      } else if (approveReq) {
        await api("/assignments", { method: "POST", body: JSON.stringify({ id: approveReq.dataset.vpeApproveReq, member_id: approveReq.dataset.vpeMemberId, status: "Confirmed" }) });
        await renderMeetings();
      } else if (viewConflicts) {
        const aId      = viewConflicts.dataset.viewConflicts;
        const conflicts = await api(`/assignments/${aId}/conflicts`);
        if (!conflicts.length) { alert("No other members have requested this role."); return; }

        const opts = conflicts.map((c, i) => {
          const p1    = String(c.priority) === "1";
          const label = `${i + 1}. ${p1 ? "⭐ " : ""}${c.member_name} — P${c.priority} | Level ${c.level} | ${c.pathway}`;
          return label;
        }).join("\n");

        const choice = prompt(`Conflicting requests:\n\n${opts}\n\nEnter number to assign, or Cancel:`);
        const idx    = parseInt(choice, 10) - 1;
        if (!isNaN(idx) && conflicts[idx]) {
          await api("/assignments", { method: "POST", body: JSON.stringify({ id: aId, member_id: conflicts[idx].member_id, status: "Confirmed" }) });
          await renderMeetings();
        }
      } else if (print) {
        const m = root._meetings.find((x) => String(x.id) === print.dataset.printAgenda);
        if (m) generatePrintView(m);
      }
    });

    // Mentor assignment modal logic
    const modal       = document.getElementById("tmp-mentor-modal");
    const mentorSel   = document.getElementById("tmp-mentor-select");
    const modalLabel  = document.getElementById("tmp-mentor-modal-member");
    const modalCancel = document.getElementById("tmp-mentor-modal-cancel");
    const modalSave   = document.getElementById("tmp-mentor-modal-save");
    let pendingMentorMemberId = null;

    if (modal) {
      overviewList?.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-assign-mentor]");
        if (!btn) return;

        pendingMentorMemberId = btn.dataset.assignMentor;
        const memberName      = btn.dataset.memberName;
        const currentMentorId = btn.dataset.currentMentor;

        if (modalLabel) modalLabel.textContent = `Assigning mentor for: ${memberName}`;

        // Fetch eligible mentors
        modal.style.display = "flex";
        if (mentorSel) mentorSel.innerHTML = '<option value="">Loading...</option>';

        let mentors = [];
        try {
          mentors = await api("/members/eligible-mentors");
          if (!Array.isArray(mentors)) mentors = [];
        } catch (err) {
          console.error("eligible-mentors fetch failed:", err);
          if (mentorSel) mentorSel.innerHTML = `<option value="" disabled>Error: ${esc(err.message)}</option>`;
          return;
        }

        if (mentorSel) {
          mentorSel.innerHTML = mentors.length === 0
            ? '<option value="">-- No eligible mentors (need Level 2+ Active member) --</option>'
            : '<option value="">-- No mentor / Remove --</option>' +
              mentors.map((m) =>
                `<option value="${esc(m.id)}" ${String(m.id) === currentMentorId ? "selected" : ""}>${esc(m.full_name)} — Level ${m.level} (${esc(m.pathway)})</option>`
              ).join("");
        }
      });

      modalCancel?.addEventListener("click", () => {
        modal.style.display = "none";
        pendingMentorMemberId = null;
      });

      const modalError = modal.querySelector("#tmp-mentor-modal-error");

      modalSave?.addEventListener("click", async () => {
        if (!pendingMentorMemberId) return;
        if (modalError) { modalError.textContent = ""; modalError.style.display = "none"; }
        modalSave.disabled = true;
        try {
          await api("/members", {
            method: "POST",
            body: JSON.stringify({ id: pendingMentorMemberId, mentor_id: mentorSel?.value || null }),
          });
          modal.style.display = "none";
          pendingMentorMemberId = null;
          await renderMembers(true);
        } catch (err) {
          if (modalError) {
            modalError.textContent = "Save failed: " + err.message;
            modalError.style.display = "block";
          }
          modalSave.disabled = false;
        } finally {
          // only re-enable on success path (error path does it above to keep modal open)
          if (modal.style.display === "none") modalSave.disabled = false;
        }
      });

      modal.addEventListener("click", (e) => {
        if (e.target === modal) { modal.style.display = "none"; pendingMentorMemberId = null; }
      });
    }

    vpeSearch?.addEventListener("input",   () => renderMembers());
    vpePathway?.addEventListener("change", () => renderMembers());
    vpeLevel?.addEventListener("change",   () => renderMembers());
    vpeMentorFilt?.addEventListener("change", () => renderMembers());

    // VPE member list: collapsed by default + sort via event delegation
    if (overviewList) overviewList.style.display = "none";
    const vpeToggle = qs("[data-tmp-vpe-members-toggle]", root);
    vpeToggle?.addEventListener("click", () => {
      const open = overviewList?.style.display !== "none";
      if (overviewList) overviewList.style.display = open ? "none" : "";
      renderMembers();
    });

    overviewList?.addEventListener("click", (ev) => {
      const th = ev.target.closest("[data-sort-col]");
      if (!th) return;
      const col = th.dataset.sortCol;
      if (!root._vpeSort) root._vpeSort = { col: "name", dir: "asc" };
      root._vpeSort = root._vpeSort.col === col
        ? { col, dir: root._vpeSort.dir === "asc" ? "desc" : "asc" }
        : { col, dir: "asc" };
      renderMembers();
    });

    // -- Role gate settings panel (VPE/admin only) ----------------------------
    async function renderRoleGateSettings() {
      const panel = qs("[data-tmp-gate-settings-panel]", root);
      const body  = qs("[data-tmp-gate-settings-body]",  root);
      if (!panel || !body) return;

      let gates;
      try {
        gates = await api("/settings/role-gates");
      } catch (err) {
        body.innerHTML = `<p style="color:var(--tmp-burgundy)">Could not load gate settings: ${esc(err.message)}</p>`;
        return;
      }

      body.innerHTML = `
        <p style="font-size:12px;color:var(--tmp-muted);margin-bottom:10px;">
          These level gates control who can take each role. Changes take effect immediately for new suggestions and suitability checks.
        </p>
        <table class="tmp-table" style="font-size:0.88rem;">
          <thead><tr><th>Role Pattern</th><th>Minimum Level</th></tr></thead>
          <tbody>${Object.entries(gates).map(([pattern, lvl]) => `
            <tr>
              <td style="text-transform:capitalize;">${esc(pattern)}</td>
              <td><select data-gate-pattern="${esc(pattern)}" style="padding:3px 6px;">
                <option value="0" ${Number(lvl) === 0 ? "selected" : ""}>L0 (anyone)</option>
                <option value="1" ${Number(lvl) === 1 ? "selected" : ""}>L1+</option>
                <option value="2" ${Number(lvl) === 2 ? "selected" : ""}>L2+</option>
                <option value="3" ${Number(lvl) === 3 ? "selected" : ""}>L3+</option>
                <option value="4" ${Number(lvl) === 4 ? "selected" : ""}>L4+</option>
              </select></td>
            </tr>`).join("")}
          </tbody>
        </table>
        <div style="margin-top:12px;">
          <button class="tmp-small-button tmp-primary" id="tmp-gate-save">Save Gate Settings</button>
          <span id="tmp-gate-status" style="margin-left:10px;font-size:12px;color:var(--tmp-muted);"></span>
        </div>`;

      qs("#tmp-gate-save", root)?.addEventListener("click", async () => {
        const saveBtn    = qs("#tmp-gate-save", root);
        const statusSpan = qs("#tmp-gate-status", root);
        saveBtn.disabled = true;
        if (statusSpan) statusSpan.textContent = "Saving…";
        const updated = {};
        qsa("[data-gate-pattern]", body).forEach((sel) => {
          updated[sel.dataset.gatePattern] = Number(sel.value);
        });
        try {
          const saved = await api("/settings/role-gates", { method: "POST", body: JSON.stringify(updated) });
          TMPortal.roleGateLevels = saved;
          if (statusSpan) { statusSpan.textContent = "Saved!"; statusSpan.style.color = "#2e7d32"; }
          setTimeout(() => { if (statusSpan) statusSpan.textContent = ""; }, 2000);
        } catch (err) {
          if (statusSpan) { statusSpan.textContent = "Error: " + err.message; statusSpan.style.color = "#c62828"; }
        } finally {
          saveBtn.disabled = false;
        }
      });
    }

    // Inject gate settings panel HTML before first use
    const gateSection = document.createElement("section");
    gateSection.className = "tmp-panel";
    gateSection.innerHTML = `
      <button class="tmp-collapsible-toggle" data-tmp-gate-settings-toggle aria-expanded="false" style="width:100%;text-align:left;">
        Role Gate Settings
        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
      </button>
      <div data-tmp-gate-settings-body style="display:none;margin-top:14px;"></div>`;
    gateSection.setAttribute("data-tmp-gate-settings-panel", "");
    root.appendChild(gateSection);

    qs("[data-tmp-gate-settings-toggle]", root)?.addEventListener("click", async (e) => {
      const btn  = e.currentTarget;
      const open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      const body = qs("[data-tmp-gate-settings-body]", root);
      if (body) body.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", btn);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
      if (!open) await renderRoleGateSettings();
    });

    // Approve All Recommended button
    qs("[data-tmp-approve-all-btn]", root)?.addEventListener("click", async (e) => {
      const btn = e.target;
      if (btn.disabled) return;
      if (!confirm("Approve all recommended requests for all upcoming meetings?")) return;
      btn.disabled = true;
      btn.textContent = "Approving all…";
      try {
        const result = await api("/assignments/approve-all-recommended", { method: "POST", body: JSON.stringify({}) });
        if (result.success) {
          alert(`✓ Approved ${result.approved} request${result.approved !== 1 ? 's' : ''}!`);
          await renderMeetings();
        } else if (result.failed && result.failed.length > 0) {
          const failures = result.failed.map((f) => `${f.member}: ${f.reason}`).join("\n");
          alert(`⚠ Some approvals failed:\n\n${failures}`);
          await renderMeetings();
        }
      } catch (err) {
        alert("Bulk approval failed: " + err.message);
      } finally {
        btn.disabled = false;
        btn.textContent = "Approve All Recommended";
      }
    });

    try {
      root._dueForRoles = await api("/members/due-for-roles").catch(() => []);
      await Promise.all([
        renderMembers(true).catch((err) => console.error("Members load failed:", err)),
        renderMeetings(),
      ]);
    } catch (err) {
      console.error("VPE init error:", err);
      meetingList.innerHTML = `<div class="tmp-panel tmp-danger"><h3>Error loading agendas</h3><p>${esc(err.message)}</p></div>`;
    }
  }

  async function renderPendingRequests(root) {
    const count = qs("[data-tmp-request-count]", root);
    const list  = qs("[data-tmp-vpe-requests]", root);
    const approveBtn = qs("[data-tmp-approve-all-btn]", root);
    if (!count || !list) {
      console.error("Pending requests elements not found", { count: !!count, list: !!list });
      return;
    }

    let data;
    try {
      data = await api("/meetings/requests");
    } catch (err) {
      console.error("Failed to fetch pending requests:", err);
      list.innerHTML = `<div style="color:var(--tmp-burgundy)">Error loading requests: ${esc(err.message)}</div>`;
      return;
    }

    const { meetings } = data;
    const totalRequests = meetings.reduce((sum, m) => sum + m.totalRequests, 0);
    if (count) count.textContent = `${totalRequests} pending`;

    if (totalRequests === 0) {
      list.innerHTML = "<p>No pending requests across upcoming meetings.</p>";
      if (approveBtn) approveBtn.style.display = "none";
      return;
    }

    if (approveBtn) approveBtn.style.display = "block";

    let html = '<div style="display:flex;flex-direction:column;gap:12px;">';
    for (const meeting of meetings) {
      html += `<div style="border:1px solid #e0e0e0;border-radius:4px;padding:12px;background:#fafafa;">
        <div style="font-weight:bold;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
          <span>${esc(meeting.meetingDate)} — ${esc(meeting.theme)}</span>
          <span style="font-size:12px;color:#666;">${meeting.totalRequests} request${meeting.totalRequests !== 1 ? 's' : ''}</span>
        </div>`;

      for (const role of meeting.roles.slice().sort((a, b) => roleSort(a.roleName) - roleSort(b.roleName))) {
        html += `<div style="margin-left:12px;padding:8px;background:#fff;border-left:3px solid #01579b;margin-bottom:8px;">
          <div style="font-weight:600;margin-bottom:6px;">${esc(role.roleName)}</div>`;

        for (const req of role.requests) {
          const scoreColor = req.score >= 100 ? '#2e7d32' : req.score >= 75 ? '#ef6c00' : '#999';
          const recommendedBadge = req.isRecommended
            ? '<span class="tmp-tag" style="background:#2e7d32;color:#fff;font-weight:bold;margin-left:4px;">✓ RECOMMENDED</span>'
            : '';
          const reasonsHtml = req.reasons && req.reasons.length > 0
            ? req.reasons.map((r) => `<span class="tmp-tag" style="background:#e3f2fd;color:#01579b;font-size:11px;margin:2px;">${esc(r)}</span>`).join('')
            : '';

          html += `<div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px;border-bottom:1px solid #eee;background:#fafafa;">
            <div style="flex:1;">
              <div style="font-size:13px;margin-bottom:4px;">
                <strong>${esc(req.memberName)}</strong> (L${req.memberLevel}, ${esc(req.pathway)})
                <span class="tmp-tag" style="background:#f5f5f5;margin:0 4px;">P${req.priority}</span>
                ${recommendedBadge}
              </div>
              <div style="font-size:11px;color:#666;">
                <span style="font-weight:bold;color:${scoreColor};">Score: ${req.score}</span>
                ${reasonsHtml}
              </div>
            </div>
            <button class="tmp-small-button" data-approve-request="${req.requestId}" data-member-id="${req.memberId}" data-meeting-id="${meeting.meetingId}" data-role-name="${esc(role.roleName)}" style="white-space:nowrap;margin-left:8px;">
              Approve
            </button>
          </div>`;
        }

        html += `</div>`;
      }

      html += `</div>`;
    }
    html += `</div>`;

    list.innerHTML = html;

    // Register the approve-click handler only once per root element.
    // renderPendingRequests is called after every approval, so without this guard
    // each re-render would stack another listener on the same element, causing
    // duplicate API calls that trigger the "already approved" guard on the server.
    if (!root._vpeApproveListenerAdded) {
      root._vpeApproveListenerAdded = true;
      qs("[data-tmp-vpe-requests]", root)?.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-approve-request]");
        if (!btn || btn.disabled) return;

        const requestId = btn.dataset.approveRequest;
        const memberId = btn.dataset.memberId;
        const meetingId = btn.dataset.meetingId;
        const roleName = btn.dataset.roleName;

        btn.disabled = true;
        btn.textContent = "Approving...";
        try {
          await api("/requests/approve-and-cascade-reject", {
            method: "POST",
            body: JSON.stringify({
              request_id: parseInt(requestId),
              member_id: parseInt(memberId),
              meeting_id: parseInt(meetingId),
              role_name: roleName
            })
          });
          await renderPendingRequests(root);
          refreshVPE();
          updateMemberDashboard().catch(() => {});
        } catch (err) {
          alert("Error: " + err.message);
          btn.disabled = false;
          btn.textContent = "Approve";
        }
      });
    }
  }

  // ===========================================================================
  // ENROLMENT
  // ===========================================================================

  async function initEnrolment() {
    const form   = qs("[data-tmc-enrol-form]");
    const status = qs("[data-tmc-form-status]");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      status.textContent = "Submitting application...";
      try {
        const d = formData(form);
        await api("/enrol", { method: "POST", body: JSON.stringify(d) });
        status.textContent = `Thank you, ${d.name}. Your application has been received!`;
        form.reset();
      } catch (err) {
        status.textContent = "Failed to submit: " + err.message;
      }
    });
  }

  // ── VPE Voting Panel ────────────────────────────────────────────────────────

  function initVotingPanel() {
    const panel = qs('[data-tmp-voting-panel]');
    if (!panel) return;

    const meetingSelect   = qs('[data-tmp-voting-meeting-select]', panel);
    const meetingLabel    = qs('[data-tmp-voting-meeting-label]', panel);
    const ttEntry         = qs('[data-tmp-tt-entry]', panel);
    const ttNameInput     = qs('[data-tmp-tt-name]', panel);
    const ttGuestWrap     = qs('[data-tmp-tt-guest-wrap]', panel);
    const ttMemberSelect  = qs('[data-tmp-tt-member-select]', panel);
    const ttAddBtn        = qs('[data-tmp-tt-add-btn]', panel);
    const ttSpeakerList   = qs('[data-tmp-tt-speaker-list]', panel);
    const nomineesBlock   = qs('[data-tmp-voting-nominees]', panel);
    const nomineesSummary = qs('[data-tmp-nominees-summary]', panel);
    const refreshBtn      = qs('[data-tmp-refresh-nominees-btn]', panel);
    const resultsBtn      = qs('[data-tmp-voting-results-btn]', panel);
    const resultsBlock    = qs('[data-tmp-voting-results]', panel);
    const openPollBtn     = qs('[data-tmp-open-poll-btn]', panel);
    const pollStatus      = qs('[data-tmp-poll-status]', panel);
    const declareWinnersBtn = qs('[data-tmp-declare-winners-btn]', panel);

    let currentMeetingId = null;
    let pollTimer        = null;
    let pollIsOpen       = false;

    // Populate meeting dropdown from existing meetings data
    api('/meetings').then(meetings => {
      if (!meetings || !meetings.length) return;
      const today = new Date().toISOString().slice(0, 10);
      meetings.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.meeting_date + (m.theme ? ' — ' + m.theme : '');
        if (m.meeting_date === today) opt.selected = true;
        meetingSelect.appendChild(opt);
      });
      if (meetingSelect.value) onMeetingChange(meetingSelect.value);
    }).catch(() => {});

    // Populate member options for TT speaker select; Guest option first, then members
    api('/members').then(members => {
      if (!members) return;
      const guestOpt = document.createElement('option');
      guestOpt.value = '__guest__';
      guestOpt.textContent = '✎ Guest speaker (enter name)…';
      ttMemberSelect.appendChild(guestOpt);
      members.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.full_name;
        ttMemberSelect.appendChild(opt);
      });
    }).catch(() => {});

    // Show/hide guest name input based on selection
    ttMemberSelect.addEventListener('change', () => {
      const isGuest = ttMemberSelect.value === '__guest__';
      ttGuestWrap.style.display = isGuest ? 'block' : 'none';
      if (isGuest) ttNameInput.focus();
      else ttNameInput.value = '';
    });

    meetingSelect.addEventListener('change', () => onMeetingChange(meetingSelect.value));

    function onMeetingChange(mid) {
      currentMeetingId = mid ? parseInt(mid) : null;
      clearInterval(pollTimer);
      if (!currentMeetingId) {
        ttEntry.style.display = 'none';
        nomineesBlock.style.display = 'none';
        return;
      }
      ttEntry.style.display = 'block';
      nomineesBlock.style.display = 'block';
      loadNominees();
      pollTimer = setInterval(loadNominees, 30000);
    }

    function loadNominees() {
      if (!currentMeetingId) return;
      api('/voting/nominees/' + currentMeetingId).then(data => {
        renderTTSpeakers(data.nominees.table_topics || []);
        renderNomineesSummary(data.nominees);
        setPollOpenUI(!!data.poll_open);
      }).catch(() => {});
    }

    function setPollOpenUI(isOpen) {
      pollIsOpen = isOpen;
      if (openPollBtn) {
        openPollBtn.textContent = isOpen ? 'Close Poll' : 'Open Poll';
        openPollBtn.style.background = isOpen ? '#a33' : '';
      }
      if (pollStatus) {
        pollStatus.textContent = isOpen ? '🟢 Poll is OPEN — members can vote' : '⚪ Poll is closed';
      }
    }

    function renderTTSpeakers(speakers) {
      if (!speakers.length) {
        ttSpeakerList.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.88rem;">No TT speakers added yet.</p>';
        return;
      }
      ttSpeakerList.innerHTML = '<ol class="tmp-tt-speaker-list">' +
        speakers.map(s => `
          <li class="tmp-tt-speaker-row">
            <span>${esc(s.display_name)}</span>
            <button class="tmp-link-button tmp-tt-remove" data-id="${s.id}" style="color:var(--tmp-burgundy);font-size:0.8rem;"
              ${s.vote_count > 0 ? 'disabled title="Has votes — cannot remove"' : ''}>remove</button>
          </li>`).join('') + '</ol>';

      panel.querySelectorAll('.tmp-tt-remove:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => {
          api('/voting/tt-speaker/' + btn.dataset.id, { method: 'DELETE' })
            .then(loadNominees).catch(err => alert('Remove failed: ' + err.message));
        });
      });
    }

    function renderNomineesSummary(nominees) {
      const cats = { main_role: 'Main Role', aux_role: 'Auxiliary Role', table_topics: 'Table Topics', speaker: 'Best Speaker', evaluator: 'Best Evaluator' };
      nomineesSummary.innerHTML = Object.entries(cats).map(([cat, label]) => {
        const items = nominees[cat] || [];
        return `<div class="tmp-vote-cat-summary">
          <strong>${label}</strong>
          <span>${items.map(n => esc(n.display_name) + ' (' + esc(n.role_name) + ')').join(', ') || '<em>—</em>'}</span>
        </div>`;
      }).join('');
    }

    // Add TT speaker
    ttAddBtn.addEventListener('click', () => {
      if (!currentMeetingId) return;
      const sel = ttMemberSelect.value;
      let name, memberId;
      if (sel === '__guest__') {
        name     = ttNameInput.value.trim();
        memberId = null;
        if (!name) { ttNameInput.focus(); return; }
      } else if (sel) {
        name     = ttMemberSelect.options[ttMemberSelect.selectedIndex].textContent;
        memberId = sel;
      } else {
        ttMemberSelect.focus();
        return;
      }

      ttAddBtn.disabled = true;
      api('/voting/tt-speaker', {
        method: 'POST',
        body: JSON.stringify({ meeting_id: currentMeetingId, display_name: name, member_id: memberId }),
      }).then(data => {
        ttNameInput.value         = '';
        ttMemberSelect.value      = '';
        ttGuestWrap.style.display = 'none';
        if (data.nominees) {
          renderTTSpeakers(data.nominees.table_topics || []);
          renderNomineesSummary(data.nominees);
        } else {
          loadNominees();
        }
      }).catch(err => alert('Failed: ' + err.message))
        .finally(() => { ttAddBtn.disabled = false; });
    });

    // Refresh nominees from assignments
    refreshBtn.addEventListener('click', () => {
      if (!currentMeetingId) return;
      refreshBtn.disabled = true;
      refreshBtn.textContent = 'Refreshing…';
      api('/voting/refresh-nominees/' + currentMeetingId, { method: 'POST' })
        .then(data => {
          renderTTSpeakers(data.nominees.table_topics || []);
          renderNomineesSummary(data.nominees);
        })
        .catch(err => alert('Refresh failed: ' + err.message))
        .finally(() => { refreshBtn.disabled = false; refreshBtn.textContent = '↻ Refresh from Assignments'; });
    });

    // Open / close poll
    if (openPollBtn) {
      openPollBtn.addEventListener('click', () => {
        if (!currentMeetingId) return;
        const newState = !pollIsOpen;
        openPollBtn.disabled = true;
        api('/voting/open-poll/' + currentMeetingId, {
          method: 'POST',
          body: JSON.stringify({ open: newState }),
        }).then(data => {
          setPollOpenUI(data.poll_open);
        }).catch(err => alert('Poll update failed: ' + err.message))
          .finally(() => { openPollBtn.disabled = false; });
      });
    }

    // Declare winners
    if (declareWinnersBtn) {
      declareWinnersBtn.addEventListener('click', () => {
        if (!currentMeetingId) return;
        if (!confirm('Declare winners now? This will mark the top vote-getter(s) in each category. You can re-run it any time.')) return;
        declareWinnersBtn.disabled = true;
        declareWinnersBtn.textContent = 'Calculating…';
        api('/voting/declare-winners/' + currentMeetingId, { method: 'POST' })
          .then(data => {
            renderResults(data.results);
            resultsBlock.style.display = 'block';
            resultsBtn.textContent = 'Hide Results';
          }).catch(err => alert('Declare winners failed: ' + err.message))
          .finally(() => { declareWinnersBtn.disabled = false; declareWinnersBtn.textContent = '🏆 Declare Winners'; });
      });
    }

    // Results toggle
    resultsBtn.addEventListener('click', () => {
      if (!currentMeetingId) return;
      const showing = resultsBlock.style.display !== 'none';
      if (showing) {
        resultsBlock.style.display = 'none';
        resultsBtn.textContent = 'Show Live Results';
        return;
      }
      resultsBtn.textContent = 'Loading…';
      api('/voting/results/' + currentMeetingId).then(data => {
        renderResults(data);
        resultsBlock.style.display = 'block';
        resultsBtn.textContent = 'Hide Results';
      }).catch(err => {
        resultsBlock.innerHTML = '<p style="color:var(--tmp-burgundy);font-size:0.85rem;">Could not load results: ' + (err.message || 'unknown error') + '</p>';
        resultsBlock.style.display = 'block';
        resultsBtn.textContent = 'Show Live Results';
      });
    });

    function renderResults(data) {
      const cats  = {
        main_role:    'Best Main Role',
        aux_role:     'Best Auxiliary Role',
        table_topics: 'Best Table Topics Speaker',
        speaker:      'Best Speaker',
        evaluator:    'Best Evaluator',
      };
      const results = data.results || data; // support both declare-winners and get-results shapes
      const total   = data.total_voters ?? '';
      const totalLine = total !== '' ? `<p style="color:var(--tmp-muted);font-size:0.85rem;">${total} voter${total !== 1 ? 's' : ''} so far</p>` : '';
      resultsBlock.innerHTML = totalLine +
        Object.entries(cats).map(([cat, label]) => {
          const items = (results[cat] || []);
          if (!items.length) return '';
          const maxVotes = Math.max(...items.map(n => n.vote_count));
          return `<div class="tmp-results-cat">
            <p class="tmp-eyebrow">${label}</p>
            ${items.map(n => {
              const isWinner = n.is_winner || (maxVotes > 0 && n.vote_count === maxVotes);
              return `<div class="tmp-result-row ${isWinner ? 'tmp-result-row--winner' : ''}">
                <span>${isWinner ? '🏆 ' : ''}${esc(n.display_name)} <small style="color:var(--tmp-muted)">${esc(n.role_name)}</small></span>
                <span class="tmp-result-votes">${n.vote_count} vote${n.vote_count !== 1 ? 's' : ''}</span>
              </div>`;
            }).join('')}
          </div>`;
        }).join('');
    }
  }

  // ── VPE Meeting Wrap-Up Panel ────────────────────────────────────────────────

  function initWrapUpPanel() {
    const panel = qs('[data-tmp-wrapup-panel]');
    if (!panel) return;

    const meetingSelect    = qs('[data-tmp-wrapup-meeting-select]', panel);
    const wrapupContent    = qs('[data-tmp-wrapup-content]', panel);
    const wrapupBadge      = qs('[data-tmp-wrapup-badge]', panel);
    const performersList   = qs('[data-tmp-role-performers-list]', panel);
    const markAllPresentBtn= qs('[data-tmp-mark-all-present]', panel);
    const walkinSearch     = qs('[data-tmp-walkin-search]', panel);
    const walkinDropdown   = qs('[data-tmp-walkin-dropdown]', panel);
    const walkinList       = qs('[data-tmp-walkin-list]', panel);
    const guestNameInput   = qs('[data-tmp-guest-name]', panel);
    const addGuestBtn      = qs('[data-tmp-add-guest-btn]', panel);
    const guestsList       = qs('[data-tmp-guests-list]', panel);
    const winnersList      = qs('[data-tmp-winners-list]', panel);
    const completeBtn      = qs('[data-tmp-complete-meeting-btn]', panel);
    const saveStatus       = qs('[data-tmp-wrapup-save-status]', panel);

    let currentMeetingId = null;
    let otherMembers     = []; // for walk-in search

    const CAT_LABELS = {
      main_role: 'Best Main Role', aux_role: 'Best Auxiliary Role',
      table_topics: 'Best Table Topics', speaker: 'Best Speaker', evaluator: 'Best Evaluator',
    };

    // Populate meeting select with recent meetings (most recent first)
    api('/meetings').then(meetings => {
      if (!meetings || !meetings.length) return;
      const now = new Date();
      const today = now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0');
      meetings.slice(0, 8).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.meeting_date + (m.theme ? ' — ' + m.theme : '');
        if (m.meeting_date === today) opt.selected = true;
        meetingSelect.appendChild(opt);
      });
      if (meetingSelect.value) loadWrapUp(parseInt(meetingSelect.value, 10));
    }).catch(() => {});

    meetingSelect.addEventListener('change', () => {
      const mid = parseInt(meetingSelect.value, 10);
      if (mid) loadWrapUp(mid);
      else { wrapupContent.style.display = 'none'; wrapupBadge.style.display = 'none'; }
    });

    function loadWrapUp(meetingId) {
      currentMeetingId = meetingId;
      api('/meetings/' + meetingId + '/wrap-up').then(renderWrapUp).catch(err => {
        wrapupContent.style.display = 'none';
        saveStatus.textContent = 'Failed to load: ' + err.message;
        saveStatus.style.color = 'var(--tmp-burgundy)';
      });
    }

    function setPerformerAbsent(row, absent) {
      const roleCheck = row.querySelector('[data-role-cb]');
      if (absent) {
        row.classList.add('tmp-performer-absent');
        row.dataset.absent = '1';
        if (roleCheck) { roleCheck.checked = false; roleCheck.closest('.tmp-role-cb-wrap').style.visibility = 'hidden'; }
        row.querySelector('[data-absent-btn]').textContent = '↩ Restore';
      } else {
        row.classList.remove('tmp-performer-absent');
        row.dataset.absent = '0';
        if (roleCheck) { roleCheck.checked = true; roleCheck.closest('.tmp-role-cb-wrap').style.visibility = ''; }
        row.querySelector('[data-absent-btn]').textContent = 'Mark Absent';
      }
    }

    function renderWrapUp(data) {
      wrapupContent.style.display = 'block';
      const done = data.wrapped_up;
      wrapupBadge.style.display = done ? '' : 'none';
      completeBtn.textContent = done ? '↻ Update Records' : '✓ Complete Meeting';
      saveStatus.textContent = '';

      // ── Role Performers (present by default, tap to mark absent) ─────────
      otherMembers = data.other_members || [];
      performersList.innerHTML = '';
      const performers = data.role_performers || [];
      if (performers.length) {
        performers.forEach(m => {
          const isAbsent   = done ? !m.attended : false;
          const roleChecked = done ? m.role_performed : true;
          const row = document.createElement('div');
          row.className = 'tmp-wrapup-performer-row' + (isAbsent ? ' tmp-performer-absent' : '');
          row.dataset.memberId     = m.member_id;
          row.dataset.assignmentId = m.assignment_id;
          row.dataset.absent       = isAbsent ? '1' : '0';
          row.innerHTML = `
            <span class="tmp-wrapup-member-name">${esc(m.full_name)}</span>
            <span class="tmp-wrapup-role-tag">${esc(m.role_name)}</span>
            <span class="tmp-role-cb-wrap"${isAbsent ? ' style="visibility:hidden"' : ''}>
              <label class="tmp-role-check-label">
                <input type="checkbox" data-role-cb${roleChecked && !isAbsent ? ' checked' : ''} />
                Role ✓
              </label>
            </span>
            <button type="button" class="tmp-absent-btn" data-absent-btn>${isAbsent ? '↩ Restore' : 'Mark Absent'}</button>`;
          row.querySelector('[data-absent-btn]').addEventListener('click', () => {
            setPerformerAbsent(row, row.dataset.absent !== '1');
          });
          performersList.appendChild(row);
        });
      } else {
        performersList.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.85rem;">No role assignments found for this meeting.</p>';
      }

      // ── Walk-in members (already attended, picked by search) ─────────────
      walkinList.innerHTML = '';
      (data.walk_ins || []).forEach(m => addWalkinChip(m.member_id, m.full_name));
      refreshWalkinSearch();

      // ── Guests ─────────────────────────────────────────────────────────────
      guestsList.innerHTML = '';
      (data.guests || []).forEach(g => appendGuestRow(g.guest_name));

      // ── Winners ─────────────────────────────────────────────────────────────
      winnersList.innerHTML = '';
      const nominees  = data.vote_winners || [];
      const savedWins = data.existing_wins || [];

      if (nominees.length) {
        // Pre-check is_winner=1 nominees; if none declared yet, pre-check top vote-getter per category.
        const declaredAny = nominees.some(n => n.is_winner);
        const topPerCat   = {};
        if (!declaredAny) {
          nominees.forEach(n => {
            if (!(n.category in topPerCat) || n.vote_count > topPerCat[n.category]) {
              topPerCat[n.category] = n.vote_count;
            }
          });
        }
        nominees.forEach(n => {
          const preChecked = declaredAny
            ? n.is_winner
            : (n.vote_count > 0 && n.vote_count === topPerCat[n.category]);
          const voteLabel = n.vote_count ? ` (${n.vote_count} vote${n.vote_count !== 1 ? 's' : ''})` : '';
          const row = document.createElement('div');
          row.className = 'tmp-wrapup-winner-row';
          row.dataset.category    = n.category;
          row.dataset.memberId    = n.member_id || '';
          row.dataset.roleName    = n.role_name;
          row.dataset.voteCount   = n.vote_count || 0;
          row.dataset.displayName = n.display_name || '';
          row.innerHTML = `
            <label class="tmp-wrapup-attend-label">
              <input type="checkbox" data-winner-check class="tmp-wrapup-cb"${preChecked ? ' checked' : ''} />
              <span>
                <span class="tmp-wrapup-cat-label">${esc(CAT_LABELS[n.category] || n.category)}</span>
                <span class="tmp-wrapup-role-tag">${esc(n.display_name || '')} — ${esc(n.role_name)}${voteLabel}</span>
              </span>
            </label>`;
          winnersList.appendChild(row);
        });
      } else if (savedWins.length) {
        savedWins.forEach(w => {
          const row = document.createElement('div');
          row.className = 'tmp-wrapup-winner-row';
          row.dataset.category    = w.category;
          row.dataset.memberId    = w.member_id || '';
          row.dataset.roleName    = w.role_name;
          row.dataset.voteCount   = w.vote_count || 0;
          row.dataset.displayName = w.display_name || '';
          row.innerHTML = `
            <label class="tmp-wrapup-attend-label">
              <input type="checkbox" data-winner-check class="tmp-wrapup-cb" checked />
              <span>
                <span class="tmp-wrapup-cat-label">${esc(CAT_LABELS[w.category] || w.category)}</span>
                <span class="tmp-wrapup-role-tag">${esc(w.display_name || '')} — ${esc(w.role_name)}</span>
              </span>
            </label>`;
          winnersList.appendChild(row);
        });
      } else {
        winnersList.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.85rem;">No voting conducted for this meeting.</p>';
      }
    }

    // ── Walk-in member search ─────────────────────────────────────────────────
    function walkinMemberIds() {
      return Array.from(walkinList.querySelectorAll('[data-walkin-id]')).map(el => parseInt(el.dataset.walkinId, 10));
    }

    function addWalkinChip(memberId, fullName) {
      if (walkinList.querySelector('[data-walkin-id="' + memberId + '"]')) return;
      const chip = document.createElement('span');
      chip.className = 'tmp-walkin-chip';
      chip.dataset.walkinId = memberId;
      chip.innerHTML = `${esc(fullName)} <button type="button" aria-label="Remove">✕</button>`;
      chip.querySelector('button').addEventListener('click', () => {
        chip.remove();
        refreshWalkinSearch();
      });
      walkinList.appendChild(chip);
    }

    function refreshWalkinSearch() {
      const added = walkinMemberIds();
      walkinSearch._filtered = otherMembers.filter(m => !added.includes(m.member_id));
      walkinDropdown.style.display = 'none';
    }

    if (walkinSearch) {
      walkinSearch.addEventListener('input', () => {
        const q = walkinSearch.value.trim().toLowerCase();
        const added = walkinMemberIds();
        const matches = otherMembers.filter(m => !added.includes(m.member_id) && m.full_name.toLowerCase().includes(q));
        if (!q || !matches.length) { walkinDropdown.style.display = 'none'; return; }
        walkinDropdown.innerHTML = matches.slice(0, 8).map(m =>
          `<div class="tmp-walkin-option" data-mid="${m.member_id}">${esc(m.full_name)}</div>`
        ).join('');
        walkinDropdown.style.display = 'block';
        walkinDropdown.querySelectorAll('.tmp-walkin-option').forEach(opt => {
          opt.addEventListener('mousedown', e => {
            e.preventDefault();
            addWalkinChip(parseInt(opt.dataset.mid, 10), opt.textContent.trim());
            walkinSearch.value = '';
            walkinDropdown.style.display = 'none';
          });
        });
      });
      walkinSearch.addEventListener('blur', () => setTimeout(() => { walkinDropdown.style.display = 'none'; }, 150));
    }

    if (markAllPresentBtn) {
      markAllPresentBtn.addEventListener('click', () => {
        performersList.querySelectorAll('.tmp-wrapup-performer-row').forEach(row => setPerformerAbsent(row, false));
      });
    }

    // ── Guests ────────────────────────────────────────────────────────────────
    function appendGuestRow(name) {
      const row = document.createElement('div');
      row.className = 'tmp-wrapup-guest-row';
      row.dataset.guestName = name;
      row.innerHTML = `<span>👤 ${esc(name)}</span>
        <button class="tmp-link-button tmp-wrapup-remove-guest" style="color:var(--tmp-burgundy);" aria-label="Remove">✕</button>`;
      row.querySelector('.tmp-wrapup-remove-guest').addEventListener('click', () => row.remove());
      guestsList.appendChild(row);
    }

    addGuestBtn.addEventListener('click', () => {
      const name = guestNameInput.value.trim();
      if (!name) { guestNameInput.focus(); return; }
      appendGuestRow(name);
      guestNameInput.value = '';
      guestNameInput.focus();
    });
    guestNameInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addGuestBtn.click(); } });

    // ── Save ─────────────────────────────────────────────────────────────────
    completeBtn.addEventListener('click', async () => {
      if (!currentMeetingId) return;

      // Role performers: absent ones excluded, present ones get role_performed state
      const attendance = [];
      performersList.querySelectorAll('.tmp-wrapup-performer-row').forEach(row => {
        if (row.dataset.absent === '1') return;
        const aid = parseInt(row.dataset.assignmentId, 10) || 0;
        const rolePerformed = row.querySelector('[data-role-cb]')?.checked ?? true;
        attendance.push({ member_id: parseInt(row.dataset.memberId, 10), assignment_id: aid, role_performed: aid > 0 && rolePerformed });
      });
      // Walk-ins: attended only
      walkinList.querySelectorAll('[data-walkin-id]').forEach(chip => {
        attendance.push({ member_id: parseInt(chip.dataset.walkinId, 10), assignment_id: 0, role_performed: false });
      });

      const guests = [];
      guestsList.querySelectorAll('.tmp-wrapup-guest-row').forEach(row => {
        if (row.dataset.guestName) guests.push({ name: row.dataset.guestName });
      });

      const winners = [];
      winnersList.querySelectorAll('.tmp-wrapup-winner-row[data-category]').forEach(row => {
        const cb = row.querySelector('[data-winner-check]');
        if (cb && cb.checked) winners.push({
          category: row.dataset.category, member_id: row.dataset.memberId ? parseInt(row.dataset.memberId, 10) : null,
          display_name: row.dataset.displayName, role_name: row.dataset.roleName,
          vote_count: parseInt(row.dataset.voteCount, 10) || 0, is_tie: 0,
        });
      });

      completeBtn.disabled = true;
      saveStatus.textContent = 'Saving…';
      saveStatus.style.color = 'var(--tmp-muted)';
      try {
        await api('/meetings/' + currentMeetingId + '/wrap-up', { method: 'POST', body: JSON.stringify({ attendance, guests, winners }) });
        saveStatus.textContent = '✓ Saved! Pulse card updates within a minute.';
        saveStatus.style.color = 'var(--tmp-teal)';
        loadWrapUp(currentMeetingId);
      } catch (err) {
        saveStatus.textContent = 'Save failed: ' + err.message;
        saveStatus.style.color = 'var(--tmp-burgundy)';
      } finally {
        completeBtn.disabled = false;
      }
    });
  }

  // ── SAA Attendance (member dashboard) ────────────────────────────────────────

  function initSAAAttendance() {
    const panel = qs('[data-tmp-saa-panel]');
    if (!panel) return;

    const searchInput  = qs('[data-tmp-saa-search]', panel);
    const dropdown     = qs('[data-tmp-saa-dropdown]', panel);
    const attendedList = qs('[data-tmp-saa-attended-list]', panel);
    const guestName    = qs('[data-tmp-saa-guest-name]', panel);
    const addGuestBtn  = qs('[data-tmp-saa-add-guest]', panel);
    const guestsList   = qs('[data-tmp-saa-guests-list]', panel);
    const saveBtn      = qs('[data-tmp-saa-save]', panel);
    const statusEl     = qs('[data-tmp-saa-status]', panel);
    const meetingLabel = qs('[data-tmp-saa-meeting-label]', panel);

    let meetingId   = null;
    let allMembers  = [];

    api('/me/saa-meeting').then(data => {
      meetingId = data.meeting_id;
      allMembers = data.members || [];
      meetingLabel.textContent = data.meeting_date + (data.theme ? ' — ' + data.theme : '');
      panel.style.display = '';

      // Pre-fill attended members as chips
      allMembers.filter(m => m.attended).forEach(m => addChip(m.member_id, m.full_name));

      // Pre-fill guests
      (data.guests || []).forEach(g => addGuestChip(g.guest_name));
    }).catch(() => {}); // Not SAA — panel stays hidden

    function attendedIds() {
      return Array.from(attendedList.querySelectorAll('[data-saa-mid]')).map(el => parseInt(el.dataset.saaMid, 10));
    }

    function addChip(memberId, fullName) {
      if (attendedList.querySelector('[data-saa-mid="' + memberId + '"]')) return;
      const chip = document.createElement('span');
      chip.className = 'tmp-walkin-chip';
      chip.dataset.saaMid = memberId;
      chip.innerHTML = `${esc(fullName)} <button type="button" aria-label="Remove">✕</button>`;
      chip.querySelector('button').addEventListener('click', () => chip.remove());
      attendedList.appendChild(chip);
    }

    function addGuestChip(name) {
      const row = document.createElement('div');
      row.className = 'tmp-wrapup-guest-row';
      row.dataset.guestName = name;
      row.innerHTML = `<span>👤 ${esc(name)}</span>
        <button class="tmp-link-button" style="color:var(--tmp-burgundy);" aria-label="Remove">✕</button>`;
      row.querySelector('button').addEventListener('click', () => row.remove());
      guestsList.appendChild(row);
    }

    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      const added = attendedIds();
      const matches = allMembers.filter(m => !added.includes(m.member_id) && m.full_name.toLowerCase().includes(q));
      if (!q || !matches.length) { dropdown.style.display = 'none'; return; }
      dropdown.innerHTML = matches.slice(0, 8).map(m =>
        `<div class="tmp-walkin-option" data-mid="${m.member_id}">${esc(m.full_name)}</div>`
      ).join('');
      dropdown.style.display = 'block';
      dropdown.querySelectorAll('.tmp-walkin-option').forEach(opt => {
        opt.addEventListener('mousedown', e => {
          e.preventDefault();
          addChip(parseInt(opt.dataset.mid, 10), opt.textContent.trim());
          searchInput.value = '';
          dropdown.style.display = 'none';
        });
      });
    });
    searchInput.addEventListener('blur', () => setTimeout(() => { dropdown.style.display = 'none'; }, 150));

    addGuestBtn.addEventListener('click', () => {
      const name = guestName.value.trim();
      if (!name) { guestName.focus(); return; }
      addGuestChip(name);
      guestName.value = '';
      guestName.focus();
    });
    guestName.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addGuestBtn.click(); } });

    saveBtn.addEventListener('click', async () => {
      if (!meetingId) return;
      const attended_member_ids = attendedIds();
      const guests = Array.from(guestsList.querySelectorAll('.tmp-wrapup-guest-row'))
        .map(r => ({ name: r.dataset.guestName })).filter(g => g.name);
      saveBtn.disabled = true;
      statusEl.textContent = 'Saving…';
      statusEl.style.color = 'var(--tmp-muted)';
      try {
        await api('/meetings/' + meetingId + '/saa-attendance', { method: 'POST', body: JSON.stringify({ attended_member_ids, guests }) });
        statusEl.textContent = '✓ Saved! VPE will see this when closing the meeting.';
        statusEl.style.color = 'var(--tmp-teal)';
      } catch (err) {
        statusEl.textContent = 'Save failed: ' + err.message;
        statusEl.style.color = 'var(--tmp-burgundy)';
      } finally {
        saveBtn.disabled = false;
      }
    });
  }

  initMemberDashboard();
  initSAAAttendance();
  initAdmin();
  initVPEducation();
  initEnrolment();
  initVotingPanel();
  initWrapUpPanel();
})();
