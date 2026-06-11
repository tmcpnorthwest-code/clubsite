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

      if (member.milestones) {
        qsa("[data-m]", root).forEach((el) => {
          const key = el.dataset.m;
          if (member.milestones[key]) { el.classList.add("tmp-done"); el.title = `Completed: ${member.milestones[key]}`; }
        });
      }

      // ── Mentor card — only relevant for Level 1 members ──────────────────
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

      // ── Active requests ────────────────────────────────────────────────────
      const requests = await api("/me/requests").catch(() => []);
      root._memberRequests = requests;
      const arEl = qs("[data-tmp-active-requests]", root); if (arEl) arEl.innerHTML = requests.length
        ? `<div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>${requests.map((r) => {
              const approved = String(r.assigned_id) === String(member.id) && r.assignment_status === "Confirmed";
              const denied   = r.assigned_id && String(r.assigned_id) !== String(member.id) && r.assignment_status === "Confirmed";
              const style    = approved ? "background:#2e7d32;color:#fff;" : denied ? "background:#c62828;color:#fff;" : "background:#eee;color:#333;";
              const label    = approved ? "Approved" : denied ? "Denied" : "Pending";
              return `<tr><td data-label="Meeting">${esc(r.meeting_date)} - ${esc(r.theme)}</td><td data-label="Role">${esc(r.role_name)}</td>
                <td data-label="Priority"><span class="tmp-tag" style="background:#f5f5f5">P${esc(r.priority)}</span></td>
                <td data-label="Status"><span class="tmp-tag" style="${style}">${label}</span></td>
                <td data-label="Action"><button class="tmp-small-button tmp-danger" data-cancel-request="${esc(r.id)}">Cancel</button></td></tr>`;
            }).join("")}</tbody></table></div>`
        : "<p>You have no active role requests.</p>";

      // Badge: pending (not yet confirmed) request count
      const pendingCount = requests.filter((r) => {
        const approved = String(r.assigned_id) === String(member.id) && r.assignment_status === "Confirmed";
        const denied   = r.assigned_id && String(r.assigned_id) !== String(member.id) && r.assignment_status === "Confirmed";
        return !approved && !denied;
      }).length;
      const badgeEl = qs("[data-tmp-meeting-badge]", root);
      if (badgeEl) { badgeEl.textContent = pendingCount; badgeEl.style.display = pendingCount > 0 ? "inline-flex" : "none"; }

      // ── Pending Requests (after VPE approval) ───────────────────────────────
      const pendingData = await api("/me/pending-requests").catch(() => ({ requests: [] }));
      const prEl = qs("[data-tmp-pending-requests]", root);
      if (prEl) {
        prEl.innerHTML = pendingData.requests.length
          ? `<div class="tmp-table-wrap"><table class="tmp-table">
              <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th><th>Reason</th></tr></thead>
              <tbody>${pendingData.requests.map((r) => {
                const statusColor = r.status === 'Approved' ? '#2e7d32' : r.status === 'NotSelected' ? '#c62828' : '#ef6c00';
                const statusIcon = r.status === 'Approved' ? '✓' : r.status === 'NotSelected' ? '✗' : '⏳';
                const reasonHtml = r.reason ? `<details style="cursor:pointer;"><summary style="color:#666;">Show reason</summary><p style="margin:8px 0;color:#555;font-size:12px;">${esc(r.reason)}</p></details>` : '';
                return `<tr style="background:${r.isExpired ? '#f5f5f5' : '#fff'}">
                  <td data-label="Meeting">${esc(r.meetingDate)} - ${esc(r.meetingTheme)}${r.isExpired ? '<br><small style="color:var(--tmp-muted)">Expired</small>' : ''}</td>
                  <td data-label="Role">${esc(r.roleName)}</td>
                  <td data-label="Priority"><span class="tmp-tag" style="background:#f5f5f5">P${esc(r.priority)}</span></td>
                  <td data-label="Status"><span class="tmp-tag" style="background:${statusColor};color:#fff;font-weight:bold;">${statusIcon} ${r.status}</span></td>
                  <td data-label="Reason">${reasonHtml}</td>
                </tr>`;
              }).join("")}</tbody></table></div>`
          : "<p>No pending request results yet. Requests will appear here after the VPE approval deadline.</p>";
      }

      // ── Request history ────────────────────────────────────────────────────
      const history = await api("/me/requests/history").catch(() => []);
      const rhEl = qs("[data-tmp-request-history]", root); if (rhEl) rhEl.innerHTML = history.length
        ? `<div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>${history.map((r) => {
              const approved = String(r.assigned_id) === String(member.id) && r.assignment_status === "Confirmed";
              const denied   = r.assigned_id && String(r.assigned_id) !== String(member.id) && r.assignment_status === "Confirmed";
              const style    = approved ? "background:#2e7d32;color:#fff;" : denied ? "background:#c62828;color:#fff;" : "background:#eee;color:#333;";
              return `<tr><td data-label="Meeting">${esc(r.meeting_date)} - ${esc(r.theme)}</td><td data-label="Role">${esc(r.role_name)}</td>
                <td data-label="Priority"><span class="tmp-tag" style="background:#f5f5f5">P${esc(r.priority)}</span></td>
                <td data-label="Status"><span class="tmp-tag" style="${style}">${approved ? "Approved" : denied ? "Denied" : "Unprocessed"}</span></td></tr>`;
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
      const hasExistingRequest = (root._memberRequests || []).some((r) => String(r.meeting_id) === mSelect.value);
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

      const opts = '<option value="">(None)</option>' +
        unique.map((r) => {
          let label = r.display;
          if (!r.qualified) label += ` (${r.requirement})`;
          else if (r.isGoal) label += " ⭐ Goal";
          return `<option value="${esc(r.assignment_id)}" ${!r.qualified ? "disabled" : ""}>${esc(label)}</option>`;
        }).join("");

      const rSelects = qsa("[data-tmp-req-role-select]", reqForm);
      rSelects.forEach((sel) => { sel.innerHTML = opts; });

      // Store original disabled state for each option (based on qualification)
      const optionStates = new Map(); // Maps element to Map of optionValue -> originalDisabledState
      rSelects.forEach((sel) => {
        const states = new Map();
        Array.from(sel.options).forEach((opt) => {
          states.set(opt.value, opt.disabled);
        });
        optionStates.set(sel, states);
      });

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
            // Get original disabled state (from qualification checks)
            const originalDisabled = optionStates.get(currentSel)?.get(opt.value) || false;
            // Disable if: originally disabled OR already selected in another priority
            const isDuplicate = opt.value && opt.value !== currentSel.value && selectedInOthers.has(opt.value);
            opt.disabled = originalDisabled || isDuplicate;
          });
        });
      };

      rSelects.forEach((sel) => {
        sel.addEventListener("change", updateDropdownAvailability);
        // Prevent selection of disabled options
        sel.addEventListener("change", () => {
          const selectedOption = Array.from(sel.options).find((opt) => opt.value === sel.value);
          if (selectedOption?.disabled) {
            sel.value = ""; // Reset to "(None)"
            alert("This role is not available for you. Check the requirements below.");
          }
        });
      });
      updateDropdownAvailability(); // Initial check

      // Info box: locked roles (deduplicated, base name only)
      const locked  = unique.filter((r) => !r.qualified);
      const infoBox = qs("[data-tmp-role-info]", reqForm);
      if (infoBox) {
        infoBox.innerHTML = locked.map((r) => {
          const isCooloff = r.cooloff && r.cooloff.in_cooloff;
          const msg       = isCooloff
            ? `<strong>${esc(r.display)}</strong> is in cooloff — eligible from <strong>${esc(r.cooloff.eligible_from)}</strong>`
            : `<strong>${esc(r.display)}</strong> requires ${esc(r.requirement)}`;
          return `<div style="margin-top:5px;font-size:11px;color:var(--tmp-muted)">${msg}</div>`;
        }).join("");
      }
    });

    // Submit role requests
    reqForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const rSelect = qs("[data-tmp-req-role-select]", reqForm);
      if (!rSelect.value || !root._member) return;

      // Check if member already has a request for this meeting
      const meetingId = reqForm.elements.meeting_id.value;
      const hasExistingRequest = (root._memberRequests || []).some((r) => String(r.meeting_id) === meetingId);
      if (hasExistingRequest) {
        alert("You already have a request for this meeting. Please cancel it first if you want to request a different role.");
        return;
      }

      // Validate that no disabled (greyed out) options were selected
      const rSelects = qsa("[data-tmp-req-role-select]", reqForm);
      for (const sel of rSelects) {
        if (sel.value) {
          const selectedOption = Array.from(sel.options).find((opt) => opt.value === sel.value);
          if (selectedOption?.disabled) {
            alert("You cannot select a role you are not qualified for. Please review the requirements shown in red.");
            return;
          }
        }
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
                <th>Mentor</th>
                <th>Actions</th>
              </tr></thead>
              <tbody>${sortedEligible.map((m) => {
                const inactive = m.recent_participation_count === 0 && m.total_recent_meetings_checked > 0;
                return `<tr ${inactive ? 'style="background:#fff8e1"' : ""}>
                  <td data-label="Name"><strong>${esc(m.full_name)}</strong>${inactive ? `<br><small style="color:#ef6c00;font-weight:bold">No roles in last ${m.total_recent_meetings_checked} meetings</small>` : ""}</td>
                  <td data-label="Pathway"><small>${esc(m.pathway)}</small></td>
                  <td data-label="Level"><span class="tmp-tag" style="background:#e8eaf6;color:#303f9f;font-size:0.78rem;">${levelLabel(m.level)}</span></td>
                  <td data-label="Phone"><small>${esc(m.phone || "—")}</small></td>
                  <td data-label="Email"><small>${esc(m.email)}</small></td>
                  <td data-label="Recent">${m.recent_participation_count} / ${m.total_recent_meetings_checked}</td>
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

    // -- Due for roles --------------------------------------------------------
    async function renderDueForRoles() {
      const due    = await api("/members/due-for-roles").catch(() => []);
      const sect   = qs("[data-tmp-due-roles-section]", root);
      const cntEl  = qs("[data-tmp-due-roles-count]", root);
      const listEl = qs("[data-tmp-due-roles-list]", root);
      if (!sect || !listEl) return;

      if (cntEl) cntEl.textContent = due.length ? `${due.length} members` : "";
      listEl.innerHTML = due.length
        ? `<div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Member</th><th>Level</th><th>Last Role</th><th>Days Since</th></tr></thead>
            <tbody>${due.map((m) => `
              <tr>
                <td data-label="Member"><strong>${esc(m.full_name)}</strong><br><small>${esc(m.pathway)}</small></td>
                <td data-label="Level">Level ${esc(m.level)}</td>
                <td data-label="Last Role">${m.last_role_date ? esc(m.last_role_date) : "<em>Never</em>"}</td>
                <td data-label="Days Since"><span class="tmp-tag" style="background:${Number(m.days_since_role) > 28 ? "#b71c1c" : "#ef6c00"};color:#fff;">${esc(m.days_since_role)} days</span></td>
              </tr>`).join("")}
            </tbody></table></div>`
        : "<p style=\"color:var(--tmp-muted)\">All eligible members have participated within the cooloff window.</p>";
    }

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

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting) => {
        const [h, min] = (meeting.start_time || "18:30:00").split(":").map(Number);
        let t = h * 60 + (min || 0);

        const agendaHtml = (meeting.assignments || []).map((a) => {
          const start = formatTime(t);
          const dur   = Number(a.duration || 0);
          t += dur;
          const end   = formatTime(t);
          const hasOverride = a.cooloff_override == 1;
          return `<li>
            <span>
              <strong>${esc(a.role_name)}</strong> / ${a.member_name ? esc(a.member_name) : (a.first_requester ? `<em>Req by ${esc(a.first_requester)}</em>` : "Unassigned")} / ${esc(a.status)} / <small class="tmp-time-tag">${start} / ${dur}m / ${end}</small>
              ${a.status === "Requested" ? ' <span class="tmp-tag" style="background:#ffd700;padding:2px 4px;border-radius:3px;font-size:10px;">PRIORITY</span>' : ""}
              ${a.request_count > 1 ? ` <span class="tmp-tag" style="background:#ff5722;color:#fff;padding:2px 4px;border-radius:3px;font-size:10px;">CONFLICT (${a.request_count})</span>` : ""}
              ${hasOverride ? ` <span class="tmp-tag" style="background:#ff9800;color:#fff;padding:2px 4px;border-radius:3px;font-size:10px;" title="${esc(a.override_reason || "")}">COOLOFF OVERRIDE</span>` : ""}
              ${a.suitability ? `<span class="tmp-tag" style="background:${a.suitability.suitable ? "#e1f5fe" : "#ffebee"};color:${a.suitability.suitable ? "#01579b" : "#b71c1c"};padding:2px 4px;border-radius:3px;font-size:10px;margin-left:5px;">${esc(a.suitability.reason)}</span>` : ""}
              ${a.speech_title ? `<br><small>Title: ${esc(a.speech_title)}</small>` : ""}
            </span>
            <span>
              ${a.request_count > 0 ? `<button class="tmp-small-button" style="background:#01579b;color:#fff;font-weight:bold;" type="button" data-view-conflicts="${esc(a.id)}">Review (${a.request_count})</button>` : ""}
              <button class="tmp-small-button tmp-danger" type="button" data-delete-assignment="${esc(a.id)}">Delete</button>
            </span>
          </li>`;
        }).join("");

        const totalUsed = t - (h * 60 + (min || 0));
        const limit     = Number(meeting.total_duration || 120);
        const warning   = totalUsed > limit
          ? `<p class="tmp-tag" style="background:#b71c1c;color:#fff;display:block;margin:10px 0;text-align:center;padding:5px;border-radius:4px;">Warning: Agenda (${totalUsed}m) exceeds limit (${limit}m)</p>`
          : "";

        return `<article class="tmp-agenda-card">
          <div class="tmp-card-head">
            <h4>${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</h4>
            <div>
              <span class="tmp-tag">${esc((meeting.start_time || "18:30").substring(0, 5))}</span>
              ${meeting.requests_close_at ? `<span class="tmp-tag" style="background:#607d8b">Closes: ${esc(meeting.requests_close_at.substring(0, 16))}</span>` : ""}
            </div>
          </div>
          <p>${esc(meeting.venue || "Venue not set")}</p>
          <p>${esc(meeting.agenda_notes || "")}</p>
          ${warning}
          <ul class="tmp-assignment-list">${agendaHtml || "<li><span>No roles scheduled yet.</span></li>"}</ul>
          <div style="display:flex;gap:10px;margin-top:15px;flex-wrap:wrap;">
            <button class="tmp-button tmp-secondary tmp-small" data-suggest-roles="${meeting.id}">Get Intelligent Suggestions</button>
            <button class="tmp-button tmp-secondary tmp-small" data-print-agenda="${meeting.id}">Print Agenda</button>
            <button class="tmp-button tmp-danger tmp-small" data-delete-meeting="${meeting.id}">Delete Meeting</button>
          </div>
          <div data-tmp-suggestion-panel="${meeting.id}" style="display:none;margin-top:14px;"></div>
        </article>`;
      }).join("")}</div>`;
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

    meetingSelect.addEventListener("change", updateRoles);

    roleSelect.addEventListener("change", () => {
      const val = roleSelect.value;
      const mid = meetingSelect.value;

      if (!val) {
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = mid;
        assignmentForm.elements.role_name.value  = "";
        toggleFieldsByRole("");
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
      const btn = ev.target.querySelector("button[type=submit]");
      if (btn) btn.disabled = true;
      try {
        const newM = await api("/meetings", { method: "POST", body: JSON.stringify(formData(meetingForm)) });
        alert("Meeting created successfully with role templates!");
        clearForm(meetingForm);
        await renderMeetings(newM.id);
      } catch (err) {
        alert("Failed to save meeting: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    // Assignment form submit
    assignmentForm.addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const btn = ev.target.querySelector("button[type=submit]");
      if (btn) btn.disabled = true;
      try {
        const d = formData(assignmentForm);
        await api("/assignments", { method: "POST", body: JSON.stringify(d) });
        alert("Assignment saved.");
        clearForm(assignmentForm);
        toggleFieldsByRole("");
        if (cooloffWarning) cooloffWarning.style.display = "none";
        if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
        await renderMeetings();
      } catch (err) {
        alert("Failed to save assignment: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    qs("[data-tmp-clear-meeting]",    root)?.addEventListener("click", () => clearForm(meetingForm));
    qs("[data-tmp-clear-assignment]", root)?.addEventListener("click", () => {
      clearForm(assignmentForm);
      toggleFieldsByRole("");
      if (cooloffWarning) cooloffWarning.style.display = "none";
      if (cooloffOverrideWrap) cooloffOverrideWrap.style.display = "none";
    });

    // Meeting list event delegation
    meetingList.addEventListener("click", async (e) => {
      const del          = e.target.closest("[data-delete-assignment]");
      const suggest      = e.target.closest("[data-suggest-roles]");
      const print        = e.target.closest("[data-print-agenda]");
      const viewConflicts= e.target.closest("[data-view-conflicts]");
      const delMeeting   = e.target.closest("[data-delete-meeting]");
      const approveReq   = e.target.closest("[data-vpe-approve-req]");

      if (del) {
        if (confirm("Remove this role from the agenda?")) {
          e.target.closest("li")?.remove();
          await api(`/assignments/${del.dataset.deleteAssignment}`, { method: "DELETE" });
          await renderMeetings();
          updateMemberDashboard().catch(() => {});
        }
      } else if (delMeeting) {
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
      } else if (suggest) {
        const meetingId = suggest.dataset.suggestRoles;
        const panel = qs(`[data-tmp-suggestion-panel="${meetingId}"]`, meetingList);
        if (panel) { panel.style.display = "block"; panel.innerHTML = '<p style="color:var(--tmp-muted);margin:0;">Calculating suggestions…</p>'; }
        let data, suggs, trace;
        try {
          data  = await api(`/meetings/${meetingId}/suggestions`);
          suggs = data.suggestions || [];
          trace = data.trace || [];
        } catch (err) {
          if (panel) panel.innerHTML = `<p style="color:var(--tmp-burgundy);margin:0;">Error: ${esc(err.message)}</p>`;
          return;
        }
        if (!suggs.length) {
          if (panel) panel.innerHTML = `<div style="background:#f5f5f5;border-radius:6px;padding:12px 14px;">
            <strong>No suggestions available.</strong>
            <details style="margin-top:8px;"><summary style="cursor:pointer;font-size:12px;color:var(--tmp-muted)">Show trace</summary>
            <pre style="font-size:11px;margin:6px 0 0;white-space:pre-wrap;color:var(--tmp-muted)">${esc(trace.join("\n"))}</pre></details>
          </div>`;
          return;
        }
        if (panel) panel.innerHTML = `
          <div style="background:#f0f7ff;border:1px solid #bbdefb;border-radius:6px;padding:14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
              <strong>Suggested Assignments (${suggs.length})</strong>
              <button class="tmp-small-button tmp-primary" data-apply-all="${meetingId}">Apply All</button>
            </div>
            <table class="tmp-table" style="min-width:0;font-size:0.88rem;">
              <thead><tr><th>Role</th><th>Member</th><th>Reason</th><th></th></tr></thead>
              <tbody>${suggs.map((s) => `<tr>
                <td>${esc(s.role_name)}</td>
                <td><strong>${esc(s.suggested_member_name)}</strong></td>
                <td style="color:var(--tmp-muted)"><small>${esc(s.progression_note || "")}</small></td>
                <td><button class="tmp-small-button" data-apply-one="${esc(s.id)}" data-apply-member="${esc(s.suggested_member_id)}">Apply</button></td>
              </tr>`).join("")}</tbody>
            </table>
          </div>`;
        // Store suggestions for Apply All
        if (panel) panel._suggestions = suggs;

      } else if (e.target.closest("[data-apply-one]")) {
        const btn = e.target.closest("[data-apply-one]");
        btn.disabled = true;
        await api("/assignments", { method: "POST", body: JSON.stringify({ id: btn.dataset.applyOne, member_id: btn.dataset.applyMember, status: "Confirmed" }) });
        btn.closest("tr").style.background = "#e8f5e9";
        btn.textContent = "✓";
        await renderMeetings();

      } else if (e.target.closest("[data-apply-all]")) {
        const btn     = e.target.closest("[data-apply-all]");
        const panel   = btn.closest("[data-tmp-suggestion-panel]");
        const suggs   = panel?._suggestions || [];
        btn.disabled  = true;
        btn.textContent = "Applying…";
        for (const s of suggs) {
          await api("/assignments", { method: "POST", body: JSON.stringify({ id: s.id, member_id: s.suggested_member_id, status: "Confirmed" }) });
        }
        await renderMeetings();
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
      await Promise.all([
        renderMembers(true).catch((err) => console.error("Members load failed:", err)),
        renderDueForRoles().catch((err) => console.error("Due-for-roles failed:", err)),
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

      for (const role of meeting.roles) {
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

          html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #eee;">
            <div style="flex:1;">
              <div style="font-size:13px;">
                <strong>${esc(req.memberName)}</strong> (L${req.memberLevel}, ${esc(req.pathway)})
                <span class="tmp-tag" style="background:#f5f5f5;margin:0 4px;">P${req.priority}</span>
                ${recommendedBadge}
              </div>
              <div style="font-size:11px;margin-top:3px;color:#666;">
                <span style="font-weight:bold;color:${scoreColor};">Score: ${req.score}</span>
                ${reasonsHtml}
              </div>
            </div>
          </div>`;
        }

        html += `</div>`;
      }

      html += `</div>`;
    }
    html += `</div>`;

    list.innerHTML = html;
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

  initMemberDashboard();
  initAdmin();
  initVPEducation();
  initEnrolment();
})();
