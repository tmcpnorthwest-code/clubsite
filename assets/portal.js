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

  // Returns YYYY-MM-DD in local time — avoids UTC-offset off-by-one from toISOString()
  const localDateStr = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;

  function roleSort(roleName) {
    const n = (roleName || "").replace(/\s*\(.*?\)\s*/g, "").trim().toLowerCase();
    const sm = n.match(/^speaker\s*(\d+)$/);
    const em = n.match(/^evaluator\s*(\d+)$/);
    if (sm) return 100 + (parseInt(sm[1], 10) - 1) * 2;
    if (em) return 101 + (parseInt(em[1], 10) - 1) * 2;
    if (n.includes("table topics master"))    return 90;
    if (n.includes("table topics evaluator")) return 92;
    if (n.includes("table topics"))           return 91;
    if (n.includes("presiding officer"))    return 0;
    if (n === "saa" || n.includes("sergeant at arms")) return 1;
    if (n.includes("toastmaster"))          return 2;
    if (n.includes("timer"))                return 3;
    if (n.includes("ah counter"))           return 4;
    if (n.includes("grammarian"))           return 5;
    if (n.includes("active listener"))      return 6;
    if (n.includes("general evaluator"))    return 7;
    return 50;
  }

  function fmtSecs(s) {
    if (!s && s !== 0) return '';
    s = Number(s);
    const m   = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}:${String(sec).padStart(2, '0')}`;
  }

  function parseMSS(str) {
    if (!str || !String(str).trim()) return null;
    const t = String(str).trim();
    if (t.includes(':')) {
      const [m, s] = t.split(':').map(Number);
      return (m * 60) + (s || 0);
    }
    return Math.round(Number(t) * 60); // bare number treated as minutes
  }

  function formatTime(totalMinutes) {
    const h = Math.floor(totalMinutes / 60) % 24;
    const m = totalMinutes % 60;
    return `${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}`;
  }

  function generatePrintView(meeting) {
    const w = window.open("", "_blank");
    if (!w) { alert("Please allow pop-ups for this site to print the agenda."); return; }

    const [startH, startMin] = (meeting.start_time || "18:30:00").split(":").map(Number);
    const startTotal = startH * 60 + startMin;
    const totalDur   = Number(meeting.total_duration || 120);
    const endTotal   = startTotal + totalDur;
    const timeRange  = `${formatTime(startTotal)} – ${formatTime(endTotal)}`;

    const tmodAsgn = (meeting.assignments || []).find((a) =>
      /toastmaster of the day|tmod/i.test(a.role_name) && a.member_name
    );
    const tmodName = tmodAsgn ? tmodAsgn.member_name : "";

    const meetDate = new Date(meeting.meeting_date + "T00:00:00");
    const dateStr  = meetDate.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });

    const venue       = meeting.venue || TMPortal.clubVenue || "";
    const clubName    = TMPortal.clubName || "Toastmasters Club";
    const clubMission = TMPortal.clubMission || "";

    const ordinalSuffix = (n) => {
      const s = ["th", "st", "nd", "rd"], v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
    };
    const chapterNum = meeting.chapter_number ? parseInt(meeting.chapter_number, 10) : 0;

    const logoSrc  = TMPortal.tmLogoUrl || TMPortal.logoUrl || "";
    const logoHtml = logoSrc
      ? `<img src="${logoSrc}" alt="" style="width:88px;height:88px;object-fit:contain;display:block;">`
      : `<div style="width:88px;height:88px;background:#8f1737;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:24px;">TM</div>`;

    // Format seconds as M or M:SS
    const fmtT = (secs) => {
      const n = Number(secs);
      if (!n) return "";
      const mm = Math.floor(n / 60);
      const ss = n % 60;
      return ss === 0 ? String(mm) : `${mm}:${String(ss).padStart(2, "0")}`;
    };

    const sectionRow = (label) => `<tr class="pv-section"><td colspan="6">${label}</td></tr>`;
    const assignments = meeting.assignments || [];

    // ── Pass 1: pre-scan section header positions ─────────────────────────────
    const isReportRole = (rn) => {
      const rL = rn.toLowerCase();
      return (rL.includes("timer") || rL.includes("ah-counter") ||
              rL.includes("grammarian") || rL.includes("active listener") ||
              rL.includes("general evaluator")) && rL.includes("report");
    };
    const sec = {};
    for (let i = 0; i < assignments.length; i++) {
      const rn     = assignments[i].role_name;
      // Strip the trailing "(note)" so combined TMOD intro lines like
      // "Toastmaster of the Day (Introduces Evaluator 1 and Speaker 1)"
      // can't be mistaken for the actual Speaker/Evaluator slot row.
      const rBase  = rn.replace(/\s*\(.*?\)\s*$/, "").trim();
      const rLow   = rn.toLowerCase();
      if (sec.speeches    === undefined && /^speaker\s+\d+$/i.test(rBase))          sec.speeches    = i;
      if (sec.tabletopics === undefined && rLow.includes("table topics master"))     sec.tabletopics = i;
      if (sec.evaluation  === undefined && /^evaluator\s+\d+$/i.test(rBase))        sec.evaluation  = i;
      if (sec.conclusion  === undefined && /presiding/i.test(rLow) &&
          (rLow.includes("closing") || rLow.includes("guest feedback")))             sec.conclusion  = i;
    }
    const lastMajorIdx = Math.max(sec.speeches ?? -1, sec.tabletopics ?? -1, sec.evaluation ?? -1);
    for (let i = 0; i < assignments.length; i++) {
      if (i <= lastMajorIdx) continue;
      if (isReportRole(assignments[i].role_name)) { sec.reports = i; break; }
    }
    const headerAt = new Map();
    if (sec.speeches    !== undefined) headerAt.set(sec.speeches,    "Prepared Speeches");
    if (sec.tabletopics !== undefined) headerAt.set(sec.tabletopics, "Table Topics Session");
    if (sec.evaluation  !== undefined) headerAt.set(sec.evaluation,  "Evaluation Session");
    if (sec.reports     !== undefined) headerAt.set(sec.reports,     "Role-player’s Report");
    if (sec.conclusion  !== undefined) headerAt.set(sec.conclusion,  "Conclusion");

    // ── Pass 2: render in exact sort_order ────────────────────────────────────
    let t = startTotal;
    let bodyRows = `<tr class="pv-gather"><td>${formatTime(startTotal - 20)}</td><td></td><td></td><td></td><td>Gathering &amp; Networking</td><td>All Participants</td></tr>`;

    for (let i = 0; i < assignments.length; i++) {
      const a       = assignments[i];
      const start   = formatTime(t);
      const dur     = Number(a.duration || 0);
      t += dur;
      const rLow    = a.role_name.toLowerCase();
      const isBreak = rLow.startsWith("break");

      if (headerAt.has(i)) bodyRows += sectionRow(headerAt.get(i));

      if (isBreak) {
        bodyRows += `<tr class="pv-break"><td>${start}</td><td></td><td></td><td></td><td colspan="2" style="text-align:center;font-weight:bold;letter-spacing:0.05em;">— Break —</td></tr>`;
        continue;
      }
      const tg  = fmtT(a.time_green);
      const ty  = fmtT(a.time_yellow);
      const tr_ = fmtT(a.time_red);
      bodyRows += `<tr>
        <td class="pv-time">${start}</td>
        <td class="pv-g">${tg}</td>
        <td class="pv-y">${ty}</td>
        <td class="pv-r">${tr_}</td>
        <td>${esc(a.role_name)}${a.speech_title ? `<br><span class="pv-subtitle">${esc(a.speech_title)}</span>` : ""}</td>
        <td class="pv-presenter">${esc(a.member_name || "")}</td>
      </tr>`;
    }

    const notesHtml = meeting.agenda_notes
      ? `<p style="margin-top:20px;font-size:11px;color:#666;font-style:italic;white-space:pre-wrap;">${esc(meeting.agenda_notes)}</p>`
      : "";

    w.document.write(`<!DOCTYPE html><html><head>
      <meta charset="UTF-8">
      <title>Meeting Agenda – ${esc(meeting.meeting_date)}</title>
      <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12px; color: #111; background: #fff; padding: 32px 36px; }

        /* ── Header ── */
        .pv-header { margin-bottom: 14px; }
        .pv-header-top { display: flex; align-items: center; gap: 20px; padding-bottom: 14px; border-bottom: 1px solid #ddd; }
        .pv-header-logo { flex-shrink: 0; }
        .pv-club-name  { font-size: 16px; font-weight: 700; color: #8f1737; line-height: 1.3; margin-bottom: 4px; }
        .pv-meta-line  { font-size: 11.5px; color: #444; margin-top: 4px; }
        .pv-header-title { text-align: center; padding: 14px 0 10px; border-bottom: 2px solid #8f1737; }
        .pv-agenda-title { font-size: 15px; font-weight: 600; color: #555; letter-spacing: 0.08em; text-transform: uppercase; }
        .pv-chapter-num  { font-size: 26px; font-weight: 900; color: #18324a; letter-spacing: 0.01em; margin-top: 2px; line-height: 1.1; }
        .pv-mission { font-size: 10.5px; color: #444; font-style: italic; border: 1px solid #e0e0e0; border-radius: 3px; padding: 6px 14px; margin: 10px 0 14px; text-align: center; line-height: 1.6; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        thead tr { background: #18324a; color: #fff; }
        thead th { padding: 9px 10px; font-size: 11px; font-weight: 600; text-align: center; letter-spacing: 0.04em; text-transform: uppercase; }
        thead th:nth-child(5), thead th:nth-child(6) { text-align: left; }
        tbody tr { border-bottom: 1px solid #e8e8e8; }
        tbody tr:nth-child(even):not(.pv-section):not(.pv-break):not(.pv-gather) { background: #f9f9f9; }
        td { padding: 9px 10px; vertical-align: middle; }
        .pv-time { white-space: nowrap; font-weight: 600; font-size: 11.5px; color: #333; }
        .pv-g { color: #2e7d32; font-weight: 700; text-align: center; }
        .pv-y { color: #b8860b; font-weight: 700; text-align: center; }
        .pv-r { color: #c62828; font-weight: 700; text-align: center; }
        .pv-presenter { font-style: italic; color: #333; }
        .pv-subtitle  { font-size: 10px; color: #777; display: block; margin-top: 2px; }
        .pv-section td { background: #18324a; color: #fff; font-weight: 700; text-align: center; padding: 6px 10px; letter-spacing: 0.07em; text-transform: uppercase; font-size: 10.5px; }
        .pv-break td  { background: #f0f0f0; color: #666; font-style: italic; text-align: center; }
        .pv-gather td { color: #888; font-style: italic; font-size: 11px; }

        @media print {
          body { padding: 14px 18px; }
          @page { margin: 12mm; }
        }
      </style>
    </head><body>
      <div class="pv-header">
        <div class="pv-header-top">
          <div class="pv-header-logo">${logoHtml}</div>
          <div>
            <div class="pv-club-name">${esc(clubName)}</div>
            <div class="pv-meta-line">${esc(dateStr)}</div>
            <div class="pv-meta-line">${esc(timeRange)}${venue ? " &nbsp;·&nbsp; " + esc(venue) : ""}</div>
            <div class="pv-meta-line">Theme: <strong>${esc(meeting.theme || "")}</strong>${tmodName ? " &nbsp;·&nbsp; TMOD: <strong>" + esc(tmodName) + "</strong>" : ""}</div>
          </div>
        </div>
        <div class="pv-header-title">
          <div class="pv-agenda-title">Meeting Agenda</div>
          ${chapterNum ? `<div class="pv-chapter-num">${ordinalSuffix(chapterNum)} Chapter Meeting</div>` : ""}
        </div>
      </div>
      ${clubMission ? `<div class="pv-mission">${esc(clubMission)}</div>` : ""}
      <table>
        <thead><tr>
          <th>Time</th><th style="color:#90ee90;">Green</th><th style="color:#ffff99;">Yellow</th>
          <th style="color:#ff9999;">Red</th><th style="text-align:left;">Activity</th><th style="text-align:left;">Presenter</th>
        </tr></thead>
        <tbody>${bodyRows}</tbody>
      </table>
      ${notesHtml}
      <script>window.print();window.onafterprint=()=>window.close();<\/script>
    </body></html>`);
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
    if (!res.ok) { const e = new Error(data.message || 'Request failed'); e.code = data.code; throw e; }
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
      const levelCompleted = Number(member.level_completed || 0);
      const pct    = Math.max(0, Math.min(100, levelCompleted * 20));

      const setField = (sel, val) => { const el = qs(sel, root); if (el) el.textContent = val; };
      setField("[data-tmp-member-name]",    member.full_name);
      setField("[data-tmp-member-summary]", `${member.pathway} - Level ${levelCompleted}`);
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
        const cls = n <= levelCompleted ? "tmp-done" : n === levelCompleted + 1 ? "tmp-active" : "";
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
      const visibleMilestones = milestonesByLevel[levelCompleted] || milestonesByLevel[5];

      qsa("[data-m]", root).forEach((el) => {
        const key = el.dataset.m;
        const isVisible = visibleMilestones.includes(key);
        el.style.display = isVisible ? "" : "none";
        if (isVisible) {
          if (member.milestones && member.milestones[key]) {
            el.classList.add("tmp-done");
            el.title = `Completed: ${member.milestones[key]}`;
          } else if (key === 'level1_completed' && levelCompleted >= 1) {
            // Mark level1_completed as done if level_completed >= 1
            el.classList.add("tmp-done");
            el.title = "Level 1 Completed";
          }
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
      // Members working on Level 2+ have usually completed mentorship and may not need it
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

      // ── Level status (speech + role progress for L1–L3) ───────────────────────
      const renderProgressSummary = async () => {
        const summaryPanel = qs("[data-tmp-progress-summary-panel]", root);
        const nextActionPanel = qs("[data-tmp-next-action-panel]", root);
        const summaryEl = qs("[data-tmp-progress-summary]", root);
        const progressLvlEl = qs("[data-tmp-progress-level]", root);
        if (!summaryEl) return;

        const data = await api("/me/level-status").catch(() => null);
        if (!data) return;

        const levelCompleted = data.level_completed || 0;
        const workingLevel = data.level || Math.min(levelCompleted + 1, 5);

        if (workingLevel > 3) {
          // L4+ members: hide progress panel, show next action
          if (summaryPanel) summaryPanel.style.display = "none";
          if (nextActionPanel) nextActionPanel.style.display = "";
          return;
        }

        // L1-L3 members: show progress panel, hide next action
        if (summaryPanel) summaryPanel.style.display = "";
        if (nextActionPanel) nextActionPanel.style.display = "none";

        if (progressLvlEl) progressLvlEl.textContent = workingLevel;

        const sp = data.speech_progress;
        const gaps = data.role_gaps || [];
        const ready = data.ready_to_advance;

        // Speeches line
        const spLine = sp
          ? `Speeches at Level ${workingLevel}: ${'▓'.repeat(sp.done)}${'░'.repeat(sp.needed - sp.done)} ${sp.done} / ${sp.needed} done${sp.offset ? ` (+${sp.offset} pre-system)` : ''}<br>${sp.needed - sp.done} more ${sp.needed - sp.done === 1 ? 'speech' : 'speeches'} needed`
          : '';

        // Roles line
        const unmetRoles = gaps.filter((g) => !g.met);
        const rolesLine = unmetRoles.length > 0
          ? `Club Roles at Level ${workingLevel}: ${gaps.length - unmetRoles.length} / ${gaps.length} done<br>Need: ${unmetRoles.map((g) => g.label).join(', ')}`
          : `Club Roles at Level ${workingLevel}: ✓ All ${gaps.length} done`;

        const statusColor = ready ? '#2e7d32' : '#ef6c00';
        const statusIcon = ready ? '🟢' : '🟡';
        const statusText = ready ? 'Ready to unlock Level Up' : 'Complete all requirements to unlock Level Up';

        summaryEl.innerHTML = `
          <div style="font-size:0.9rem;line-height:1.6;margin-bottom:12px;">
            ${spLine}<br><br>
            ${rolesLine}<br><br>
            <span style="color:${statusColor};font-weight:600;">${statusIcon} ${statusText}</span>
          </div>`;
      };

      const renderLevelStatus = async () => {
        const statusEl  = qs("[data-tmp-level-status]", root);
        const nextLvlEl = qs("[data-tmp-next-level]", root);
        const lvlUpSect = qs("[data-tmp-levelup-section]", root);
        const lvlUpStat = qs("[data-tmp-levelup-request-status]", root);
        const lvlUpBtn  = qs("[data-tmp-request-levelup]", root);
        if (!statusEl) return;

        const data = await api("/me/level-status").catch(() => null);
        if (!data) { statusEl.innerHTML = '<p style="color:var(--tmp-muted)">Could not load progress.</p>'; return; }

        root._levelStatus = data;
        const lvl = data.level;
        if (nextLvlEl) nextLvlEl.textContent = lvl + 1;

        if (lvl > 3) {
          statusEl.innerHTML = `<p style="color:var(--tmp-muted);font-size:0.88rem;">Level ${lvl} progress is coordinated with your VPE. Contact your VPE for guidance on L4+ requirements.</p>`;
          if (lvlUpSect) lvlUpSect.style.display = "none";
          return;
        }

        const sp       = data.speech_progress;
        const gaps     = data.role_gaps || [];
        const ready    = data.ready_to_advance;
        const verdict  = data.verdict_detail || [];

        // Speech bar
        let speechHtml = "";
        if (sp) {
          const pct   = Math.round((sp.done / sp.needed) * 100);
          const chips = (sp.speeches || []).map((s) =>
            `<span class="tmp-speech-chip" title="${esc(s.meeting_date)}">${esc(s.role_name)} <small>${esc(s.meeting_date)}</small></span>`
          ).join("");
          const still = sp.needed - sp.done;
          for (let i = 0; i < still; i++) chips && 0; // placeholder below
          const emptyChips = still > 0 ? `<span class="tmp-speech-chip tmp-speech-chip--empty">${still} more needed</span>` : "";
          speechHtml = `
            <div style="margin-bottom:14px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-weight:600;font-size:0.88rem;">Speeches at Level ${lvl}</span>
                <span style="font-size:0.88rem;color:var(--tmp-muted);">${sp.done} / ${sp.needed}</span>
              </div>
              <div class="tmp-progress" style="margin-bottom:8px;"><span style="width:${pct}%;background:${pct>=100?'var(--tmp-teal)':'var(--tmp-burgundy)'};"></span></div>
              <div style="display:flex;flex-wrap:wrap;gap:6px;">${chips}${emptyChips}</div>
              ${sp.offset > 0 ? `<p style="font-size:0.78rem;color:var(--tmp-muted);margin:6px 0 0;">Includes ${sp.offset} pre-system speech${sp.offset>1?'es':''} set by VPE.</p>` : ""}
            </div>`;
        }

        // Role gaps (reuse same rendering as Level Journey panel)
        const rolesHtml = gaps.length === 0 ? "" : `
          <div style="margin-bottom:14px;">
            <p style="font-weight:600;font-size:0.88rem;margin:0 0 6px;">Club Roles at Level ${lvl}</p>
            ${gaps.map((g) => `
              <div style="display:flex;align-items:center;gap:8px;margin:4px 0;font-size:0.88rem;">
                <span style="font-size:1rem;">${g.met ? "✅" : "☐"}</span>
                <span style="${g.met ? "color:var(--tmp-muted);" : ""}">${esc(g.label)}</span>
                ${g.met ? "" : `<span class="tmp-badge" style="background:#fff3e0;color:#e65100;font-size:0.75rem;">needed</span>`}
              </div>`).join("")}
          </div>`;

        // Summary
        const summaryColor = ready ? "var(--tmp-teal)" : "#e65100";
        const summaryIcon  = ready ? "🟢" : "🟡";
        const summaryText  = ready
          ? "All requirements met — ready to request Level Up!"
          : verdict.join(" · ");
        const summaryHtml  = `<p style="font-weight:600;font-size:0.9rem;color:${summaryColor};margin:0;">${summaryIcon} ${esc(summaryText)}</p>`;

        statusEl.innerHTML = speechHtml + rolesHtml + summaryHtml;

        // Level-up section: show only for L1–L2
        if (lvlUpSect && lvl <= 2) {
          lvlUpSect.style.display = "";
          // Check for existing pending request
          const requests = await api("/me/level-up-requests").catch(() => []);
          const pending  = (requests || []).find((r) => r.status === "pending");
          if (pending) {
            if (lvlUpBtn) lvlUpBtn.style.display = "none";
            if (lvlUpStat) lvlUpStat.innerHTML = `<p style="color:var(--tmp-muted);font-size:0.88rem;">⏳ Level Up request submitted on ${esc(pending.created_at?.split(" ")[0] || "—")} — awaiting VPE review.</p>`;
            const denied = (requests || []).find((r) => r.status === "denied");
            if (denied && lvlUpStat) {
              lvlUpStat.innerHTML += `<p style="color:var(--tmp-burgundy);font-size:0.88rem;margin-top:4px;">❌ Previous request was not approved. VPE note: ${esc(denied.vpe_note || "No note provided.")}</p>`;
            }
          } else {
            if (lvlUpBtn) {
              lvlUpBtn.style.display = ready ? "" : "none";
              if (lvlUpStat && !ready) lvlUpStat.innerHTML = `<p style="color:var(--tmp-muted);font-size:0.88rem;">Complete all Level ${lvl} requirements above to unlock the Level Up request.</p>`;
              else if (lvlUpStat) lvlUpStat.innerHTML = "";
            }
          }
        } else if (lvlUpSect) {
          lvlUpSect.style.display = "none";
        }
      };

      // Level-up request submit
      qs("[data-tmp-levelup-section]", root)?.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-tmp-request-levelup]");
        if (!btn || btn._pending) return;
        const note = prompt("Optional note to your VPE (press Cancel to abort):") ?? false;
        if (note === false) return;
        btn._pending = true;
        btn.disabled = true;
        btn.textContent = "Submitting…";
        try {
          await api("/me/level-up-request", { method: "POST", body: JSON.stringify({ note }) });
          await renderLevelStatus();
        } catch (err) {
          alert("Could not submit: " + err.message);
          btn._pending = false;
          btn.disabled = false;
          btn.textContent = "Request Level Up";
        }
      });

      await renderProgressSummary();
      await renderLevelStatus();

      // (mentee panel removed — combined into mentor dashboard table below)

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

      // Determine if any request period is still open
      const nowTs = new Date();
      const meetingGroups = Object.values(byMeeting);
      const hasOpenRequests = meetingGroups.some((m) => !m.deadline || new Date(m.deadline) > nowTs);
      const hasPendingOrApproved = pendingData.requests.some((r) => r.status === "Pending" || r.status === "Approved");

      // Toggle visibility: show Active Requests if period is open, else show Assigned Roles
      const arSection = qs("[data-tmp-active-requests-section]", root);
      const asSection = qs("[data-tmp-assigned-roles-section]", root);
      if (arSection) arSection.style.display = hasOpenRequests ? "" : "none";
      if (asSection) asSection.style.display = hasOpenRequests ? "none" : "";

      // Render Active Requests (when period is open)
      const arEl = qs("[data-tmp-active-requests]", root);
      if (arEl) {
        if (meetingGroups.length === 0) {
          arEl.innerHTML = "<p>You have no pending role requests.</p>";
        } else {
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

      // Render Assigned Roles (when period is closed)
      const asEl = qs("[data-tmp-assigned-roles]", root);
      if (asEl) {
        const approved = pendingData.requests.filter((r) => r.status === "Approved");
        if (approved.length === 0) {
          asEl.innerHTML = "<p>No roles assigned to you.</p>";
        } else {
          const now = Date.now();
          asEl.innerHTML = `<div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Status</th></tr></thead>
            <tbody>${approved.map((r) => {
              const isSpeaker      = r.roleName.toLowerCase().startsWith("speaker");
              const deadlinePassed = r.deadline && new Date(r.deadline).getTime() < now;
              const isConfirmed    = r.assignmentStatus === "Confirmed";
              const showTitleField = isSpeaker && deadlinePassed && isConfirmed;
              const titleField     = showTitleField
                ? `<div style="margin-top:6px;"><input type="text" data-member-speech-title="${esc(r.assignmentId)}" value="${esc(r.speechTitle || '')}" placeholder="Add your speech title…" style="width:100%;padding:5px 8px;border:1px solid var(--tmp-line);border-radius:5px;font-size:0.82rem;" /><span data-member-title-status="${esc(r.assignmentId)}" style="font-size:0.75rem;color:var(--tmp-muted);"></span></div>`
                : "";
              return `<tr>
                <td data-label="Meeting">${esc(r.meetingDate)} — ${esc(r.meetingTheme)}</td>
                <td data-label="Role">${esc(r.roleName)}${titleField}</td>
                <td data-label="Status"><span class="tmp-tag" style="background:#2e7d32;color:#fff;font-weight:bold;">✓ Confirmed</span></td>
              </tr>`;
            }).join("")}
            </tbody>
          </table></div>`;

          // Wire up speech title autosave (debounced on input)
          asEl.querySelectorAll("[data-member-speech-title]").forEach((inp) => {
            inp.addEventListener("input", () => {
              clearTimeout(inp._timer);
              const statusEl = asEl.querySelector(`[data-member-title-status="${inp.dataset.memberSpeechTitle}"]`);
              inp._timer = setTimeout(async () => {
                if (statusEl) statusEl.textContent = "Saving…";
                try {
                  await api("/me/speech-title", { method: "POST", body: JSON.stringify({ assignment_id: parseInt(inp.dataset.memberSpeechTitle), speech_title: inp.value }) });
                  if (statusEl) { statusEl.textContent = "Saved"; setTimeout(() => { if (statusEl) statusEl.textContent = ""; }, 2000); }
                } catch (err) {
                  if (statusEl) statusEl.textContent = "Save failed";
                }
              }, 800);
            });
          });
        }
      }

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

      // ── Available meetings for role requests ───────────────────────────────
      const availMeetings = await api("/meetings/available").catch(() => []);

      const reqForm  = qs("[data-tmp-member-request-form]", root);
      const mSelect  = qs("[data-tmp-req-meeting-select]", reqForm);
      const rSelects = qsa("[data-tmp-req-role-select]", reqForm);

      const reqSection = qs("[data-tmp-request-section]", root);
      if (reqSection) reqSection.style.display = availMeetings.length ? "" : "none";

      root._availMeetings = availMeetings;
      root._groupedSlots  = {}; // populated lazily when member selects a meeting

      if (mSelect) {
        mSelect.innerHTML = '<option value="">Select a meeting...</option>' +
          availMeetings.map((m) =>
            `<option value="${esc(m.id)}">${esc(m.meeting_date + " - " + (m.theme || ""))}</option>`
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
          no_mentor:            { label: "No Mentor",         bg: "#9e9e9e" },
          assigned:             { label: "Mentor Assigned",   bg: "#1565c0" },
          orientation_complete: { label: "Orientation Done",  bg: "#6a1b9a" },
          icebreaker_delivered: { label: "Ice Breaker Done",  bg: "#2e7d32" },
          level1_complete:      { label: "L1 Complete",       bg: "#ef6c00" },
          closed:               { label: "L1 Done",           bg: "#424242" },
        };
        if (menteeList) menteeList.innerHTML = `
          <div class="tmp-table-wrap"><table class="tmp-table">
            <thead><tr><th>Mentee</th><th>Stage</th><th>Gaps</th><th>Participation</th><th>Your Next Action</th></tr></thead>
            <tbody>${mentees.map((m) => {
              const unmetGaps    = (m.level_gaps || []).filter((g) => !g.met);
              const allMet       = unmetGaps.length === 0 && (m.level_gaps || []).length > 0;
              const noPathway    = (m.next_action || "").startsWith("Register for a Pathway");
              const stageMeta    = STAGE_LABELS[m.mentorship_stage] || STAGE_LABELS.no_mentor;
              const rowStyle     = m.is_at_risk ? "background:#fff8e1" : noPathway ? "background:#fce4ec" : "";

              const pathwayLabel = noPathway
                ? `<span class="tmp-tag" style="background:#c62828;color:#fff;display:inline-block;margin-top:4px;">Not Enrolled</span>`
                : `<small style="color:var(--tmp-muted)">${esc(m.pathway)} · L${m.level}</small>`;

              const gapsHtml = noPathway ? "—"
                : allMet
                  ? `<span style="color:#2e7d32;font-size:0.85rem;">✓ All L${m.level} reqs met</span>`
                  : unmetGaps.length === 0
                    ? "—"
                    : unmetGaps.map((g) =>
                        `<span class="tmp-tag" style="background:#fff3e0;color:#e65100;font-size:0.78rem;display:inline-block;margin:2px 2px 2px 0;">⚠ ${esc(g.label)}</span>`
                      ).join("");

              const participationHtml = `${m.recent_participation_count} / ${m.total_recent_meetings_checked}`
                + (m.is_at_risk ? ` <span style="color:red;font-weight:bold;font-size:0.8rem;">AT RISK</span>` : "");

              const mentorActionHtml = noPathway
                ? `<a href="https://www.toastmasters.org/pathways-overview" target="_blank" rel="noopener" style="color:var(--tmp-burgundy);text-decoration:underline;font-size:0.85rem;">Help register on TI →</a>`
                : `<small style="color:var(--tmp-muted)">${esc(m.mentor_next_action || "—")}</small>`;

              return `<tr style="${rowStyle}">
                <td data-label="Mentee"><strong>${esc(m.full_name)}</strong><br>${pathwayLabel}</td>
                <td data-label="Stage"><span class="tmp-tag" style="background:${stageMeta.bg};color:#fff;">${esc(stageMeta.label)}</span></td>
                <td data-label="Gaps">${gapsHtml}</td>
                <td data-label="Participation">${participationHtml}</td>
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

    // Meeting select → fetch open slots on-demand, populate role dropdowns
    mSelect?.addEventListener("change", async () => {
      const meetingId   = mSelect.value;
      const meetingMeta = (root._availMeetings || []).find((m) => String(m.id) === meetingId);

      // Check if request period has expired
      const now = new Date();
      const deadlineExpired = meetingMeta?.requests_close_at && new Date(meetingMeta.requests_close_at) < now;

      // Show deadline info
      const deadlineEl = qs("[data-tmp-deadline-info]", reqForm);
      if (deadlineEl && meetingMeta) {
        const closeTime = meetingMeta.requests_close_at;
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

      // Fetch and cache open slots for this meeting on first select
      if (!root._groupedSlots[meetingId]) {
        const slotsResp = await api(`/meetings/open-slots?meeting_id=${meetingId}`).catch(() => ({ slots: [] }));
        const rawSlots = (slotsResp.slots || [])
          .filter((s) => {
            const lower = s.role_name.toLowerCase();
            if (lower.startsWith("break")) return false;
            if (lower.includes("toastmaster")) return true;
            if (lower.includes("presiding officer")) return false;
            return true;
          })
          .map((s) => {
            let qualified   = !!s.qualified;
            let requirement = s.requirement || "";
            const base = s.role_name.replace(/\s*\(.*?\)\s*/g, "").replace(/\s+\d+$/, "").trim();
            const cooloff = s.cooloff || null;
            if (cooloff && cooloff.in_cooloff) {
              qualified   = false;
              requirement = `Cooloff until ${cooloff.eligible_from}`;
            }
            return { ...s, qualified, requirement, isGoal: !!s.is_goal, cooloff, base };
          });
        root._groupedSlots[meetingId] = {
          id: parseInt(meetingId, 10),
          text: meetingMeta ? `${meetingMeta.meeting_date} - ${meetingMeta.theme || ""}` : meetingId,
          roles: rawSlots,
        };
      }
      const group = root._groupedSlots[meetingId];

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
          return `<option value="${esc(r.base)}">${esc(r.display)}</option>`;
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

      // Info box: speech encouragement + locked roles
      const locked  = unique.filter((r) => !r.qualified);
      const infoBox = qs("[data-tmp-role-info]", reqForm);
      if (infoBox) {
        let infoHtml = "";
        // Speech encouragement for L1–L3 members who still need speeches
        const lvlSt = root._levelStatus;
        if (lvlSt && lvlSt.speech_progress && !lvlSt.speech_progress.met) {
          const hasSpeakerSlot = unique.some((r) => /^speaker/i.test(r.display) || /ice breaker/i.test(r.display));
          if (hasSpeakerSlot) {
            const still = lvlSt.speech_progress.needed - lvlSt.speech_progress.done;
            infoHtml += `<div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:4px;padding:10px 12px;margin-top:8px;font-size:0.88rem;">
              ⭐ <strong>Getting a Speaker slot counts toward your Level ${lvlSt.level} Pathways project requirements.</strong>
              You need ${still} more speech${still > 1 ? "es" : ""} at Level ${lvlSt.level}.
            </div>`;
          }
        }
        if (locked.length > 0) {
          infoHtml += `<div style="background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:10px 12px;margin-top:10px;">
            <p style="margin:0 0 8px;font-weight:bold;color:#666;font-size:12px;text-transform:uppercase;">Unavailable roles</p>
            ${locked.map((r) => {
              const isCooloff = r.cooloff && r.cooloff.in_cooloff;
              const msg = isCooloff
                ? `<strong>${esc(r.display)}</strong> — in cooloff until ${esc(r.cooloff.eligible_from)}`
                : `<strong>${esc(r.display)}</strong> — ${esc(r.requirement)}`;
              return `<div style="margin-top:6px;font-size:11px;color:#666;">${msg}</div>`;
            }).join("")}
          </div>`;
        }
        infoBox.innerHTML = infoHtml;
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

    // Change password collapsible
    const pwToggle = qs("[data-tmp-change-password-toggle]", root);
    const pwBody   = qs("[data-tmp-change-password-body]",   root);
    pwToggle?.addEventListener("click", () => {
      const open = pwToggle.getAttribute("aria-expanded") === "true";
      pwToggle.setAttribute("aria-expanded", String(!open));
      if (pwBody) pwBody.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", pwToggle);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
    });

    // Show/hide password toggles
    qs("[data-tmp-change-password-panel]", root)?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-pw-reveal]");
      if (!btn) return;
      const wrap  = btn.closest(".tmp-pw-field-wrap");
      const input = wrap?.querySelector("input");
      if (!input) return;
      const isText = input.type === "text";
      input.type = isText ? "password" : "text";
      btn.querySelector(".tmp-eye-open").style.display = isText ? "" : "none";
      btn.querySelector(".tmp-eye-shut").style.display = isText ? "none" : "";
    });

    // Password strength meter
    const newPwInput    = qs("[data-tmp-new-password]", root);
    const strengthWrap  = qs("[data-tmp-pw-strength]", root);
    const strengthLabel = qs("[data-pw-strength-label]", root);
    const bars          = strengthWrap ? Array.from(strengthWrap.querySelectorAll("[data-pw-bar]")) : [];
    const levels = [
      { label: "Too short",  color: "#e53935" },
      { label: "Weak",       color: "#fb8c00" },
      { label: "Fair",       color: "#fdd835" },
      { label: "Good",       color: "#43a047" },
      { label: "Strong",     color: "#00897b" },
    ];
    function scorePassword(pw) {
      if (pw.length < 8) return 0;
      let s = 1;
      if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
      if (/\d/.test(pw)) s++;
      if (/[^a-zA-Z0-9]/.test(pw)) s++;
      if (pw.length >= 12) s = Math.min(s + 1, 4);
      return Math.min(s, 4);
    }
    newPwInput?.addEventListener("input", () => {
      const score = scorePassword(newPwInput.value);
      const lvl   = newPwInput.value.length === 0 ? -1 : score;
      bars.forEach((bar, i) => {
        bar.style.background = lvl >= 0 && i <= lvl ? levels[lvl].color : "var(--tmp-line)";
      });
      if (strengthLabel) {
        strengthLabel.textContent = lvl >= 0 ? levels[lvl].label : "";
        strengthLabel.style.color = lvl >= 0 ? levels[lvl].color : "var(--tmp-muted)";
      }
    });

    const cpForm = qs("[data-tmp-change-password-form]", root);
    cpForm?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const form   = e.currentTarget;
      const status = qs("[data-tmp-change-password-status]", root);
      const newPw  = form.elements.new_password.value;
      const confPw = form.elements.confirm_password.value;
      if (newPw !== confPw) {
        if (status) { status.textContent = "Passwords do not match."; status.style.color = "#c62828"; }
        return;
      }
      const btn = form.querySelector("button[type=submit]");
      btn.disabled = true;
      if (status) { status.textContent = "Saving…"; status.style.color = "var(--tmp-muted)"; }
      try {
        await api("/me/change-password", {
          method: "POST",
          body: JSON.stringify({ current_password: form.elements.current_password.value, new_password: newPw }),
        });
        if (status) { status.textContent = "✓ Password updated!"; status.style.color = "#2e7d32"; }
        form.reset();
        bars.forEach((b) => { b.style.background = "var(--tmp-line)"; });
        if (strengthLabel) strengthLabel.textContent = "";
        setTimeout(() => { if (status) status.textContent = ""; }, 3000);
      } catch (err) {
        if (status) { status.textContent = err.message; status.style.color = "#c62828"; }
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
        (levelFilter === "all" || String(m.level_completed) === levelFilter)
      );

      // Sort
      const { col: sortCol, dir: sortDir } = root._adminSort;
      const sorted = [...filtered].sort((a, b) => {
        const mul = sortDir === "asc" ? 1 : -1;
        if (sortCol === "level") {
          const ld = a.level_completed - b.level_completed;
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

      const levelLabel = (lvl) => lvl === 0 ? "Level 0 (New — no levels completed)" : `Level ${lvl}`;

      const memberToRow = (m) => {
        const inactive = m.recent_participation_count === 0 && m.total_recent_meetings_checked > 0;
        return `<tr>
          <td><strong>${esc(m.full_name)}</strong></td>
          <td>${esc(m.customer_id || "")}</td>
          <td>${esc(m.email)}</td>
          <td>${esc(m.pathway)}</td>
          <td>${levelLabel(m.level_completed)}</td>
          <td>${esc(m.state)}</td>
          <td style="${inactive ? "color:#ef6c00;font-weight:bold;" : ""}">${m.recent_participation_count} / ${m.total_recent_meetings_checked}</td>
          <td>${m.is_exempt_from_unpaid_block ? "Yes" : "No"}</td>
          <td><div class="tmp-row-actions">
            <button class="tmp-small-button tmp-secondary" type="button" data-reset-password="${esc(m.id)}">Reset PW</button>
            <button class="tmp-small-button tmp-danger" type="button" data-delete-member="${esc(m.id)}">Delete</button>
          </div></td>
        </tr>
        <tr data-pw-row="${esc(m.id)}" style="display:none;">
          <td colspan="9" style="background:#f9f9f9;padding:10px 16px;border-bottom:1px solid var(--tmp-line);">
            <form data-pw-form="${esc(m.id)}" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <input type="password" placeholder="New password (min 8 chars)" minlength="8" required
                     style="padding:6px 10px;border:1px solid var(--tmp-line);border-radius:4px;font-size:0.88rem;flex:1;min-width:180px;" />
              <button class="tmp-small-button tmp-primary" type="submit">Set Password</button>
              <button class="tmp-small-button" type="button" data-cancel-pw="${esc(m.id)}">Cancel</button>
              <span data-pw-status="${esc(m.id)}" style="font-size:12px;"></span>
            </form>
          </td>
        </tr>`;
      };

      if (groupKey === "none") {
        table.innerHTML = sorted.map(memberToRow).join("");
      } else {
        const groups = sorted.reduce((acc, m) => {
          const key = (groupKey === "level" ? levelLabel(m.level_completed) : m[groupKey]) || "Unassigned";
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
        return;
      }

      const resetBtn = ev.target.closest("[data-reset-password]");
      if (resetBtn) {
        const id  = resetBtn.dataset.resetPassword;
        const row = table.querySelector(`[data-pw-row="${id}"]`);
        if (row) row.style.display = row.style.display === "none" ? "" : "none";
        return;
      }

      const cancelBtn = ev.target.closest("[data-cancel-pw]");
      if (cancelBtn) {
        const row = table.querySelector(`[data-pw-row="${cancelBtn.dataset.cancelPw}"]`);
        if (row) row.style.display = "none";
      }
    });

    table.addEventListener("submit", async (ev) => {
      const form = ev.target.closest("[data-pw-form]");
      if (!form) return;
      ev.preventDefault();
      const id     = form.dataset.pwForm;
      const pw     = form.querySelector("input[type=password]").value;
      const status = table.querySelector(`[data-pw-status="${id}"]`);
      const btn    = form.querySelector("button[type=submit]");
      btn.disabled = true;
      if (status) { status.textContent = "Saving…"; status.style.color = ""; }
      try {
        await api(`/members/${id}/reset-password`, { method: "POST", body: JSON.stringify({ new_password: pw }) });
        if (status) { status.textContent = "Password set!"; status.style.color = "#2e7d32"; }
        form.reset();
        setTimeout(() => {
          if (status) status.textContent = "";
          const row = table.querySelector(`[data-pw-row="${id}"]`);
          if (row) row.style.display = "none";
        }, 2000);
      } catch (err) {
        if (status) { status.textContent = err.message; status.style.color = "#c62828"; }
      } finally {
        btn.disabled = false;
      }
    });

    await render(true);

    // ── New Member Spotlight ────────────────────────────────────────────────
    const spotlightForm   = qs("[data-tmp-spotlight-form]", root);
    const spotlightStatus = qs("[data-tmp-spotlight-status]", root);
    if (spotlightForm) {
      const mSelect  = qs("[data-tmp-spotlight-member]", spotlightForm);
      const blurbEl  = qs("[data-tmp-spotlight-blurb]",  spotlightForm);
      const photoEl  = qs("[data-tmp-spotlight-photo]",  spotlightForm);
      const activeEl = qs("[data-tmp-spotlight-active]", spotlightForm);

      (root._members || [])
        .slice().sort((a, b) => a.full_name.localeCompare(b.full_name))
        .forEach(m => {
          const opt = document.createElement("option");
          opt.value       = m.id;
          opt.textContent = `${m.full_name} (${m.pathway}, L${m.level})`;
          mSelect.appendChild(opt);
        });

      const saved = await api("/settings/new-member-spotlight").catch(() => null);
      if (saved) {
        mSelect.value    = String(saved.member_id || "");
        blurbEl.value    = saved.blurb     || "";
        photoEl.value    = saved.photo_url || "";
        activeEl.checked = !!saved.active;
      }

      spotlightForm.addEventListener("submit", async ev => {
        ev.preventDefault();
        spotlightStatus.textContent = "Saving…";
        try {
          await api("/settings/new-member-spotlight", {
            method: "POST",
            body: JSON.stringify({
              member_id: Number(mSelect.value),
              blurb:     blurbEl.value.trim(),
              photo_url: photoEl.value.trim(),
              active:    activeEl.checked,
            }),
          });
          spotlightStatus.textContent = "Saved!";
        } catch (e) {
          spotlightStatus.textContent = "Save failed: " + e.message;
        }
      });
    }
  }

  // ===========================================================================
  // VPE DASHBOARD
  // ===========================================================================

  async function initVPEducation() {
    const root = qs("[data-tmp-vpe]");
    if (!root) return;

    const meetingForm    = qs("[data-tmp-meeting-form]", root);
    if (meetingForm?.elements.venue) {
      meetingForm.elements.venue.defaultValue = TMPortal.clubVenue || "";
    }
    const assignmentForm = qs("[data-tmp-assignment-form]", root);
    const meetingSelect  = qs("[data-tmp-meeting-select]", root);
    const roleSelect     = qs("[data-tmp-role-select]", root);
    const memberSelect   = qs("[data-tmp-member-select]", root);
    const meetingList    = qs("[data-tmp-meeting-list]", root);
    const compactList    = qs("[data-tmp-meetings-compact-list]", root);
    const meetingCount   = qs("[data-tmp-meeting-count]", root);
    const vpeSearch      = qs("[data-tmp-vpe-search]", root);
    const vpePathway     = qs("[data-tmp-vpe-pathway]", root);
    const vpeLevel       = qs("[data-tmp-vpe-level]", root);
    const vpeMentorFilt  = qs("[data-tmp-vpe-mentor-filter]", root);
    const statusFilter   = qs("[data-tmp-vpe-lp-status]", root);
    const unifiedRows    = qs("[data-tmp-unified-rows]", root);
    const overviewCount  = qs("[data-tmp-vpe-member-count]", root);
    const readyCountEl   = qs("[data-tmp-vpe-ready-count]", root);
    const cooloffWarning = qs("[data-tmp-cooloff-warning]", assignmentForm);
    const cooloffOverrideWrap = qs("[data-tmp-cooloff-override-wrapper]", assignmentForm);
    const presSeries     = qs("[data-tmp-pres-series-wrapper]", assignmentForm);
    const speechWrapper  = qs("[data-tmp-speech-title-wrapper]", assignmentForm);

    refreshVPE = () => renderMeetings().catch(console.error);

    // -- Unified members table -------------------------------------------------

    let expandedLpId = null;
    const trafficLabel = (t) => t === "ready" ? "🟢 Ready" : t === "stuck" ? "🔴 Stuck" : "🟡 In Progress";
    const trafficStyle = (t) => t === "ready" ? "background:#e8f5e9;color:#2e7d32" : t === "stuck" ? "background:#ffebee;color:#c62828" : "background:#fff3e0;color:#e65100";

    const loadUnifiedDetail = async (memberId, inPlace = false) => {
      const detailRow = unifiedRows?.querySelector(`[data-lp-detail="${memberId}"]`);
      if (!detailRow) return;
      const td = detailRow.querySelector("td");
      if (!td) return;

      const setChevron = (id, open) => {
        const btn = unifiedRows?.querySelector(`[data-expand-lp="${id}"]`);
        if (btn) btn.innerHTML = open ? "&#9660;" : "&#9658;";
      };

      if (!inPlace && expandedLpId === memberId) {
        detailRow.style.display = "none";
        setChevron(memberId, false);
        expandedLpId = null;
        return;
      }
      if (!inPlace && expandedLpId) {
        unifiedRows.querySelector(`[data-lp-detail="${expandedLpId}"]`)?.style?.setProperty("display", "none");
        setChevron(expandedLpId, false);
      }
      expandedLpId = memberId;
      setChevron(memberId, true);
      detailRow.style.display = "";
      if (!inPlace) {
        td.innerHTML = `<div style="padding:12px;background:#f9f9f9;border-top:1px solid var(--tmp-line);">Loading…</div>`;
      } else {
        td.style.opacity = "0.4";
      }

      try {
        const memberRow = unifiedRows?.querySelector(`[data-lp-member="${memberId}"]`);
        const mLevel = parseInt(memberRow?.dataset.mLevel || "0", 10);

        if (mLevel > 3) {
          td.style.opacity = "";
          td.innerHTML = `<div style="padding:14px;background:#f9f9f9;border-top:1px solid var(--tmp-line);">
            <p style="font-size:0.88rem;color:var(--tmp-muted);">Level 4+ progress is tracked manually. Use the member edit form to update their level.</p>
          </div>`;
          return;
        }

        const data = await api(`/members/${memberId}/level-status`);
        const sp   = data.speech_progress;
        const lvl  = data.level;

        const chips = sp ? (sp.speeches || []).map((s) =>
          `<span class="tmp-speech-chip">${esc(s.role_name)} <small>${esc(s.meeting_date)}</small></span>`
        ).join("") : "";

        const spHtml = sp ? `
          <div style="margin-bottom:12px;">
            <p style="font-weight:600;font-size:0.88rem;margin:0 0 6px;">Speeches (Level ${lvl})</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">${chips || "<span style=\"color:var(--tmp-muted)\">None recorded yet</span>"}</div>
            <p style="font-size:0.85rem;color:var(--tmp-muted);margin:0;">${sp.done}/${sp.needed} done${sp.offset ? ` (includes ${sp.offset} pre-system)` : ""}</p>
            <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
              <span style="font-size:0.85rem;">Pre-system offset:</span>
              <button class="tmp-small-button" data-offset-dec="${memberId}" data-offset-lvl="${lvl}">−</button>
              <span data-offset-val="${memberId}">${sp.offset}</span>
              <button class="tmp-small-button" data-offset-inc="${memberId}" data-offset-lvl="${lvl}">+</button>
            </div>
          </div>` : "";

        const rolesHtml = `<div style="margin-bottom:12px;">
          <p style="font-weight:600;font-size:0.88rem;margin:0 0 6px;">Club Roles (Level ${lvl}) <small style="font-weight:400;color:var(--muted);">— auto-detected from meeting history</small></p>
          ${(data.role_gaps || []).map((g) => `
            <div style="font-size:0.88rem;display:flex;align-items:center;gap:6px;margin:3px 0;">
              <span style="color:${g.met ? "var(--teal)" : "var(--muted)"};">${g.met ? "✅" : "○"}</span>
              <span style="color:${g.met ? "var(--ink)" : "var(--muted)"};">${esc(g.label)}</span>
              ${g.met ? "" : `<span style="font-size:0.75rem;color:var(--muted);">(not yet completed)</span>`}
            </div>`).join("")}
        </div>`;

        td.style.opacity = "";
        td.innerHTML = `<div style="padding:14px;background:#f9f9f9;border-top:1px solid var(--tmp-line);">
          ${spHtml}${rolesHtml}
          <p style="font-size:0.88rem;font-weight:600;color:${data.ready_to_advance ? "var(--tmp-teal)" : "#e65100"};">
            ${data.ready_to_advance ? "🟢 Ready to advance" : "🟡 " + (data.verdict_detail || []).join(" · ")}
          </p>
        </div>`;
      } catch (err) {
        td.style.opacity = "";
        td.innerHTML = `<div style="padding:12px;color:var(--tmp-burgundy);">Could not load: ${esc(err.message)}</div>`;
      }
    };

    async function renderMembers(force = false) {
      if (force || !root._allMembers) {
        try {
          root._allMembers = await api("/members");
        } catch (err) {
          if (unifiedRows) unifiedRows.innerHTML = `<tr><td colspan="7" style="color:var(--tmp-burgundy)">Could not load members: ${esc(err.message)}</td></tr>`;
          return;
        }
      }
      const all   = root._allMembers;
      const lsMap = root._levelSummaryMap || {};

      const search     = (vpeSearch?.value || "").toLowerCase();
      const pathway    = vpePathway?.value || "all";
      const levelFilt  = vpeLevel?.value || "all";
      const mentorFilt = vpeMentorFilt?.value || "all";
      const stFilt     = statusFilter?.value || "all";

      const eligible = (all || []).filter((m) =>
        m.is_eligible &&
        (!search || m.full_name.toLowerCase().includes(search) || (m.email || "").toLowerCase().includes(search)) &&
        (pathway === "all" || m.pathway === pathway) &&
        (levelFilt === "all" || String(m.level_completed) === levelFilt) &&
        (mentorFilt === "all" ||
         (mentorFilt === "none"     && !m.mentor_id && !m.mentor_name) ||
         (mentorFilt === "assigned" && (m.mentor_id  ||  m.mentor_name))) &&
        (stFilt === "all" || lsMap[String(m.id)]?.traffic_light === stFilt)
      );

      if (memberSelect) {
        memberSelect.innerHTML = '<option value="">Unassigned</option>' +
          (all || []).filter((m) => m.is_eligible)
            .map((m) => `<option value="${esc(m.id)}">${esc(m.formatted_name)}</option>`).join("");
      }

      const unmentored = (all || []).filter((m) => m.is_eligible && !m.mentor_id && !m.mentor_name && m.level_completed === 0);
      const alertEl    = qs("[data-tmp-unmentored-alert]", root);
      if (alertEl) {
        alertEl.innerHTML = unmentored.length
          ? `<div style="background:#fff8e1;border:1px solid #ffd54f;border-radius:4px;padding:10px 14px;margin-bottom:12px;font-size:13px;">
              <strong>${unmentored.length} new member${unmentored.length > 1 ? "s have" : " has"} no mentor assigned.</strong>
              Use the Assign Mentor button to pair them up.
             </div>`
          : "";
      }

      if (!root._vpeSort) root._vpeSort = { col: "name", dir: "asc" };
      const { col: vSortCol, dir: vSortDir } = root._vpeSort;
      const sortedEligible = [...eligible].sort((a, b) => {
        const mul = vSortDir === "asc" ? 1 : -1;
        if (vSortCol === "level") {
          const ld = a.level_completed - b.level_completed;
          return mul * (ld !== 0 ? ld : a.full_name.localeCompare(b.full_name));
        }
        return mul * a.full_name.localeCompare(b.full_name);
      });

      const sortInd = (col) => col === vSortCol ? (vSortDir === "asc" ? "▲" : "▼") : "↕";
      if (overviewCount) overviewCount.textContent = `${sortedEligible.length} member${sortedEligible.length !== 1 ? "s" : ""}`;

      const readyCount = Object.values(lsMap).filter((ls) => ls.traffic_light === "ready").length;
      if (readyCountEl) readyCountEl.textContent = readyCount ? `${readyCount} ready to advance` : "";

      const thead = unifiedRows?.closest("table")?.querySelector("thead");
      if (thead) {
        qsa("[data-sort-col]", thead).forEach((th) => {
          const ind = th.querySelector(".tmp-sort-ind");
          if (ind) ind.textContent = sortInd(th.dataset.sortCol);
        });
      }

      if (!unifiedRows) return;
      if (!sortedEligible.length) {
        unifiedRows.innerHTML = `<tr><td colspan="7" style="color:var(--tmp-muted);text-align:center;padding:16px;">No members match the selected filters.</td></tr>`;
        return;
      }

      unifiedRows.innerHTML = sortedEligible.map((m) => {
        const ls       = lsMap[String(m.id)];
        const inactive = m.recent_participation_count === 0 && m.total_recent_meetings_checked > 0;
        const spCell   = ls ? `${ls.speech_done}/${ls.speech_needed}` : "—";
        const roleCell = ls ? `${ls.roles_total - ls.roles_unmet}/${ls.roles_total}` : "—";
        const statusCell = ls
          ? `<span class="tmp-badge" style="${trafficStyle(ls.traffic_light)};">${trafficLabel(ls.traffic_light)}</span>`
          : `<span class="tmp-tag" style="background:#e8eaf6;color:#303f9f;font-size:0.78rem;">L${m.level_completed}</span>`;
        const mentorCell = (m.level_completed === 0 && !m.mentor_name)
          ? `<span style="color:var(--tmp-burgundy);font-size:0.82rem;">⚠ No mentor</span>`
          : esc(m.mentor_name || "—");
        const mentorBtn = m.level_completed === 0
          ? `<button class="tmp-small-button" type="button" data-assign-mentor="${m.id}" data-member-name="${esc(m.full_name)}" data-current-mentor="${esc(m.mentor_id || "")}">${m.mentor_name ? "Change" : "Assign"} Mentor</button>`
          : "";
        const actionCell = `<div style="display:flex;gap:6px;align-items:center;">${mentorBtn}<button class="tmp-small-button tmp-secondary" type="button" data-vpe-reset-pw="${m.id}">Reset PW</button><button class="tmp-small-button" data-expand-lp="${m.id}" style="min-width:28px;">&#9658;</button></div>`;
        return `<tr data-lp-member="${m.id}" data-m-level="${m.level_completed}"${inactive ? ' style="background:#fff8e1"' : ""}>
          <td><strong>${esc(m.full_name)}</strong>${inactive ? `<br><small style="color:#ef6c00;font-weight:bold">Inactive</small>` : ""}<br><small style="color:var(--tmp-muted)">${esc(m.pathway)}</small></td>
          <td>Level ${m.level_completed}</td>
          <td${spCell === "—" ? ' data-empty' : ""}>${spCell}</td>
          <td${roleCell === "—" ? ' data-empty' : ""}>${roleCell}</td>
          <td${mentorCell === "—" ? ' data-empty' : ""}>${mentorCell}</td>
          <td>${statusCell}</td>
          <td>${actionCell}</td>
        </tr>
        <tr data-vpe-pw-row="${m.id}" style="display:none;"><td colspan="7" style="padding:0;background:#f9f9f9;">
          <form data-vpe-pw-form="${m.id}" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 16px;">
            <span style="font-size:0.88rem;font-weight:600;">${esc(m.full_name)}:</span>
            <input type="password" placeholder="New password (min 8 chars)" minlength="8" required
                   style="padding:6px 10px;border:1px solid var(--tmp-line);border-radius:4px;font-size:0.88rem;flex:1;min-width:180px;" />
            <button class="tmp-small-button tmp-primary" type="submit">Set Password</button>
            <button class="tmp-small-button" type="button" data-vpe-cancel-pw="${m.id}">Cancel</button>
            <span data-vpe-pw-status="${m.id}" style="font-size:12px;"></span>
          </form>
        </td></tr>
        <tr data-lp-detail="${m.id}" style="display:none;"><td colspan="7" style="padding:0;"></td></tr>`;
      }).join("");
    }

    // -- Due for roles (data loaded at init, used by renderMembers + renderRoleStatus) --------

    // -- Meetings list --------------------------------------------------------
    async function renderMeetings(selectedId = null) {
      const meetings = await api("/meetings") || [];
      root._meetings = Array.isArray(meetings) ? meetings : [];

      if (meetingCount) meetingCount.textContent = `${meetings.length} ${meetings.length === 1 ? "meeting" : "meetings"}`;
      const prevMeetingVal = meetingSelect.value;
      meetingSelect.innerHTML =
        '<option value="">— Select or create meeting —</option>' +
        '<option value="new">+ Schedule New Meeting</option>' +
        meetings.map((m) => `<option value="${esc(m.id)}">${esc(m.meeting_date)} - ${esc(m.theme)}</option>`).join("");

      renderPendingRequests(root).catch(() => {});
      if (selectedId) meetingSelect.value = selectedId;
      else if (prevMeetingVal) meetingSelect.value = prevMeetingVal;
      updateRoles();
      applyMeetingSelection(meetingSelect.value);

      if (compactList) {
        compactList.innerHTML = meetings.length
          ? `<div class="tmp-table-wrap"><table class="tmp-table" style="font-size:0.88rem;">
              <thead><tr><th>Date</th><th>Theme</th><th class="tmp-no-print"></th></tr></thead>
              <tbody>${meetings.map((m) => `<tr>
                <td style="white-space:nowrap;">${esc(m.meeting_date)}${String(m.is_published) === "1" ? ' <span class="tmp-tag" style="background:#2e7d32;color:#fff;font-size:10px;padding:1px 5px;vertical-align:middle;">Live</span>' : ""}</td>
                <td>${esc(m.theme)}</td>
                <td class="tmp-no-print" style="white-space:nowrap;text-align:right;padding-right:6px;">
                  <button class="tmp-small-button" data-compact-edit="${esc(m.id)}">Edit</button>
                  <button class="tmp-small-button tmp-danger" data-compact-delete="${esc(m.id)}">Delete</button>
                </td>
              </tr>`).join("")}</tbody>
            </table></div>`
          : `<p style="color:var(--tmp-muted);font-size:0.88rem;">No meetings yet. Schedule your first meeting below.</p>`;
      }

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting, idx) => {
        const [h, min] = (meeting.start_time || "18:30:00").split(":").map(Number);
        let t = h * 60 + (min || 0);

        // Pure timeline rows — no operational data (status/cooloff/suitability live in Role Status panel)
        const assignments = meeting.assignments || [];
        const agendaRows = assignments.map((a, aIdx) => {
          const start   = formatTime(t);
          const dur     = Number(a.duration || 0);
          t += dur;
          const end     = formatTime(t);
          const isBreak = a.role_name.toLowerCase().startsWith("break");
          const isFirst = aIdx === 0;
          const isLast  = aIdx === assignments.length - 1;

          const moveCell = `<td class="tmp-no-print tmp-move-cell">
            <button class="tmp-move-btn" data-move-up="${a.id}" data-move-mid="${meeting.id}" ${isFirst ? "disabled" : ""} title="Move up">▲</button>
            <button class="tmp-move-btn" data-move-down="${a.id}" data-move-mid="${meeting.id}" ${isLast ? "disabled" : ""} title="Move down">▼</button>
          </td>`;

          const tg = a.time_green, ty = a.time_yellow, tr_t = a.time_red;
          const timingHint = (!isBreak && (tg || ty || tr_t))
            ? `<tr class="tmp-timing-row">
                <td class="tmp-no-print"></td>
                <td colspan="5" class="tmp-timing-hint">
                  Timer: <span class="tmp-dot-green">●</span> ${fmtSecs(tg)}
                  &nbsp;<span class="tmp-dot-yellow">●</span> ${fmtSecs(ty)}
                  &nbsp;<span class="tmp-dot-red">●</span> ${fmtSecs(tr_t)}
                </td>
              </tr>`
            : "";

          if (isBreak) {
            return `<tr style="background:#f5f5f5;">
              ${moveCell}
              <td style="color:var(--tmp-muted);">${start}</td>
              <td style="color:var(--tmp-muted);">${dur}m</td>
              <td style="color:var(--tmp-muted);">${end}</td>
              <td colspan="2" style="color:var(--tmp-muted);font-style:italic;text-align:center;">— Break —</td>
            </tr>`;
          }

          return `<tr>
            ${moveCell}
            <td>${start}</td>
            <td>${dur}m</td>
            <td>${end}</td>
            <td>${esc(a.role_name)}${a.speech_title ? `<br><small style="color:var(--tmp-muted);">${esc(a.speech_title)}</small>` : ""}</td>
            <td>${a.member_name ? esc(a.member_name) : '<em style="color:#ef6c00;">TBA</em>'}</td>
          </tr>${timingHint}`;
        }).join("");

        const totalUsed = t - (h * 60 + (min || 0));
        const limit     = Number(meeting.total_duration || 120);
        const warning   = totalUsed > limit
          ? `<p class="tmp-tag" style="background:#b71c1c;color:#fff;display:block;margin:10px 0;text-align:center;padding:5px;border-radius:4px;">Warning: Agenda (${totalUsed}m) exceeds limit (${limit}m)</p>`
          : "";

        const agendaTable = agendaRows
          ? `<div class="tmp-table-wrap" style="margin-top:12px;">
              <table class="tmp-table">
                <thead><tr><th class="tmp-no-print" style="width:54px;padding:4px;"></th><th>Start</th><th>Dur</th><th>End</th><th>Agenda Item</th><th>Member</th></tr></thead>
                <tbody>${agendaRows}</tbody>
              </table>
            </div>`
          : `<p style="color:var(--tmp-muted);margin-top:12px;">No agenda items yet.</p>`;

        return `<article class="tmp-agenda-card" data-agenda-meeting="${esc(meeting.id)}">
          <p style="margin:0 0 8px;color:var(--tmp-muted);font-size:13px;">${esc(meeting.venue || "Venue not set")}${meeting.agenda_notes ? ` · ${esc(meeting.agenda_notes)}` : ""}</p>
          ${warning}
          ${agendaTable}
          <div style="display:flex;gap:10px;margin-top:15px;flex-wrap:wrap;align-items:center;">
            <button class="tmp-button tmp-secondary tmp-small" data-print-agenda="${meeting.id}">Print Agenda</button>
            <button class="tmp-button ${String(meeting.is_published) === "1" ? "tmp-primary" : "tmp-secondary"} tmp-small" data-publish-agenda="${meeting.id}">${String(meeting.is_published) === "1" ? "Unpublish" : "Publish to Website"}</button>
            ${String(meeting.is_published) === "1" ? '<span class="tmp-tag" style="background:#2e7d32;color:#fff;padding:3px 8px;font-size:11px;">● Live on website</span>' : ""}
            <button class="tmp-button tmp-secondary tmp-small" data-notify-members="${meeting.id}" title="Send email to all assigned members with their roles">&#9993; Notify Members</button>
            <span data-notify-status="${meeting.id}" style="font-size:12px;color:var(--tmp-muted);"></span>
          </div>
        </article>`;
      }).join("")}</div>`;

      // Keep the Role Status panel in sync with the currently selected meeting
      if (meetingSelect.value) renderRoleStatus(meetingSelect.value);
      applyMeetingSelection(meetingSelect.value);
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
      if (roleSelect) roleSelect.innerHTML = html;
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

      const eligible   = root._allMembers.filter((m) => m.is_eligible && m.level_completed >= minLevel && !takenIds.includes(String(m.id)));
      const ineligible = roleName ? root._allMembers.filter((m) => m.is_eligible && m.level_completed < minLevel) : [];

      let html = '<option value="">Unassigned</option>';
      html += eligible.map((m) => `<option value="${esc(m.id)}">${esc(m.full_name)} (L${m.level_completed})</option>`).join("");
      if (ineligible.length) {
        html += `<optgroup label="Not eligible — Level ${minLevel}+ required">`;
        html += ineligible.map((m) => `<option disabled value="">${esc(m.full_name)} (L${m.level_completed})</option>`).join("");
        html += `</optgroup>`;
      }
      memberSelect.innerHTML = html;
    }

    function toggleFieldsByRole(roleName) {
      const rLower = (roleName || "").toLowerCase();
      // Speech title: only for Speaker slots (Speaker, Speaker 1, etc.)
      if (speechWrapper) speechWrapper.style.display = rLower.startsWith("speaker") ? "block" : "none";
      if (presSeries) presSeries.style.display = rLower.includes("educational presentation") ? "block" : "none";
      const timingWrap = qs("[data-tmp-timing-wrap]", root);
      if (timingWrap) timingWrap.style.display = (roleName && !rLower.startsWith("break")) ? "block" : "none";
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

        const eligible = (root._allMembers || []).filter((m) => m.is_eligible && m.level_completed >= minLevel);
        const overdueEligible = eligible.filter((m) => dueRoleMap[String(m.id)])
          .sort((a, b) => Number(dueRoleMap[String(b.id)].days_since_role) - Number(dueRoleMap[String(a.id)].days_since_role));
        const regularEligible = eligible.filter((m) => !dueRoleMap[String(m.id)]);

        let opts = '<option value="">— Unassigned —</option>';
        if (overdueEligible.length) {
          opts += `<optgroup label="Haven't had a role recently">` +
            overdueEligible.map((m) => {
              const note = takenIds.has(String(m.id)) ? " (other role)" : "";
              const days = dueRoleMap[String(m.id)].days_since_role;
              return `<option value="${esc(m.id)}" ${String(m.id) === String(primary.member_id) ? "selected" : ""}>${esc(m.full_name)} (L${m.level_completed}) — ${days}d${note}</option>`;
            }).join("") + `</optgroup>`;
        }
        opts += `<optgroup label="All eligible">` +
          regularEligible.map((m) => {
            const note = takenIds.has(String(m.id)) ? " (other role)" : "";
            return `<option value="${esc(m.id)}" ${String(m.id) === String(primary.member_id) ? "selected" : ""}>${esc(m.full_name)} (L${m.level_completed})${note}</option>`;
          }).join("") + `</optgroup>`;

        const notes = [];
        if (primary.status === "Needs replacement") notes.push(`<span class="tmp-tag" style="background:#b71c1c;color:#fff;font-size:10px;">⚠ Needs replacement</span>`);
        if (primary.cooloff_override == 1) notes.push(`<span class="tmp-tag" style="background:#ff9800;color:#fff;font-size:10px;" title="${esc(primary.override_reason || "")}">Cooloff override</span>`);
        if (primary.suitability && !primary.suitability.suitable) notes.push(`<span class="tmp-tag" style="background:#ffebee;color:#b71c1c;font-size:10px;">${esc(primary.suitability.reason)}</span>`);

        const isSpeakerSlot = baseRole.toLowerCase().startsWith("speaker");
        const timerDurVal   = primary.timer_duration != null
          ? primary.timer_duration
          : (isSpeakerSlot ? Math.max(1, (Number(primary.duration) || 8) - 1) : "");
        const timerCell = isSpeakerSlot
          ? `<td data-label="Timer (min)" style="width:80px;"><input type="number" min="1" data-assign-timer-duration="${esc(primary.id)}" value="${esc(timerDurVal)}" placeholder="—" style="width:60px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;" /></td>`
          : `<td></td>`;
        const speakerExtras = isSpeakerSlot
          ? `<div style="margin-top:6px;">
               <input type="text" data-assign-speech-title="${esc(primary.id)}" value="${esc(primary.speech_title || '')}" placeholder="Speech title (optional)" style="width:100%;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:0.82rem;" />
             </div>`
          : "";
        return `<tr>
          <td data-label="Role" style="white-space:nowrap;">${esc(baseRole)}${speakerExtras}</td>
          <td data-label="Member">
            <select data-assign-roles="${esc(allIds)}" style="width:100%;max-width:220px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;">${opts}</select>
          </td>
          <td data-label="Dur (min)" style="width:80px;">
            <input type="number" min="0" data-assign-duration="${esc(primary.id)}" value="${esc(primary.duration || '')}" placeholder="—" style="width:60px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;" />
          </td>
          ${timerCell}
          <td data-label="Notes" style="font-size:11px;">${notes.join(" ") || "—"}</td>
          <td data-label="Action"><button class="tmp-small-button tmp-danger" type="button" data-delete-roles="${esc(allIds)}" data-role-name="${esc(baseRole)}">Remove slot</button></td>
        </tr>`;
      }).join("");

      const totalGroups      = Object.keys(roleGroups).length;
      const unassignedGroups = Object.values(roleGroups).filter((g) => !g[0].member_id).length;
      const badgeBg    = totalGroups === 0 ? "#9e9e9e" : unassignedGroups === 0 ? "#2e7d32" : "#ef6c00";
      const badgeLabel = totalGroups === 0
        ? "No roles yet"
        : unassignedGroups === 0
          ? "All roles assigned ✓"
          : `${unassignedGroups} role${unassignedGroups > 1 ? "s" : ""} need${unassignedGroups === 1 ? "s" : ""} a member`;

      panel.innerHTML = `
        <p style="font-size:13px;margin:0 0 12px;">
          <strong style="color:${badgeBg};">${esc(badgeLabel)}.</strong>
          <span style="color:var(--tmp-muted);"> Select a member in any row to assign. Edit the Dur column to revise slot duration.</span>
        </p>
        <div class="tmp-table-wrap">
          <table class="tmp-table" style="font-size:0.88rem;">
            <thead><tr><th>Role</th><th>Member</th><th>Dur (min)</th><th>Timer (min)</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;

      // Register change (assign) and click (remove) once per panel lifetime
      if (!panel._listenersAdded) {
        panel._listenersAdded = true;

        panel.addEventListener("change", async (e) => {
          const sel           = e.target.closest("[data-assign-roles]");
          const durInput      = e.target.matches("[data-assign-duration]")       ? e.target : null;
          const timerDurInput = e.target.matches("[data-assign-timer-duration]") ? e.target : null;

          if (sel) {
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
          } else if (durInput) {
            const assignId = parseInt(durInput.dataset.assignDuration);
            const dur      = Number(durInput.value) || 0;
            durInput.disabled = true;
            try {
              await api("/assignments", { method: "POST", body: JSON.stringify({ id: assignId, duration: dur }) });
              await renderMeetings(meetingSelect.value);
            } catch (err) {
              alert("Failed to update duration: " + err.message);
            } finally {
              durInput.disabled = false;
            }
          } else if (timerDurInput) {
            const assignId = parseInt(timerDurInput.dataset.assignTimerDuration);
            const timerDur = Number(timerDurInput.value) || null;
            timerDurInput.disabled = true;
            try {
              await api("/assignments", { method: "POST", body: JSON.stringify({ id: assignId, timer_duration: timerDur }) });
              await renderMeetings(meetingSelect.value);
            } catch (err) {
              alert("Failed to update timer duration: " + err.message);
            } finally {
              timerDurInput.disabled = false;
            }
          }
        });

        panel.addEventListener("click", async (e) => {
          const deleteBtn = e.target.closest("[data-delete-roles]");
          if (deleteBtn) {
            const roleName = deleteBtn.dataset.roleName || "this role";
            if (!confirm(`Remove the "${roleName}" slot from this meeting?\n\nThe agenda item will be deleted. Use this when a role won't be needed for this meeting.`)) return;
            deleteBtn.disabled = true;
            try {
              for (const id of deleteBtn.dataset.deleteRoles.split(",")) {
                await api(`/assignments/${id}`, { method: "DELETE" });
              }
              await renderMeetings(meetingSelect.value);
              updateMemberDashboard().catch(() => {});
            } catch (err) {
              alert("Failed to remove: " + err.message);
              deleteBtn.disabled = false;
            }
            return;
          }

        });

        panel.addEventListener("change", function speechTitleHandler(e) {
          const inp = e.target.closest("[data-assign-speech-title]");
          if (!inp) return;
          clearTimeout(inp._saveTimer);
          inp._saveTimer = setTimeout(async () => {
            const aid = inp.dataset.assignSpeechTitle;
            try {
              await api("/assignments", { method: "POST", body: JSON.stringify({ id: parseInt(aid), speech_title: inp.value }) });
            } catch (err) {
              console.warn("Speech title save failed", err);
            }
          }, 800);
        });
      }
    }

    meetingSelect.addEventListener("change", () => {
      const val = meetingSelect.value;
      if (val !== "new") {
        updateRoles();
        renderRoleStatus(val);
      }
      applyMeetingSelection(val);
    });

    function applyMeetingSelection(val) {
      const meetingFormWrap      = qs("[data-tmp-meeting-form-wrap]", root);
      const roleAssignmentWrap   = qs("[data-tmp-role-assignment-wrap]", root);
      const meetingAgendaWrap    = qs("[data-tmp-meeting-agenda-wrap]", root);
      const deleteBtn            = qs("[data-tmp-delete-meeting]", root);
      const rolesSetup        = qs(".tmp-roles-setup", root);
      const formLabel         = qs("[data-tmp-meeting-form-label]", root);
      const formToggle        = qs("[data-tmp-meeting-form-toggle]", root);
      const formBody          = qs("[data-tmp-meeting-form-body]", root);
      const submitBtn         = meetingForm?.querySelector("button[type=submit]");

      if (val === "new") {
        const formHadId = !!(meetingForm?.elements.id?.value);
        if (formHadId) clearForm(meetingForm);
        // Auto-suggest next chapter number
        const chapterInput = meetingForm?.elements?.chapter_number;
        if (chapterInput && root._meetings?.length) {
          const maxChapter = Math.max(0, ...root._meetings.map((m) => parseInt(m.chapter_number || 0, 10)));
          if (maxChapter > 0) chapterInput.value = maxChapter + 1;
        }
        if (rolesSetup) {
          rolesSetup.style.display = "";
          const lbl       = qs("[data-tmp-roles-setup-label]", root);
          const hint      = qs("[data-tmp-roles-setup-hint]", root);
          const customBtn = qs("[data-tmp-customise-roles]", root);
          const rolesGrid = qs("[data-tmp-roles-grid]", root);
          if (lbl) lbl.textContent = "Role Slots";
          if (hint) hint.textContent = "Using standard agenda with all roles.";
          if (customBtn) { customBtn.style.display = ""; customBtn.textContent = "Customise roles ▾"; }
          if (rolesGrid) rolesGrid.style.display = "none";
        }
        if (formLabel) formLabel.textContent = "Schedule New Meeting";
        if (submitBtn) submitBtn.textContent = "Save Meeting";
        if (deleteBtn) deleteBtn.style.display = "none";
        if (formToggle) {
          formToggle.setAttribute("aria-expanded", "true");
          const ch = qs(".tmp-chevron", formToggle);
          if (ch) ch.style.transform = "rotate(90deg)";
        }
        if (formBody) formBody.style.display = "block";
        if (meetingFormWrap) meetingFormWrap.style.display = "block";
        if (roleAssignmentWrap) roleAssignmentWrap.style.display = "none";
        if (meetingAgendaWrap) meetingAgendaWrap.style.display = "none";
      } else if (val) {
        const m = (root._meetings || []).find((x) => String(x.id) === val);
        if (m && meetingForm) {
          fillForm(meetingForm, m);
          if (rolesSetup) {
            rolesSetup.style.display = "";
            rolesSetup.querySelectorAll("input[type=checkbox]").forEach((cb) => { cb.checked = false; });
            const slotInput = rolesSetup.querySelector("input[name=speech_slots]");
            if (slotInput) slotInput.value = "0";
            const lbl       = qs("[data-tmp-roles-setup-label]", root);
            const hint      = qs("[data-tmp-roles-setup-hint]", root);
            const customBtn = qs("[data-tmp-customise-roles]", root);
            const rolesGrid = qs("[data-tmp-roles-grid]", root);
            if (lbl) lbl.textContent = "Add Role Slots";
            if (hint) hint.textContent = "Check roles to add them if not already in this meeting. Set Speech Slots > 0 to append extra Speaker + Evaluator pairs.";
            if (customBtn) customBtn.style.display = "none";
            if (rolesGrid) rolesGrid.style.display = "grid";
          }
          if (formLabel) formLabel.textContent = "Edit Meeting";
          if (submitBtn) submitBtn.textContent = "Update Meeting";
          if (deleteBtn) deleteBtn.style.display = "";
          if (formToggle) {
            formToggle.setAttribute("aria-expanded", "false");
            const ch = qs(".tmp-chevron", formToggle);
            if (ch) ch.style.transform = "";
          }
          if (formBody) formBody.style.display = "none";
          if (meetingFormWrap) meetingFormWrap.style.display = "block";
        }
        if (roleAssignmentWrap) roleAssignmentWrap.style.display = "block";
        if (meetingAgendaWrap) meetingAgendaWrap.style.display = "block";
        meetingList.querySelectorAll("[data-agenda-meeting]").forEach((card) => {
          card.style.display = String(card.dataset.agendaMeeting) === val ? "" : "none";
        });
      } else {
        if (meetingFormWrap) meetingFormWrap.style.display = "none";
        if (roleAssignmentWrap) roleAssignmentWrap.style.display = "none";
        if (meetingAgendaWrap) meetingAgendaWrap.style.display = "none";
      }
    }

    qs("[data-tmp-role-assignment-toggle]", root)?.addEventListener("click", (e) => {
      const btn  = e.currentTarget;
      const open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      const body = qs("[data-tmp-role-assignment-body]", root);
      if (body) body.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", btn);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
    });

    qs("[data-tmp-agenda-toggle]", root)?.addEventListener("click", (e) => {
      const btn  = e.currentTarget;
      const open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      const body = qs("[data-tmp-agenda-body]", root);
      if (body) body.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", btn);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
    });

    qs("[data-tmp-rebuild-agenda]", root)?.addEventListener("click", async () => {
      const mid = meetingForm?.elements.id?.value;
      if (!mid) { alert("Select a meeting first."); return; }
      if (!confirm("Rebuild the agenda in the prescribed order?\n\nAll role slots will be recreated in the standard sequence. Existing member assignments are preserved.")) return;
      const btn = qs("[data-tmp-rebuild-agenda]", root);
      if (btn) { btn.disabled = true; btn.textContent = "Rebuilding…"; }
      try {
        const result = await api(`/meetings/${mid}/rebuild-agenda`, { method: "POST", body: JSON.stringify({}) });
        await renderMeetings(mid);
        updateRoles();
        alert(`✓ Agenda rebuilt — ${result.rebuilt} slots in prescribed order.`);
      } catch (err) {
        alert("Error: " + err.message);
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = "Rebuild Agenda"; }
      }
    });

    qs("[data-tmp-delete-meeting]", root)?.addEventListener("click", async () => {
      const mid = meetingForm?.elements.id?.value;
      if (!mid) return;
      if (!confirm("Delete this meeting? This removes all role assignments, requests, votes, and attendance records permanently.")) return;
      try {
        await api(`/meetings/${mid}`, { method: "DELETE" });
        meetingSelect.value = "";
        updateRoles();
        await renderMeetings();
        updateMemberDashboard().catch(() => {});
      } catch (err) {
        alert("Failed to delete meeting: " + err.message);
      }
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

    roleSelect?.addEventListener("change", () => {
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
          // Convert seconds to M:SS for timing inputs
          ["time_green", "time_yellow", "time_red"].forEach((f) => {
            const el = assignmentForm.elements[f];
            if (el) el.value = asgn[f] != null ? fmtSecs(Number(asgn[f])) : "";
          });
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
        const d      = formData(meetingForm);
        const isEdit = !!(d.id);
        const newM   = await api("/meetings", { method: "POST", body: JSON.stringify(d) });
        clearForm(meetingForm);
        // Restore create-mode labels
        const formLabel = qs("[data-tmp-meeting-form-label]", root);
        if (formLabel) formLabel.textContent = "Schedule New Meeting";
        if (btn) btn.textContent = "Save Meeting";
        const rolesSetup = qs(".tmp-roles-setup", root);
        if (rolesSetup) rolesSetup.style.display = "";
        // Auto-collapse the form after a successful save
        const formToggle = qs("[data-tmp-meeting-form-toggle]", root);
        const formBody   = qs("[data-tmp-meeting-form-body]", root);
        if (formToggle) { formToggle.setAttribute("aria-expanded", "false"); qs(".tmp-chevron", formToggle).style.transform = ""; }
        if (formBody)   formBody.style.display = "none";
        // Inline success feedback
        if (statusEl) {
          statusEl.textContent = isEdit
            ? `Meeting for ${esc(d.meeting_date)} updated.`
            : `Meeting for ${esc(d.meeting_date || "new date")} created — roles ready.`;
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

    qs("[data-tmp-clear-meeting]", root)?.addEventListener("click", () => {
      clearForm(meetingForm);
      const formLabel  = qs("[data-tmp-meeting-form-label]", root);
      if (formLabel) formLabel.textContent = "Schedule New Meeting";
      const submitBtn  = meetingForm.querySelector("button[type=submit]");
      if (submitBtn) submitBtn.textContent = "Save Meeting";
      const rolesSetup = qs(".tmp-roles-setup", root);
      if (rolesSetup) rolesSetup.style.display = "";
    });
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

      const moveBtn      = e.target.closest("[data-move-up],[data-move-down]");
      if (moveBtn) {
        const mid      = moveBtn.dataset.moveMid;
        const aid      = parseInt(moveBtn.dataset.moveUp || moveBtn.dataset.moveDown, 10);
        const isUp     = !!moveBtn.dataset.moveUp;
        const meeting  = (root._meetings || []).find((m) => String(m.id) === String(mid));
        if (!meeting) return;
        const arr      = meeting.assignments;
        const idx      = arr.findIndex((a) => Number(a.id) === aid);
        if (idx < 0) return;
        const swapIdx  = isUp ? idx - 1 : idx + 1;
        if (swapIdx < 0 || swapIdx >= arr.length) return;
        [arr[idx], arr[swapIdx]] = [arr[swapIdx], arr[idx]];
        try {
          await api(`/meetings/${mid}/agenda-order`, { method: "POST", body: JSON.stringify({ order: arr.map((a) => a.id) }) });
        } catch (err) {
          console.error("Reorder failed", err);
        }
        await renderMeetings(meetingSelect.value);
        return;
      }

      const print          = e.target.closest("[data-print-agenda]");
      const viewConflicts  = e.target.closest("[data-view-conflicts]");
      const delMeeting     = e.target.closest("[data-delete-meeting]");
      const editMeeting    = e.target.closest("[data-edit-meeting]");
      const approveReq     = e.target.closest("[data-vpe-approve-req]");
      const publishAgenda  = e.target.closest("[data-publish-agenda]");

      if (publishAgenda) {
        const mid = publishAgenda.dataset.publishAgenda;
        try {
          await api(`/meetings/${mid}/publish`, { method: "POST" });
          await renderMeetings(meetingSelect.value);
        } catch (err) {
          alert("Could not update publish status: " + err.message);
        }
        return;
      }

      const notifyMembers = e.target.closest("[data-notify-members]");
      if (notifyMembers) {
        const mid       = notifyMembers.dataset.notifyMembers;
        const statusEl  = qs(`[data-notify-status="${mid}"]`);
        const confirmed = confirm("Send role assignment emails to all assigned members for this meeting?");
        if (!confirmed) return;
        notifyMembers.disabled = true;
        if (statusEl) { statusEl.textContent = "Sending…"; statusEl.style.color = "var(--tmp-muted)"; }
        try {
          const res = await api(`/meetings/${mid}/notify-members`, { method: "POST" });
          if (statusEl) {
            statusEl.textContent = res.found === 0
              ? "No assigned members found."
              : `✓ ${res.sent}/${res.found} sent.`;
            statusEl.style.color = res.sent === res.found && res.found > 0 ? "#2e7d32" : "#e65100";
            setTimeout(() => { if (statusEl) statusEl.textContent = ""; }, 6000);
          }
        } catch (err) {
          if (statusEl) { statusEl.textContent = "Failed: " + err.message; statusEl.style.color = "#c62828"; }
        } finally {
          notifyMembers.disabled = false;
        }
        return;
      }

      if (editMeeting) {
        const mid = editMeeting.dataset.editMeeting;
        const m   = root._meetings.find((x) => String(x.id) === mid);
        if (!m) return;
        fillForm(meetingForm, m);
        const rolesSetup = qs(".tmp-roles-setup", root);
        if (rolesSetup) rolesSetup.style.display = "none";
        const formLabel  = qs("[data-tmp-meeting-form-label]", root);
        if (formLabel) formLabel.textContent = "Edit Meeting";
        const submitBtn  = meetingForm.querySelector("button[type=submit]");
        if (submitBtn) submitBtn.textContent = "Update Meeting";
        const formToggle = qs("[data-tmp-meeting-form-toggle]", root);
        const formBody   = qs("[data-tmp-meeting-form-body]", root);
        if (formToggle) {
          formToggle.setAttribute("aria-expanded", "true");
          const ch = qs(".tmp-chevron", formToggle);
          if (ch) ch.style.transform = "rotate(90deg)";
        }
        if (formBody) formBody.style.display = "block";
        formToggle?.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
      }

      if (delMeeting) {
        if (confirm("Delete this meeting? This removes all role assignments, requests, votes, and attendance records permanently.")) {
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

    // Compact list (Edit / Delete)
    if (compactList) {
      compactList.addEventListener("click", async (e) => {
        const editBtn   = e.target.closest("[data-compact-edit]");
        const deleteBtn = e.target.closest("[data-compact-delete]");

        if (editBtn) {
          const mid = editBtn.dataset.compactEdit;
          const m   = root._meetings.find((x) => String(x.id) === mid);
          if (!m) return;
          fillForm(meetingForm, m);
          const rolesSetup = qs(".tmp-roles-setup", root);
          if (rolesSetup) rolesSetup.style.display = "none";
          const formLabel  = qs("[data-tmp-meeting-form-label]", root);
          if (formLabel) formLabel.textContent = "Edit Meeting";
          const submitBtn  = meetingForm.querySelector("button[type=submit]");
          if (submitBtn) submitBtn.textContent = "Update Meeting";
          const formToggle = qs("[data-tmp-meeting-form-toggle]", root);
          const formBody   = qs("[data-tmp-meeting-form-body]", root);
          if (formToggle) {
            formToggle.setAttribute("aria-expanded", "true");
            const ch = qs(".tmp-chevron", formToggle);
            if (ch) ch.style.transform = "rotate(90deg)";
          }
          if (formBody) formBody.style.display = "block";
          formToggle?.scrollIntoView({ behavior: "smooth", block: "start" });
          return;
        }

        if (deleteBtn) {
          if (confirm("Delete this meeting? This removes all role assignments, requests, votes, and attendance records permanently.")) {
            const mid = deleteBtn.dataset.compactDelete;
            deleteBtn.closest("tr")?.remove();
            await api(`/meetings/${mid}`, { method: "DELETE" });
            meetingSelect.value = "";
            updateRoles();
            await renderMeetings();
            updateMemberDashboard().catch(() => {});
          }
        }
      });
    }

    // Mentor assignment modal logic
    const modal       = document.getElementById("tmp-mentor-modal");
    const mentorSel   = document.getElementById("tmp-mentor-select");
    const modalLabel  = document.getElementById("tmp-mentor-modal-member");
    const modalCancel = document.getElementById("tmp-mentor-modal-cancel");
    const modalSave   = document.getElementById("tmp-mentor-modal-save");
    let pendingMentorMemberId = null;

    if (modal) {
      unifiedRows?.addEventListener("click", async (e) => {
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
    statusFilter?.addEventListener("change", () => renderMembers());

    // Unified table: sort via thead delegation
    unifiedRows?.closest("table")?.querySelector("thead")?.addEventListener("click", (ev) => {
      const th = ev.target.closest("[data-sort-col]");
      if (!th) return;
      const col = th.dataset.sortCol;
      if (!root._vpeSort) root._vpeSort = { col: "name", dir: "asc" };
      root._vpeSort = root._vpeSort.col === col
        ? { col, dir: root._vpeSort.dir === "asc" ? "desc" : "asc" }
        : { col, dir: "asc" };
      renderMembers();
    });

    // Unified table: expand detail + offset controls + Reset PW
    unifiedRows?.addEventListener("click", async (e) => {
      const expandBtn  = e.target.closest("[data-expand-lp]");
      const incBtn     = e.target.closest("[data-offset-inc]");
      const decBtn     = e.target.closest("[data-offset-dec]");
      const resetPwBtn = e.target.closest("[data-vpe-reset-pw]");
      const cancelPwBtn = e.target.closest("[data-vpe-cancel-pw]");

      if (resetPwBtn) {
        const id  = resetPwBtn.dataset.vpeResetPw;
        const row = unifiedRows.querySelector(`[data-vpe-pw-row="${id}"]`);
        if (row) row.style.display = row.style.display === "none" ? "" : "none";
        return;
      }

      if (cancelPwBtn) {
        const row = unifiedRows.querySelector(`[data-vpe-pw-row="${cancelPwBtn.dataset.vpeCancelPw}"]`);
        if (row) row.style.display = "none";
        return;
      }

      if (expandBtn) {
        await loadUnifiedDetail(expandBtn.dataset.expandLp);
        return;
      }

      if (incBtn || decBtn) {
        const mId   = (incBtn || decBtn).dataset[incBtn ? "offsetInc" : "offsetDec"];
        const lvl   = (incBtn || decBtn).dataset.offsetLvl;
        const valEl = unifiedRows.querySelector(`[data-offset-val="${mId}"]`);
        let current = parseInt(valEl?.textContent || "0", 10);
        current = Math.max(0, current + (incBtn ? 1 : -1));
        if (valEl) valEl.textContent = current;
        try {
          await api(`/members/${mId}/pathway-offset`, {
            method: "POST",
            body: JSON.stringify({ level: parseInt(lvl, 10), offset: current }),
          });
          await loadUnifiedDetail(mId, true);
          api("/vpe/members/level-summary").then((fresh) => {
            if (!fresh) return;
            root._levelSummaryMap = Object.fromEntries(fresh.map((s) => [String(s.member_id), s]));
            const m   = fresh.find((x) => String(x.member_id) === String(mId));
            const row = unifiedRows.querySelector(`[data-lp-member="${mId}"]`);
            if (m && row && row.cells[2]) {
              row.cells[2].textContent = m.speech_done !== null ? `${m.speech_done}/${m.speech_needed}` : "—";
            }
          }).catch(() => {});
        } catch (err) {
          alert("Could not save offset: " + err.message);
        }
      }
    });

    // VPE table: reset password submit
    unifiedRows?.addEventListener("submit", async (e) => {
      const form = e.target.closest("[data-vpe-pw-form]");
      if (!form) return;
      e.preventDefault();
      const id     = form.dataset.vpePwForm;
      const pw     = form.querySelector("input[type=password]").value;
      const status = unifiedRows.querySelector(`[data-vpe-pw-status="${id}"]`);
      const btn    = form.querySelector("button[type=submit]");
      btn.disabled = true;
      if (status) { status.textContent = "Saving…"; status.style.color = ""; }
      try {
        await api(`/members/${id}/reset-password`, { method: "POST", body: JSON.stringify({ new_password: pw }) });
        if (status) { status.textContent = "Password set!"; status.style.color = "#2e7d32"; }
        form.reset();
        setTimeout(() => {
          if (status) status.textContent = "";
          const row = unifiedRows.querySelector(`[data-vpe-pw-row="${id}"]`);
          if (row) row.style.display = "none";
        }, 2000);
      } catch (err) {
        if (status) { status.textContent = err.message; status.style.color = "#c62828"; }
      } finally {
        btn.disabled = false;
      }
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

    // Inject gate settings and timer defaults panels at the bottom of the meetings tab
    const meetingsTab = qs("[data-tab-body='meetings']", root) || root;
    const gateSection = document.createElement("section");
    gateSection.className = "tmp-panel";
    gateSection.innerHTML = `
      <button class="tmp-collapsible-toggle" data-tmp-gate-settings-toggle aria-expanded="false" style="width:100%;text-align:left;">
        Role Gate Settings
        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
      </button>
      <div data-tmp-gate-settings-body style="display:none;margin-top:14px;"></div>`;
    gateSection.setAttribute("data-tmp-gate-settings-panel", "");
    meetingsTab.appendChild(gateSection);

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

    // -- Timer defaults settings panel ----------------------------------------
    async function renderTimingSettings() {
      const body = qs("[data-tmp-timing-settings-body]", root);
      if (!body) return;
      let rules, clubSettings;
      try {
        [rules, clubSettings] = await Promise.all([
          api("/settings/timing-rules"),
          api("/settings/club"),
        ]);
      } catch (err) {
        body.innerHTML = `<p style="color:var(--tmp-burgundy)">Could not load settings: ${esc(err.message)}</p>`;
        return;
      }
      body.innerHTML = `
        <div style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--tmp-line);">
          <label style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;">Default Venue (shown on print agenda when meeting has no venue set)</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input id="tmp-default-venue" type="text" value="${esc(clubSettings.default_venue || "")}" placeholder="Room, address, or meeting link" style="flex:1;padding:5px 8px;" />
            <button class="tmp-small-button tmp-primary" id="tmp-venue-save">Save</button>
            <span id="tmp-venue-status" style="font-size:12px;color:var(--tmp-muted);"></span>
          </div>
          <div style="margin-top:8px;display:flex;gap:8px;align-items:center;">
            <input id="tmp-default-maps-url" type="url" value="${esc(clubSettings.default_maps_url || "")}" placeholder="Google Maps link (shown on home page with published agenda)" style="flex:1;padding:5px 8px;" />
            <button class="tmp-small-button tmp-primary" id="tmp-maps-url-save">Save</button>
            <span id="tmp-maps-url-status" style="font-size:12px;color:var(--tmp-muted);"></span>
          </div>
          <div style="margin-top:8px;">
            <label style="display:block;margin-bottom:4px;font-size:12px;font-weight:600;">Club Mission Statement (shown on printed agenda)</label>
            <div style="display:flex;gap:8px;align-items:flex-start;">
              <textarea id="tmp-club-mission" rows="2" style="flex:1;padding:5px 8px;font-size:12px;" placeholder="We provide a supportive and positive learning experience…">${esc(clubSettings.club_mission || "")}</textarea>
              <div style="display:flex;flex-direction:column;gap:4px;">
                <button class="tmp-small-button tmp-primary" id="tmp-mission-save">Save</button>
                <span id="tmp-mission-status" style="font-size:12px;color:var(--tmp-muted);"></span>
              </div>
            </div>
          </div>
        </div>
        <div style="margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--tmp-line);">
          <label style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;">Default Agenda Slot Durations (applied when creating or rebuilding an agenda)</label>
          <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
            <label style="font-size:12px;">Speaker speech (min)
              <input id="tmp-speaker-dur" type="number" min="1" max="60" value="${esc(clubSettings.speaker_duration || 7)}" style="width:60px;padding:3px 6px;margin-left:6px;border:1px solid #ddd;border-radius:4px;" />
            </label>
            <label style="font-size:12px;">Table Topics session (min)
              <input id="tmp-ttm-dur" type="number" min="1" max="60" value="${esc(clubSettings.ttm_duration || 20)}" style="width:60px;padding:3px 6px;margin-left:6px;border:1px solid #ddd;border-radius:4px;" />
            </label>
            <button class="tmp-small-button tmp-primary" id="tmp-dur-save">Save</button>
            <span id="tmp-dur-status" style="font-size:12px;color:var(--tmp-muted);"></span>
          </div>
        </div>
        <p style="font-size:12px;color:var(--tmp-muted);margin-bottom:10px;">
          Enter times as M:SS (e.g. <strong>5:00</strong>). These defaults auto-fill when you create a new meeting.
          You can override per slot in the Role Assignment form.
        </p>
        <table class="tmp-table" style="font-size:0.88rem;">
          <thead><tr><th>Agenda Slot Type</th><th style="color:#2e7d32;">● Green</th><th style="color:#f9a825;">● Yellow</th><th style="color:#c62828;">● Red</th></tr></thead>
          <tbody>${rules.map((r) => `
            <tr>
              <td>${esc(r.label)}</td>
              <td><input type="text" data-timing-key="${esc(r.key)}" data-timing-field="green"  value="${esc(fmtSecs(r.green))}"  style="width:70px;padding:3px 6px;" /></td>
              <td><input type="text" data-timing-key="${esc(r.key)}" data-timing-field="yellow" value="${esc(fmtSecs(r.yellow))}" style="width:70px;padding:3px 6px;" /></td>
              <td><input type="text" data-timing-key="${esc(r.key)}" data-timing-field="red"    value="${esc(fmtSecs(r.red))}"    style="width:70px;padding:3px 6px;" /></td>
            </tr>`).join("")}
          </tbody>
        </table>
        <div style="margin-top:12px;">
          <button class="tmp-small-button tmp-primary" id="tmp-timing-save">Save Timer Defaults</button>
          <span id="tmp-timing-status" style="margin-left:10px;font-size:12px;color:var(--tmp-muted);"></span>
        </div>`;

      qs("#tmp-venue-save", root)?.addEventListener("click", async () => {
        const btn    = qs("#tmp-venue-save", root);
        const status = qs("#tmp-venue-status", root);
        const val    = qs("#tmp-default-venue", root)?.value || "";
        btn.disabled = true;
        try {
          await api("/settings/club", { method: "POST", body: JSON.stringify({ default_venue: val }) });
          TMPortal.clubVenue = val;
          if (status) { status.textContent = "Saved!"; status.style.color = "#2e7d32"; }
          setTimeout(() => { if (status) status.textContent = ""; }, 2000);
        } catch (err) {
          if (status) { status.textContent = "Error: " + err.message; status.style.color = "#c62828"; }
        } finally {
          btn.disabled = false;
        }
      });

      qs("#tmp-maps-url-save", root)?.addEventListener("click", async () => {
        const btn    = qs("#tmp-maps-url-save", root);
        const status = qs("#tmp-maps-url-status", root);
        const val    = qs("#tmp-default-maps-url", root)?.value || "";
        btn.disabled = true;
        try {
          await api("/settings/club", { method: "POST", body: JSON.stringify({ default_maps_url: val }) });
          if (status) { status.textContent = "Saved!"; status.style.color = "#2e7d32"; }
          setTimeout(() => { if (status) status.textContent = ""; }, 2000);
        } catch (err) {
          if (status) { status.textContent = "Error: " + err.message; status.style.color = "#c62828"; }
        } finally {
          btn.disabled = false;
        }
      });

      qs("#tmp-mission-save", root)?.addEventListener("click", async () => {
        const btn    = qs("#tmp-mission-save", root);
        const status = qs("#tmp-mission-status", root);
        const val    = qs("#tmp-club-mission", root)?.value || "";
        btn.disabled = true;
        try {
          await api("/settings/club", { method: "POST", body: JSON.stringify({ club_mission: val }) });
          TMPortal.clubMission = val;
          if (status) { status.textContent = "Saved!"; status.style.color = "#2e7d32"; }
          setTimeout(() => { if (status) status.textContent = ""; }, 2000);
        } catch (err) {
          if (status) { status.textContent = "Error: " + err.message; status.style.color = "#c62828"; }
        } finally {
          btn.disabled = false;
        }
      });

      qs("#tmp-dur-save", root)?.addEventListener("click", async () => {
        const btn    = qs("#tmp-dur-save", root);
        const status = qs("#tmp-dur-status", root);
        const speakerVal = parseInt(qs("#tmp-speaker-dur", root)?.value, 10);
        const ttmVal     = parseInt(qs("#tmp-ttm-dur", root)?.value, 10);
        if (!speakerVal || !ttmVal) {
          if (status) { status.textContent = "Enter valid minutes."; status.style.color = "#c62828"; }
          return;
        }
        btn.disabled = true;
        try {
          await api("/settings/club", { method: "POST", body: JSON.stringify({ speaker_duration: speakerVal, ttm_duration: ttmVal }) });
          clubSettings.speaker_duration = speakerVal;
          clubSettings.ttm_duration     = ttmVal;
          if (status) { status.textContent = "Saved!"; status.style.color = "#2e7d32"; }
          setTimeout(() => { if (status) status.textContent = ""; }, 2000);
        } catch (err) {
          if (status) { status.textContent = "Error: " + err.message; status.style.color = "#c62828"; }
        } finally {
          btn.disabled = false;
        }
      });

      qs("#tmp-timing-save", root)?.addEventListener("click", async () => {
        const saveBtn    = qs("#tmp-timing-save", root);
        const statusSpan = qs("#tmp-timing-status", root);
        saveBtn.disabled = true;
        if (statusSpan) statusSpan.textContent = "Saving…";
        const updated = rules.map((r) => ({
          key:    r.key,
          label:  r.label,
          green:  parseMSS(body.querySelector(`[data-timing-key="${r.key}"][data-timing-field="green"]`)?.value)  ?? r.green,
          yellow: parseMSS(body.querySelector(`[data-timing-key="${r.key}"][data-timing-field="yellow"]`)?.value) ?? r.yellow,
          red:    parseMSS(body.querySelector(`[data-timing-key="${r.key}"][data-timing-field="red"]`)?.value)    ?? r.red,
        }));
        try {
          await api("/settings/timing-rules", { method: "POST", body: JSON.stringify(updated) });
          rules = updated;
          if (statusSpan) { statusSpan.textContent = "Saved!"; statusSpan.style.color = "#2e7d32"; }
          setTimeout(() => { if (statusSpan) statusSpan.textContent = ""; }, 2000);
        } catch (err) {
          if (statusSpan) { statusSpan.textContent = "Error: " + err.message; statusSpan.style.color = "#c62828"; }
        } finally {
          saveBtn.disabled = false;
        }
      });
    }

    const timingSection = document.createElement("section");
    timingSection.className = "tmp-panel";
    timingSection.innerHTML = `
      <button class="tmp-collapsible-toggle" data-tmp-timing-settings-toggle aria-expanded="false" style="width:100%;text-align:left;">
        Timer Defaults
        <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
      </button>
      <div data-tmp-timing-settings-body style="display:none;margin-top:14px;"></div>`;
    meetingsTab.appendChild(timingSection);

    qs("[data-tmp-timing-settings-toggle]", root)?.addEventListener("click", async (e) => {
      const btn  = e.currentTarget;
      const open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      const body = qs("[data-tmp-timing-settings-body]", root);
      if (body) body.style.display = open ? "none" : "block";
      const chevron = qs(".tmp-chevron", btn);
      if (chevron) chevron.style.transform = open ? "" : "rotate(90deg)";
      if (!open) await renderTimingSettings();
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
      const lsSummary   = await api("/vpe/members/level-summary").catch(() => []);
      root._levelSummaryMap = Object.fromEntries((lsSummary || []).map((s) => [String(s.member_id), s]));
      await Promise.all([
        renderMembers(true).catch((err) => console.error("Members load failed:", err)),
        renderMeetings(),
      ]);
    } catch (err) {
      console.error("VPE init error:", err);
      if (meetingList) meetingList.innerHTML = `<div class="tmp-panel tmp-danger"><h3>Error loading agendas</h3><p>${esc(err.message)}</p></div>`;
    }
  }

  async function renderPendingRequests(root) {
    const count      = qs("[data-tmp-request-count]", root);
    const list       = qs("[data-tmp-vpe-requests]", root);
    const body       = qs("[data-tmp-requests-body]", root);
    const toggleBtn  = qs("[data-tmp-requests-toggle]", root);
    const approveBtn = qs("[data-tmp-approve-all-btn]", root);
    if (!list) {
      console.error("Pending requests elements not found");
      return;
    }

    if (!root._requestsToggleBound) {
      root._requestsToggleBound = true;
      toggleBtn?.addEventListener("click", () => {
        if (!body) return;
        const open = body.style.display !== "none";
        body.style.display = open ? "none" : "";
        toggleBtn.setAttribute("aria-expanded", String(!open));
        const ch = toggleBtn.querySelector(".tmp-chevron");
        if (ch) ch.style.transform = open ? "" : "rotate(90deg)";
      });
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
    root._pendingRolesCount = totalRequests;
    updateMembersTabBadge(root);

    if (count) { count.textContent = totalRequests; count.style.display = totalRequests > 0 ? "inline-flex" : "none"; }

    if (totalRequests === 0) {
      list.innerHTML = "<p>No pending requests across upcoming meetings.</p>";
      if (approveBtn) approveBtn.style.display = "none";
      return;
    }

    if (approveBtn) approveBtn.style.display = "block";
    if (body) { body.style.display = ""; if (toggleBtn) { toggleBtn.setAttribute("aria-expanded", "true"); const ch = toggleBtn.querySelector(".tmp-chevron"); if (ch) ch.style.transform = "rotate(90deg)"; } }

    const buildAccordion = (filteredMeetings) => {
      const roleMap = new Map();
      for (const meeting of filteredMeetings) {
        for (const role of meeting.roles) {
          if (!roleMap.has(role.roleName)) roleMap.set(role.roleName, []);
          for (const req of role.requests) {
            roleMap.get(role.roleName).push({ ...req, meetingDate: meeting.meetingDate, theme: meeting.theme, meetingId: meeting.meetingId });
          }
        }
      }
      const sortedRoles = [...roleMap.keys()].sort((a, b) => roleSort(a) - roleSort(b));
      if (sortedRoles.length === 0) return "<p style=\"color:var(--tmp-muted);font-size:0.88rem;\">No pending requests for this meeting.</p>";

      let html = '<div class="tmp-role-accordion">';
      for (const roleName of sortedRoles) {
        const reqs   = roleMap.get(roleName);
        const roleId = 'racc-' + roleName.replace(/[^a-z0-9]/gi, '-').toLowerCase();
        html += `<div class="tmp-role-accordion-item" data-accordion-item>
          <button class="tmp-role-accordion-header" data-accordion-toggle aria-expanded="false" aria-controls="${esc(roleId)}">
            <span>${esc(roleName)}</span>
            <span style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:12px;color:#666;">${reqs.length} request${reqs.length !== 1 ? "s" : ""}</span>
              <span class="tmp-chevron" aria-hidden="true">&#9658;</span>
            </span>
          </button>
        <div id="${esc(roleId)}" class="tmp-role-accordion-body" style="display:none;">`;

      for (const req of reqs) {
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
              <span style="margin-left:6px;">${esc(req.meetingDate)}${req.theme ? ' — ' + esc(req.theme) : ''}</span>
              ${reasonsHtml}
            </div>
          </div>
          <button class="tmp-small-button" data-approve-request="${req.requestId}" data-member-id="${req.memberId}" data-meeting-id="${req.meetingId}" data-role-name="${esc(roleName)}" style="white-space:nowrap;margin-left:8px;">
            Approve
          </button>
        </div>`;
      }

        html += `</div></div>`;
      }
      html += `</div>`;
      return html;
    };

    const renderFiltered = () => {
      const filter = root._requestMeetingFilter ?? "";
      const filtered = filter ? meetings.filter((m) => String(m.meetingId) === filter) : meetings;
      const filterHtml = `<div style="margin-bottom:12px;">
        <label style="font-size:0.85rem;color:var(--tmp-muted);margin-right:8px;">Filter by meeting:</label>
        <select data-tmp-requests-meeting-filter style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:0.85rem;">
          <option value="">All upcoming meetings</option>
          ${meetings.map((m) => `<option value="${esc(String(m.meetingId))}"${String(m.meetingId) === filter ? " selected" : ""}>${esc(m.meetingDate)}${m.theme ? " — " + esc(m.theme) : ""}</option>`).join("")}
        </select>
      </div>`;
      list.innerHTML = filterHtml + buildAccordion(filtered);
      list.querySelector("[data-tmp-requests-meeting-filter]")?.addEventListener("change", (e) => {
        root._requestMeetingFilter = e.target.value;
        renderFiltered();
      });
    };

    renderFiltered();

    // Accordion: one panel open at a time. Guard prevents stacking listeners across re-renders.
    if (!root._accordionListenerAdded) {
      root._accordionListenerAdded = true;
      list.addEventListener("click", (e) => {
        const hdr = e.target.closest("[data-accordion-toggle]");
        if (!hdr) return;
        const item  = hdr.closest("[data-accordion-item]");
        const panel = item?.querySelector(".tmp-role-accordion-body");
        if (!item || !panel) return;
        const isOpen = hdr.getAttribute("aria-expanded") === "true";
        // Close all
        qsa("[data-accordion-item]", list).forEach((i) => {
          const h = i.querySelector("[data-accordion-toggle]");
          const p = i.querySelector(".tmp-role-accordion-body");
          if (h) h.setAttribute("aria-expanded", "false");
          if (p) p.style.display = "none";
          const ch = h?.querySelector(".tmp-chevron");
          if (ch) ch.style.transform = "";
        });
        // Open clicked one if it was closed
        if (!isOpen) {
          hdr.setAttribute("aria-expanded", "true");
          panel.style.display = "";
          const ch = hdr.querySelector(".tmp-chevron");
          if (ch) ch.style.transform = "rotate(90deg)";
        }
      });
    }

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
    const declareWinnersBtn  = qs('[data-tmp-declare-winners-btn]', panel);
    const linkUrlEl          = qs('[data-tmp-vote-link-url]', panel);
    const copyLinkBtn        = qs('[data-tmp-copy-vote-link]', panel);
    const linkExpiryEl       = qs('[data-tmp-vote-link-expiry]', panel);
    const postMeetingActions = qs('[data-tmp-postmeeting-actions]', panel);

    let currentMeetingId = null;
    let pollTimer        = null;
    let pollIsOpen       = false;
    let votingMeetings   = [];

    const rateSpeakerSection  = qs('[data-tmp-rate-speaker-section]', panel);
    const speakerFeedbackList = qs('[data-tmp-speaker-feedback-list]', panel);
    const feedbackEmailStatus = qs('[data-tmp-speaker-feedback-email-status]', panel);

    // Populate meeting dropdown from existing meetings data
    api('/meetings').then(meetings => {
      if (!meetings || !meetings.length) return;
      votingMeetings = meetings;
      const today = localDateStr(new Date());
      const isExCom = !!panel.closest('[data-tmp-excom-panel]');
      (isExCom ? meetings.filter(m => m.meeting_date === today) : meetings).forEach(m => {
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
        if (rateSpeakerSection)  rateSpeakerSection.style.display = 'none';
        if (postMeetingActions)  postMeetingActions.style.display = 'none';
        return;
      }
      ttEntry.style.display = 'block';
      nomineesBlock.style.display = 'block';
      if (postMeetingActions) postMeetingActions.style.display = 'block';
      loadNominees();
      pollTimer = setInterval(loadNominees, 30000);
      renderRateSpeakers(currentMeetingId);
      autoGenerateVotingLink();
    }

    function autoGenerateVotingLink() {
      if (!currentMeetingId) return;
      if (linkUrlEl) linkUrlEl.textContent = 'Generating…';
      if (linkExpiryEl) linkExpiryEl.textContent = '';
      api('/voting/token', { method: 'POST', body: JSON.stringify({ meeting_id: currentMeetingId }) }).then(data => {
        if (linkUrlEl) linkUrlEl.textContent = data.url;
      }).catch(() => {
        if (linkUrlEl) linkUrlEl.textContent = 'Could not generate link — try refreshing.';
      });
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
        openPollBtn.textContent = isOpen ? 'Close Moment of Glory' : 'Moment of Glory';
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

    if (copyLinkBtn && linkUrlEl) {
      copyLinkBtn.addEventListener('click', () => {
        const url = linkUrlEl.textContent.trim();
        if (!url || url.startsWith('Select') || url.startsWith('Could not') || url.startsWith('Generating')) return;
        navigator.clipboard.writeText(url)
          .then(() => { copyLinkBtn.textContent = 'Copied!'; setTimeout(() => { copyLinkBtn.textContent = 'Copy Link'; }, 2000); })
          .catch(() => {});
      });
    }

    // Results toggle with auto-refresh every 10s while open
    let resultsInterval = null;
    function loadResults() {
      if (!currentMeetingId) return;
      api('/voting/results/' + currentMeetingId).then(data => {
        renderResults(data);
        resultsBlock.style.display = 'block';
        resultsBtn.textContent = 'Hide Results';
      }).catch(err => {
        resultsBlock.innerHTML = '<p style="color:var(--tmp-burgundy);font-size:0.85rem;">Could not load results: ' + (err.message || 'unknown error') + '</p>';
        resultsBlock.style.display = 'block';
        resultsBtn.textContent = 'Show Live Results';
      });
    }
    resultsBtn.addEventListener('click', () => {
      if (!currentMeetingId) return;
      const showing = resultsBlock.style.display !== 'none';
      if (showing) {
        resultsBlock.style.display = 'none';
        resultsBtn.textContent = 'Show Live Results';
        clearInterval(resultsInterval);
        resultsInterval = null;
        return;
      }
      resultsBtn.textContent = 'Loading…';
      loadResults();
      resultsInterval = setInterval(loadResults, 10000);
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

    async function renderRateSpeakers(mid) {
      if (!rateSpeakerSection || !speakerFeedbackList) return;
      const meeting = votingMeetings.find(m => String(m.id) === String(mid));
      if (!meeting) { rateSpeakerSection.style.display = 'none'; return; }

      const speakers = (meeting.assignments || []).filter(a =>
        a.role_name && a.role_name.toLowerCase().startsWith('speaker') && a.member_id
      );
      if (!speakers.length) { rateSpeakerSection.style.display = 'none'; return; }

      rateSpeakerSection.style.display = 'block';

      // Load counts (non-blocking)
      const counts = await api(`/speech-feedback/counts/${mid}`).catch(() => ({}));

      speakerFeedbackList.innerHTML = speakers.map(s => {
        const cnt   = counts[s.id] || 0;
        const title = s.speech_title ? `"${esc(s.speech_title)}"` : '';
        const countLabel = cnt > 0 ? `${cnt} response${cnt !== 1 ? 's' : ''}` : 'No feedback yet';
        return `<div class="tmp-speaker-feedback-card" data-sfcard="${s.id}"
          style="border:1px solid var(--tmp-line);border-radius:6px;padding:12px;margin-bottom:10px;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div>
              <strong>${esc(s.member_name || s.display_name || 'TBA')}</strong>
              <span style="color:var(--tmp-muted);font-size:0.85rem;"> · ${esc(s.role_name)}</span>
              ${title ? `<br><span style="font-size:0.82rem;color:var(--tmp-muted);">${title}</span>` : ''}
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex-shrink:0;">
              <button class="tmp-small-button" data-gen-fb-link="${s.id}">&#128279; Copy Link</button>
              <span class="tmp-tag" data-sfcount="${s.id}"
                style="background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:10px;font-size:0.78rem;">${esc(countLabel)}</span>
              <button class="tmp-small-button" data-show-fb="${s.id}">Show Feedback &#9660;</button>
            </div>
          </div>
          <div data-fb-view="${s.id}" style="display:none;margin-top:10px;"></div>
        </div>`;
      }).join('');

      speakerFeedbackList.querySelectorAll('[data-gen-fb-link]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const aid = btn.dataset.genFbLink;
          btn.disabled = true; btn.textContent = 'Copying…';
          try {
            const res = await api(`/speech-feedback/link/${aid}`);
            await navigator.clipboard.writeText(res.url);
            btn.textContent = '✓ Copied!';
            setTimeout(() => { btn.textContent = '&#128279; Copy Link'; btn.disabled = false; }, 2500);
          } catch (err) {
            alert('Failed: ' + (err.message || 'error'));
            btn.textContent = '&#128279; Copy Link'; btn.disabled = false;
          }
        });
      });

      speakerFeedbackList.querySelectorAll('[data-show-fb]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const aid  = btn.dataset.showFb;
          const view = speakerFeedbackList.querySelector(`[data-fb-view="${aid}"]`);
          if (!view) return;
          if (view.style.display !== 'none') {
            view.style.display = 'none'; btn.textContent = 'Show Feedback ▾'; return;
          }
          btn.textContent = 'Loading…';
          try {
            const items = await api(`/speech-feedback/list/${aid}`);
            if (!items.length) {
              view.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.85rem;">No feedback submitted yet.</p>';
            } else {
              view.innerHTML = items.map((f, i) => `
                <div style="padding:8px 10px;background:#f9f9f9;border-radius:4px;${i > 0 ? 'margin-top:6px;' : ''}">
                  <strong style="font-size:0.82rem;">${esc(f.respondent_name || 'Anonymous')}</strong>
                  <span style="font-size:0.75rem;color:var(--tmp-muted);margin-left:6px;">${esc(f.submitted_at)}</span>
                  <p style="margin:4px 0 0;font-size:0.88rem;white-space:pre-wrap;">${esc(f.feedback_text)}</p>
                </div>`).join('');

              // Refresh count badge
              const countEl = speakerFeedbackList.querySelector(`[data-sfcount="${aid}"]`);
              if (countEl) { countEl.textContent = `${items.length} response${items.length !== 1 ? 's' : ''}`; }
            }
            view.style.display = 'block';
            btn.textContent = 'Hide Feedback ▲';
          } catch (err) {
            view.innerHTML = `<p style="color:#c62828;font-size:0.85rem;">Failed to load: ${esc(err.message)}</p>`;
            view.style.display = 'block';
            btn.textContent = 'Show Feedback ▾';
          }
        });
      });
    }
  }

  // ── VPE Meeting Wrap-Up Panel ────────────────────────────────────────────────

  function initWrapUpPanel() {
    const panel = qs('[data-tmp-wrapup-panel]');
    if (!panel) return;

    const meetingSelect      = qs('[data-tmp-wrapup-meeting-select]', panel);
    const wrapupContent      = qs('[data-tmp-wrapup-content]', panel);
    const wrapupBadge        = qs('[data-tmp-wrapup-badge]', panel);
    const roleCountEl        = qs('[data-tmp-role-attendance-count]', panel);
    const refreshRoleBtn     = qs('[data-tmp-refresh-role-attendance]', panel);
    const walkinSearch       = qs('[data-tmp-walkin-search]', panel);
    const walkinDropdown     = qs('[data-tmp-walkin-dropdown]', panel);
    const walkinList         = qs('[data-tmp-walkin-list]', panel);
    const guestNameInput     = qs('[data-tmp-guest-name]', panel);
    const addGuestBtn        = qs('[data-tmp-add-guest-btn]', panel);
    const guestsList         = qs('[data-tmp-guests-list]', panel);
    const completeBtn          = qs('[data-tmp-complete-meeting-btn]', panel);
    const saveStatus           = qs('[data-tmp-wrapup-save-status]', panel);
    const feedbackEmailStatus  = qs('[data-tmp-speaker-feedback-email-status]', panel);

    let currentMeetingId  = null;
    let otherMembers      = [];
    let rolePerformers    = [];
    let declaredWinners   = [];

    // Populate meeting select with recent meetings (most recent first)
    api('/meetings').then(meetings => {
      if (!meetings || !meetings.length) return;
      const today = localDateStr(new Date());
      const isExCom = !!panel.closest('[data-tmp-excom-panel]');
      (isExCom ? meetings.filter(m => m.meeting_date === today) : meetings.slice(0, 8)).forEach(m => {
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

    function renderWrapUp(data) {
      wrapupContent.style.display = 'block';
      const done = data.wrapped_up;
      wrapupBadge.style.display = done ? '' : 'none';
      completeBtn.textContent = done ? '↻ Update Records' : '✓ Complete Meeting';
      completeBtn.style.display = '';
      saveStatus.textContent = '';

      // ── Store role performers for use on save ─────────────────────────────
      rolePerformers = data.role_performers || [];
      const n = rolePerformers.length;
      if (roleCountEl) {
        roleCountEl.textContent = n > 0
          ? n + ' role player' + (n !== 1 ? 's' : '') + ' found in assignments — all marked as attended.'
          : 'No role assignments found for this meeting.';
      }

      // ── Store declared winners for use on save ────────────────────────────
      const allNominees = data.vote_winners || [];
      declaredWinners = allNominees.filter(w => w.is_winner);

      // ── Walk-in members ───────────────────────────────────────────────────
      otherMembers = data.other_members || [];
      walkinList.innerHTML = '';
      (data.walk_ins || []).forEach(m => addWalkinChip(m.member_id, m.full_name));
      refreshWalkinSearch();

      // ── Guests ────────────────────────────────────────────────────────────
      guestsList.innerHTML = '';
      (data.guests || []).forEach(g => appendGuestRow(g.guest_name));
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

    if (refreshRoleBtn) {
      refreshRoleBtn.addEventListener('click', () => {
        if (!currentMeetingId) return;
        refreshRoleBtn.textContent = 'Refreshing…';
        refreshRoleBtn.disabled = true;
        loadWrapUp(currentMeetingId);
        setTimeout(() => { refreshRoleBtn.textContent = '↺ Refresh from Assignments'; refreshRoleBtn.disabled = false; }, 1200);
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

      // Role players: all present, role performed (assignment recorded)
      const attendance = rolePerformers.map(m => ({
        member_id: m.member_id,
        assignment_id: m.assignment_id,
        role_performed: m.assignment_id > 0,
      }));
      // Walk-ins: attended, no role
      walkinList.querySelectorAll('[data-walkin-id]').forEach(chip => {
        attendance.push({ member_id: parseInt(chip.dataset.walkinId, 10), assignment_id: 0, role_performed: false });
      });

      const guests = [];
      guestsList.querySelectorAll('.tmp-wrapup-guest-row').forEach(row => {
        if (row.dataset.guestName) guests.push({ name: row.dataset.guestName });
      });

      // Winners: auto-pulled from declared winners (is_winner = 1 set via Declare Winners)
      const winners = declaredWinners.map(w => ({
        category:     w.category,
        member_id:    w.member_id ? parseInt(w.member_id, 10) : null,
        display_name: w.display_name || '',
        role_name:    w.role_name || '',
        vote_count:   parseInt(w.vote_count, 10) || 0,
        is_tie:       0,
      }));

      completeBtn.disabled = true;

      if (feedbackEmailStatus) { feedbackEmailStatus.textContent = 'Sending emails…'; feedbackEmailStatus.style.color = 'var(--tmp-muted)'; }
      try {
        const res = await api(`/speech-feedback/email-rollup/${currentMeetingId}`, { method: 'POST' });
        if (feedbackEmailStatus) {
          feedbackEmailStatus.textContent = `✓ ${res.sent} email${res.sent !== 1 ? 's' : ''} sent.`;
          feedbackEmailStatus.style.color = '#2e7d32';
          setTimeout(() => { if (feedbackEmailStatus) feedbackEmailStatus.textContent = ''; }, 8000);
        }
      } catch (err) {
        if (feedbackEmailStatus) { feedbackEmailStatus.textContent = 'Email failed: ' + err.message; feedbackEmailStatus.style.color = '#c62828'; }
      }
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

    const meetingLabel   = qs('[data-tmp-saa-meeting-label]', panel);
    const markAllBtn     = qs('[data-tmp-saa-mark-all]', panel);
    const performersList = qs('[data-tmp-saa-performers-list]', panel);
    const walkinSearch   = qs('[data-tmp-saa-walkin-search]', panel);
    const walkinDropdown = qs('[data-tmp-saa-walkin-dropdown]', panel);
    const walkinList     = qs('[data-tmp-saa-walkin-list]', panel);
    const guestName      = qs('[data-tmp-saa-guest-name]', panel);
    const addGuestBtn    = qs('[data-tmp-saa-add-guest]', panel);
    const guestsList     = qs('[data-tmp-saa-guests-list]', panel);
    const saveBtn        = qs('[data-tmp-saa-save]', panel);
    const statusEl       = qs('[data-tmp-saa-status]', panel);

    let meetingId    = null;
    let otherMembers = [];

    function setPerformerAbsent(row, absent) {
      if (absent) {
        row.classList.add('tmp-performer-absent');
        row.dataset.absent = '1';
        row.querySelector('[data-absent-btn]').textContent = '↩ Restore';
      } else {
        row.classList.remove('tmp-performer-absent');
        row.dataset.absent = '0';
        row.querySelector('[data-absent-btn]').textContent = 'Mark Absent';
      }
    }

    function renderPerformers(performers) {
      performersList.innerHTML = '';
      if (!performers || !performers.length) {
        performersList.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.85rem;">No role assignments found for this meeting.</p>';
        return;
      }
      performers.forEach(m => {
        const row = document.createElement('div');
        row.className = 'tmp-wrapup-performer-row';
        row.dataset.memberId     = m.member_id;
        row.dataset.assignmentId = m.assignment_id;
        row.dataset.absent       = '0';
        row.innerHTML = `
          <span class="tmp-wrapup-member-name">${esc(m.full_name)}</span>
          <span class="tmp-wrapup-role-tag">${esc(m.role_name)}</span>
          <button type="button" class="tmp-absent-btn" data-absent-btn>Mark Absent</button>`;
        row.querySelector('[data-absent-btn]').addEventListener('click', () => {
          setPerformerAbsent(row, row.dataset.absent !== '1');
        });
        performersList.appendChild(row);
      });
    }

    function walkinMemberIds() {
      return Array.from(walkinList.querySelectorAll('[data-walkin-id]')).map(el => parseInt(el.dataset.walkinId, 10));
    }

    function addWalkinChip(memberId, fullName) {
      if (walkinList.querySelector('[data-walkin-id="' + memberId + '"]')) return;
      const chip = document.createElement('span');
      chip.className = 'tmp-walkin-chip';
      chip.dataset.walkinId = memberId;
      chip.innerHTML = `${esc(fullName)} <button type="button" aria-label="Remove">✕</button>`;
      chip.querySelector('button').addEventListener('click', () => chip.remove());
      walkinList.appendChild(chip);
    }

    function addGuestRow(name) {
      const row = document.createElement('div');
      row.className = 'tmp-wrapup-guest-row';
      row.dataset.guestName = name;
      row.innerHTML = `<span>👤 ${esc(name)}</span>
        <button class="tmp-link-button" style="color:var(--tmp-burgundy);" aria-label="Remove">✕</button>`;
      row.querySelector('button').addEventListener('click', () => row.remove());
      guestsList.appendChild(row);
    }

    api('/me/saa-meeting').then(data => {
      meetingId    = data.meeting_id;

      otherMembers = data.other_members || [];
      meetingLabel.textContent = data.meeting_date + (data.theme ? ' — ' + data.theme : '');

      renderPerformers(data.role_performers || []);
      (data.walk_ins || []).forEach(m => addWalkinChip(m.member_id, m.full_name));
      (data.guests  || []).forEach(g => addGuestRow(g.guest_name));

      panel.style.display = '';
    }).catch(() => {}); // Not SAA — panel stays hidden


    markAllBtn.addEventListener('click', () => {
      performersList.querySelectorAll('.tmp-wrapup-performer-row').forEach(row => setPerformerAbsent(row, false));
    });

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

    addGuestBtn.addEventListener('click', () => {
      const name = guestName.value.trim();
      if (!name) { guestName.focus(); return; }
      addGuestRow(name);
      guestName.value = '';
      guestName.focus();
    });
    guestName.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addGuestBtn.click(); } });

    saveBtn.addEventListener('click', async () => {
      if (!meetingId) return;
      const attendance = [];
      performersList.querySelectorAll('.tmp-wrapup-performer-row').forEach(row => {
        if (row.dataset.absent === '1') return;
        attendance.push({
          member_id: parseInt(row.dataset.memberId, 10),
          assignment_id: parseInt(row.dataset.assignmentId, 10) || 0,
        });
      });
      walkinList.querySelectorAll('[data-walkin-id]').forEach(chip => {
        attendance.push({ member_id: parseInt(chip.dataset.walkinId, 10), assignment_id: 0 });
      });
      const guests = Array.from(guestsList.querySelectorAll('.tmp-wrapup-guest-row'))
        .map(r => ({ name: r.dataset.guestName })).filter(g => g.name);

      saveBtn.disabled = true;
      statusEl.textContent = 'Saving…';
      statusEl.style.color = 'var(--tmp-muted)';
      try {
        await api('/meetings/' + meetingId + '/saa-attendance', {
          method: 'POST',
          body: JSON.stringify({ attendance, guests }),
        });
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

  // ── SAA Voting & Poll Panel (member dashboard) ────────────────────────────────

  function initSAAPollPanel() {
    const panel = qs('[data-tmp-saa-voting-panel]');
    if (!panel) return;

    const votingLabel       = qs('[data-tmp-saa-voting-label]', panel);
    const ttNameInput       = qs('[data-tmp-saa-tt-name]', panel);
    const ttGuestWrap       = qs('[data-tmp-saa-tt-guest-wrap]', panel);
    const ttSelect          = qs('[data-tmp-saa-tt-select]', panel);
    const ttAddBtn          = qs('[data-tmp-saa-tt-add]', panel);
    const ttList            = qs('[data-tmp-saa-tt-list]', panel);
    const nomSummary        = qs('[data-tmp-saa-nominees-summary]', panel);
    const refreshBtn        = qs('[data-tmp-saa-refresh-btn]', panel);
    const resultsBtn        = qs('[data-tmp-saa-results-btn]', panel);
    const resultsBlock      = qs('[data-tmp-saa-results]', panel);
    const openPollBtn       = qs('[data-tmp-saa-open-poll]', panel);
    const pollStatusEl      = qs('[data-tmp-saa-poll-status]', panel);
    const declareWinnersBtn = qs('[data-tmp-saa-declare-winners-btn]', panel);
    const genLinkBtn        = qs('[data-tmp-saa-gen-link]', panel);
    const linkDisplay       = qs('[data-tmp-saa-link-display]', panel);
    const linkUrlEl         = qs('[data-tmp-saa-link-url]', panel);
    const copyLinkBtn       = qs('[data-tmp-saa-copy-link]', panel);
    const linkExpiryEl      = qs('[data-tmp-saa-link-expiry]', panel);

    let meetingId  = null;
    let pollIsOpen = false;
    let pollTimer  = null;

    function loadNominees() {
      if (!meetingId) return;
      api('/voting/nominees/' + meetingId).then(data => {
        renderTTSpeakers(data.nominees.table_topics || []);
        renderNomineesSummary(data.nominees);
        setPollOpenUI(!!data.poll_open);
      }).catch(() => {});
    }

    function setPollOpenUI(isOpen) {
      pollIsOpen = isOpen;
      openPollBtn.textContent  = isOpen ? 'Close Moment of Glory' : 'Moment of Glory';
      openPollBtn.className    = 'tmp-button ' + (isOpen ? 'tmp-secondary' : 'tmp-primary');
      pollStatusEl.textContent = isOpen ? '🟢 Poll is OPEN — members can vote' : '⚪ Poll is closed';
    }

    function renderTTSpeakers(speakers) {
      if (!speakers.length) {
        ttList.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.88rem;">No TT speakers added yet.</p>';
        return;
      }
      ttList.innerHTML = '<ol class="tmp-tt-speaker-list">' +
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
      nomSummary.innerHTML = Object.entries(cats).map(([cat, label]) => {
        const items = nominees[cat] || [];
        return `<div class="tmp-vote-cat-summary">
          <strong>${label}</strong>
          <span>${items.map(n => esc(n.display_name) + ' (' + esc(n.role_name) + ')').join(', ') || '<em>—</em>'}</span>
        </div>`;
      }).join('');
    }

    function renderResults(data) {
      const cats = {
        main_role: 'Best Main Role', aux_role: 'Best Auxiliary Role',
        table_topics: 'Best Table Topics Speaker', speaker: 'Best Speaker', evaluator: 'Best Evaluator',
      };
      const results   = data.results || data;
      const total     = data.total_voters ?? '';
      const totalLine = total !== '' ? `<p style="color:var(--tmp-muted);font-size:0.85rem;">${total} voter${total !== 1 ? 's' : ''} so far</p>` : '';
      resultsBlock.innerHTML = totalLine + Object.entries(cats).map(([cat, label]) => {
        const items = results[cat] || [];
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

    api('/members').then(members => {
      if (!members) return;
      const guestOpt = document.createElement('option');
      guestOpt.value = '__guest__';
      guestOpt.textContent = '✎ Guest speaker (enter name)…';
      ttSelect.appendChild(guestOpt);
      members.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.full_name;
        ttSelect.appendChild(opt);
      });
    }).catch(() => {});

    ttSelect.addEventListener('change', () => {
      const isGuest = ttSelect.value === '__guest__';
      ttGuestWrap.style.display = isGuest ? 'block' : 'none';
      if (isGuest) ttNameInput.focus();
      else ttNameInput.value = '';
    });

    ttAddBtn.addEventListener('click', () => {
      if (!meetingId) return;
      const sel = ttSelect.value;
      let name, memberId;
      if (sel === '__guest__') {
        name     = ttNameInput.value.trim();
        memberId = null;
        if (!name) { ttNameInput.focus(); return; }
      } else if (sel) {
        name     = ttSelect.options[ttSelect.selectedIndex].textContent;
        memberId = sel;
      } else {
        ttSelect.focus();
        return;
      }
      ttAddBtn.disabled = true;
      api('/voting/tt-speaker', {
        method: 'POST',
        body: JSON.stringify({ meeting_id: meetingId, display_name: name, member_id: memberId }),
      }).then(data => {
        ttNameInput.value         = '';
        ttSelect.value            = '';
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

    refreshBtn.addEventListener('click', () => {
      if (!meetingId) return;
      refreshBtn.disabled = true;
      refreshBtn.textContent = 'Refreshing…';
      api('/voting/refresh-nominees/' + meetingId, { method: 'POST' })
        .then(data => {
          renderTTSpeakers(data.nominees.table_topics || []);
          renderNomineesSummary(data.nominees);
        })
        .catch(err => alert('Refresh failed: ' + err.message))
        .finally(() => { refreshBtn.disabled = false; refreshBtn.textContent = '↻ Refresh from Assignments'; });
    });

    openPollBtn.addEventListener('click', () => {
      if (!meetingId) return;
      openPollBtn.disabled = true;
      api('/voting/open-poll/' + meetingId, {
        method: 'POST',
        body: JSON.stringify({ open: !pollIsOpen }),
      }).then(data => {
        setPollOpenUI(!!data.poll_open);
      }).catch(err => alert('Poll update failed: ' + err.message))
        .finally(() => { openPollBtn.disabled = false; });
    });

    if (declareWinnersBtn) {
      declareWinnersBtn.addEventListener('click', () => {
        if (!meetingId) return;
        if (!confirm('Declare winners now? This will mark the top vote-getter in each category.')) return;
        declareWinnersBtn.disabled = true;
        declareWinnersBtn.textContent = 'Calculating…';
        api('/voting/declare-winners/' + meetingId, { method: 'POST' })
          .then(data => {
            renderResults(data);
            if (resultsBlock) resultsBlock.style.display = 'block';
            if (resultsBtn)   resultsBtn.textContent = 'Hide Results';
          }).catch(err => alert('Declare winners failed: ' + err.message))
          .finally(() => { declareWinnersBtn.disabled = false; declareWinnersBtn.textContent = '🏆 Declare Winners'; });
      });
    }

    if (resultsBtn) {
      resultsBtn.addEventListener('click', () => {
        if (!meetingId) return;
        const showing = resultsBlock.style.display !== 'none';
        if (showing) {
          resultsBlock.style.display = 'none';
          resultsBtn.textContent = 'Show Live Results';
          return;
        }
        resultsBtn.textContent = 'Loading…';
        api('/voting/results/' + meetingId).then(data => {
          renderResults(data);
          resultsBlock.style.display = 'block';
          resultsBtn.textContent = 'Hide Results';
        }).catch(err => {
          resultsBlock.innerHTML = '<p style="color:var(--tmp-burgundy);font-size:0.85rem;">Could not load results: ' + (err.message || 'unknown error') + '</p>';
          resultsBlock.style.display = 'block';
          resultsBtn.textContent = 'Show Live Results';
        });
      });
    }

    if (genLinkBtn) {
      genLinkBtn.addEventListener('click', () => {
        genLinkBtn.disabled = true;
        genLinkBtn.textContent = 'Generating…';
        api('/voting/token', { method: 'POST' }).then(data => {
          if (linkUrlEl)    linkUrlEl.textContent = data.url;
          if (linkExpiryEl) linkExpiryEl.textContent = 'Expires: ' + data.expires_at + ' UTC';
          if (linkDisplay)  linkDisplay.style.display = 'block';
          genLinkBtn.textContent = 'Regenerate';
        }).catch(err => {
          genLinkBtn.textContent = 'Generate Link';
          alert('Could not generate link: ' + (err.message || 'unknown error'));
        }).finally(() => { genLinkBtn.disabled = false; });
      });
    }

    if (copyLinkBtn && linkUrlEl) {
      copyLinkBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(linkUrlEl.textContent)
          .then(() => { copyLinkBtn.textContent = 'Copied!'; setTimeout(() => { copyLinkBtn.textContent = 'Copy'; }, 2000); })
          .catch(() => {});
      });
    }

    api('/me/saa-meeting').then(data => {
      meetingId = data.meeting_id;
      if (votingLabel) votingLabel.textContent = data.meeting_date + (data.theme ? ' — ' + data.theme : '');
      panel.style.display = '';
      loadNominees();
      pollTimer = setInterval(loadNominees, 30000);
    }).catch(() => {}); // Not SAA — panel stays hidden
  }

  // ── Member voting panel (auto-polls /voting/active every 15 s) ───────────────

  function initMemberVoting() {
    const panel = qs('[data-tmp-member-vote-panel]');
    if (!panel) return;

    const label    = qs('[data-tmp-member-vote-label]', panel);
    const body     = qs('[data-tmp-member-vote-body]', panel);
    const statusEl = qs('[data-tmp-member-vote-status]', panel);

    let voterToken = null;
    try {
      voterToken = localStorage.getItem('tmp_voter_token');
      if (!voterToken) {
        voterToken = (crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now());
        localStorage.setItem('tmp_voter_token', voterToken);
      }
    } catch (_) { voterToken = 'guest-' + Date.now(); }

    let activeMeetingId = null;
    const voted         = {};

    const CAT_LABELS = {
      main_role: 'Best Main Role', aux_role: 'Best Auxiliary Role',
      table_topics: 'Best Table Topics', speaker: 'Best Speaker', evaluator: 'Best Evaluator',
    };

    function checkPoll() {
      api('/voting/active').then(data => {
        if (!data.poll_open) {
          panel.style.display = 'none';
          return;
        }
        activeMeetingId = data.meeting_id;
        if (label) label.textContent = (data.meeting_date || '') + (data.theme ? ' — ' + data.theme : '');
        renderVoteForm(data.nominees);
        panel.style.display = '';
      }).catch(() => {});
    }

    function renderVoteForm(nominees) {
      const rows = Object.entries(CAT_LABELS).map(([cat, catLabel]) => {
        const items = nominees[cat] || [];
        if (!items.length) return '';
        const hasVoted = !!voted[cat];
        return `<div class="tmp-vote-cat" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--tmp-line);">
          <p class="tmp-eyebrow" style="margin:0 0 8px;">${catLabel}</p>
          <div class="tmp-vote-nominees">
            ${items.map(n => `
              <button type="button" class="tmp-vote-btn${voted[cat] === n.id ? ' tmp-vote-btn--voted' : ''}"
                data-nominee="${n.id}" data-cat="${cat}"
                ${hasVoted ? 'disabled' : ''}>
                <span class="tmp-vote-btn-name">${esc(n.display_name)}</span>
                <span class="tmp-vote-btn-role">${esc(n.role_name)}</span>
                ${voted[cat] === n.id ? '<span class="tmp-vote-check">✓</span>' : ''}
              </button>`).join('')}
          </div>
        </div>`;
      }).filter(Boolean);
      if (!rows.length) { body.innerHTML = '<p style="color:var(--tmp-muted);">No nominees yet — check back soon.</p>'; return; }
      body.innerHTML = rows.join('');
      body.querySelectorAll('.tmp-vote-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => castVote(parseInt(btn.dataset.nominee), btn.dataset.cat));
      });
    }

    function castVote(nomineeId, category) {
      if (!activeMeetingId) return;
      api('/voting/vote', {
        method: 'POST',
        body: JSON.stringify({ meeting_id: activeMeetingId, nominee_id: nomineeId, voter_token: voterToken }),
      }).then(() => {
        voted[category] = String(nomineeId);
        statusEl.textContent = '';
        checkPoll();
      }).catch(err => {
        if (err.code === 'already_voted') {
          voted[category] = String(nomineeId); checkPoll();
        } else {
          statusEl.textContent = 'Vote failed — ' + (err.message || 'try again');
          statusEl.style.color = 'var(--tmp-burgundy)';
        }
      });
    }

    checkPoll();
    setInterval(checkPoll, 15000);
  }

  // ── Standalone voting page (/tm_voting shortcode) ─────────────────────────────

  function initVotingPage() {
    const page = qs('[data-tmp-vote-page]');
    if (!page) return;

    const title    = qs('[data-tmp-vote-page-title]', page);
    const body     = qs('[data-tmp-vote-page-body]', page);
    const statusEl = qs('[data-tmp-vote-page-status]', page);

    let voterToken = null;
    try {
      voterToken = localStorage.getItem('tmp_voter_token');
      if (!voterToken) {
        voterToken = (crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now());
        localStorage.setItem('tmp_voter_token', voterToken);
      }
    } catch (_) { voterToken = 'guest-' + Date.now(); }

    const activeMeetingId = parseInt(page.dataset.tmpMeetingId, 10) || null;
    if (!activeMeetingId) return;

    const voted = {};

    const CAT_LABELS = {
      main_role: 'Best Main Role', aux_role: 'Best Auxiliary Role',
      table_topics: 'Best Table Topics', speaker: 'Best Speaker', evaluator: 'Best Evaluator',
    };

    function checkPoll() {
      api('/voting/nominees/' + activeMeetingId).then(data => {
        if (!data.poll_open) {
          body.innerHTML = '<div style="text-align:center;padding:40px 20px;"><p style="color:var(--tmp-muted);font-size:1rem;">The poll is not open yet.</p><p style="color:var(--tmp-muted);font-size:0.88rem;">This page checks automatically — no need to refresh.</p></div>';
          return;
        }
        if (title) title.textContent = data.theme ? 'Vote — ' + data.theme : 'Cast Your Vote';
        renderVoteForm(data.nominees);
      }).catch(() => {
        body.innerHTML = '<p style="color:var(--tmp-muted);">Could not connect to the voting system. Please refresh.</p>';
      });
    }

    function renderVoteForm(nominees) {
      const rows = Object.entries(CAT_LABELS).map(([cat, catLabel]) => {
        const items = nominees[cat] || [];
        if (!items.length) return '';
        const hasVoted = !!voted[cat];
        return `<div class="tmp-vote-cat" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--tmp-line);">
          <p class="tmp-eyebrow" style="margin:0 0 10px;">${catLabel}</p>
          <div class="tmp-vote-nominees">
            ${items.map(n => `
              <button type="button" class="tmp-vote-btn${voted[cat] === n.id ? ' tmp-vote-btn--voted' : ''}"
                data-nominee="${n.id}" data-cat="${cat}"
                ${hasVoted ? 'disabled' : ''}>
                <span class="tmp-vote-btn-name">${esc(n.display_name)}</span>
                <span class="tmp-vote-btn-role">${esc(n.role_name)}</span>
                ${voted[cat] === n.id ? '<span class="tmp-vote-check">✓ Your vote</span>' : ''}
              </button>`).join('')}
          </div>
        </div>`;
      }).filter(Boolean);
      if (!rows.length) { body.innerHTML = '<p style="color:var(--tmp-muted);">No nominees found for this meeting.</p>'; return; }
      body.innerHTML = rows.join('');
      body.querySelectorAll('.tmp-vote-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', () => castVote(parseInt(btn.dataset.nominee), btn.dataset.cat));
      });
    }

    function castVote(nomineeId, category) {
      if (!activeMeetingId) return;
      api('/voting/vote', {
        method: 'POST',
        body: JSON.stringify({ meeting_id: activeMeetingId, nominee_id: nomineeId, voter_token: voterToken }),
      }).then(() => {
        voted[category] = String(nomineeId);
        statusEl.textContent = '';
        checkPoll();
      }).catch(err => {
        if (err.code === 'already_voted') {
          voted[category] = String(nomineeId); checkPoll();
        } else {
          statusEl.textContent = 'Could not record vote — ' + (err.message || 'please try again');
          statusEl.style.color = 'var(--tmp-burgundy)';
        }
      });
    }

    checkPoll();
    setInterval(checkPoll, 15000);
  }

  // ===========================================================================
  // LEVEL UP QUEUE (VPE)
  // ===========================================================================

  async function initLevelUpQueue() {
    const root = qs("[data-tmp-vpe]");
    if (!root) return;
    const listEl    = qs("[data-tmp-levelup-request-list]", root);
    const countEl   = qs("[data-tmp-levelup-pending-count]", root);
    const toggleBtn = qs("[data-tmp-levelup-toggle]", root);
    if (!listEl) return;

    const openSection = () => {
      listEl.style.display = "";
      if (toggleBtn) { toggleBtn.setAttribute("aria-expanded", "true"); const ch = toggleBtn.querySelector(".tmp-chevron"); if (ch) ch.style.transform = "rotate(90deg)"; }
    };
    toggleBtn?.addEventListener("click", () => {
      const open = listEl.style.display !== "none";
      listEl.style.display = open ? "none" : "";
      toggleBtn.setAttribute("aria-expanded", String(!open));
      const ch = toggleBtn.querySelector(".tmp-chevron");
      if (ch) ch.style.transform = open ? "" : "rotate(90deg)";
    });

    const requests = await api("/vpe/level-up-requests").catch(() => []);
    root._pendingLevelUpCount = (requests || []).length;
    updateMembersTabBadge(root);

    if (!requests || requests.length === 0) {
      if (countEl) countEl.style.display = "none";
      return;
    }

    if (countEl) { countEl.textContent = requests.length; countEl.style.display = "inline-flex"; }
    openSection();

    listEl.innerHTML = requests.map((req) => {
      const ev      = req.evidence || {};
      const sp      = ev.speech_progress;
      const gaps    = ev.role_gaps || [];
      const unmet   = gaps.filter((g) => !g.met);
      const complete = req.system_verdict === "complete";
      const verdictBadge = complete
        ? `<span class="tmp-badge" style="background:#e8f5e9;color:#2e7d32;">✅ Evidence complete</span>`
        : `<span class="tmp-badge" style="background:#fff3e0;color:#e65100;">⚠ Evidence incomplete</span>`;

      const spLine = sp
        ? `<p style="margin:4px 0;font-size:0.85rem;">Speeches: ${sp.done}/${sp.needed}${sp.met ? " ✓" : " — needs " + (sp.needed - sp.done) + " more"}</p>`
        : "";
      const unmetLines = unmet.map((g) => `<p style="margin:4px 0;font-size:0.85rem;color:#e65100;">• Club role: ${esc(g.label)} not completed</p>`).join("");
      const memberNote = req.member_note ? `<p style="margin:6px 0;font-size:0.85rem;font-style:italic;">"${esc(req.member_note)}"</p>` : "";

      return `<div class="tmp-levelup-req-card" data-req-id="${req.id}" style="border:1px solid var(--tmp-line);border-radius:6px;padding:14px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
          <div>
            <strong>${esc(req.member_name)}</strong>
            <span style="color:var(--tmp-muted);font-size:0.88rem;margin-left:8px;">Level ${req.from_level} → Level ${req.to_level}</span>
            <span style="color:var(--tmp-muted);font-size:0.8rem;margin-left:8px;">${esc(req.created_at?.split(" ")[0] || "")}</span>
          </div>
          ${verdictBadge}
        </div>
        <div style="margin:10px 0 0;padding:10px;background:#f9f9f9;border-radius:4px;font-size:0.88rem;">
          ${spLine}${unmetLines}${memberNote}
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <button class="tmp-button tmp-primary tmp-small-button" data-approve-req="${req.id}">Approve</button>
          <button class="tmp-button tmp-secondary tmp-small-button" data-deny-req="${req.id}">Deny</button>
          <input type="text" placeholder="VPE note (optional)" data-vpe-note="${req.id}"
            style="flex:1;min-width:160px;padding:6px 10px;border:1px solid var(--tmp-line);border-radius:4px;font-size:0.85rem;" />
        </div>
      </div>`;
    }).join("");

    listEl.addEventListener("click", async (e) => {
      const approveBtn = e.target.closest("[data-approve-req]");
      const denyBtn    = e.target.closest("[data-deny-req]");
      const btn        = approveBtn || denyBtn;
      if (!btn || btn._pending) return;

      const reqId  = btn.dataset.approveReq || btn.dataset.denyReq;
      const action = approveBtn ? "approve" : "deny";
      const noteEl = listEl.querySelector(`[data-vpe-note="${reqId}"]`);
      const note   = noteEl ? noteEl.value : "";

      if (action === "deny" && !note.trim()) {
        alert("Please add a note explaining the denial.");
        noteEl?.focus();
        return;
      }

      btn._pending = true;
      btn.disabled = true;
      btn.textContent = "Saving…";
      try {
        await api(`/vpe/level-up-requests/${reqId}/review`, {
          method: "POST",
          body: JSON.stringify({ action, note }),
        });
        // Remove card and refresh
        listEl.querySelector(`[data-req-id="${reqId}"]`)?.remove();
        const remaining = listEl.querySelectorAll("[data-req-id]").length;
        if (countEl) { countEl.textContent = remaining; if (!remaining) countEl.style.display = "none"; }
        if (!remaining) listEl.innerHTML = "<p style=\"color:var(--tmp-muted);font-size:0.88rem;\">No pending requests.</p>";
      } catch (err) {
        alert("Error: " + err.message);
        btn._pending = false;
        btn.disabled = false;
        btn.textContent = approveBtn ? "Approve" : "Deny";
      }
    });
  }

  // ===========================================================================
  // LEVEL PROGRESS PANEL (VPE)
  // ===========================================================================

  async function initLevelProgressPanel() {
    const root = qs("[data-tmp-vpe]");
    if (!root) return;
    const rowsEl   = qs("[data-tmp-vpe-lp-rows]", root);
    const readyEl  = qs("[data-tmp-vpe-ready-count]", root);
    const pFilter  = qs("[data-tmp-vpe-lp-pathway]", root);
    const lFilter  = qs("[data-tmp-vpe-lp-level]", root);
    const sFilter  = qs("[data-tmp-vpe-lp-status]", root);
    if (!rowsEl) return;

    // Reset filter selects to "all" on every page load — prevent stale browser state
    if (pFilter) pFilter.value = "all";
    if (lFilter) lFilter.value = "all";
    if (sFilter) sFilter.value = "all";

    let allData = null;
    let expandedId = null;

    const trafficLabel = (t) => t === "ready" ? "🟢 Ready" : t === "stuck" ? "🔴 Stuck" : "🟡 In Progress";
    const trafficColor = (t) => t === "ready" ? "#e8f5e9;color:#2e7d32" : t === "stuck" ? "#ffebee;color:#c62828" : "#fff3e0;color:#e65100";

    const populatePathwayFilter = (data) => {
      if (!pFilter) return;
      const pathways = [...new Set(data.map((m) => m.pathway).filter(Boolean))].sort();
      Array.from(pFilter.options).filter((o) => o.value !== "all").forEach((o) => o.remove());
      pathways.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p;
        opt.textContent = p;
        pFilter.appendChild(opt);
      });
    };

    const render = () => {
      if (!allData) return;
      const pw = pFilter?.value || "all";
      const lv = lFilter?.value || "all";
      const st = sFilter?.value || "all";

      const filtered = allData.filter((m) =>
        (pw === "all" || m.pathway === pw) &&
        (lv === "all" || String(m.level) === lv) &&
        (st === "all" || m.traffic_light === st)
      );

      const readyCount = allData.filter((m) => m.traffic_light === "ready").length;
      if (readyEl) readyEl.textContent = readyCount ? `${readyCount} ready to advance` : "";

      if (!filtered.length) {
        const totalMsg = allData.length > 0 ? ` — ${allData.length} member${allData.length !== 1 ? "s" : ""} total, try clearing filters` : "";
        rowsEl.innerHTML = `<tr><td colspan="6" style="color:var(--tmp-muted);text-align:center;padding:16px;">No members match this filter${totalMsg}.</td></tr>`;
        return;
      }

      rowsEl.innerHTML = filtered.map((m) => {
        const spCell  = m.speech_done !== null ? `${m.speech_done}/${m.speech_needed}` : "—";
        const roleCell = `${m.roles_total - m.roles_unmet}/${m.roles_total}`;
        const tBg     = trafficColor(m.traffic_light);
        return `<tr data-lp-member="${m.member_id}" style="cursor:pointer;">
          <td><strong>${esc(m.name)}</strong><br><small style="color:var(--tmp-muted);">${esc(m.pathway)}</small></td>
          <td>Level ${m.level}</td>
          <td>${spCell} speeches</td>
          <td>${roleCell} roles</td>
          <td><span class="tmp-badge" style="background:${tBg};">${trafficLabel(m.traffic_light)}</span></td>
          <td><button class="tmp-small-button" data-expand-lp="${m.member_id}">Details</button></td>
        </tr>
        <tr data-lp-detail="${m.member_id}" style="display:none;">
          <td colspan="6" style="padding:0;"></td>
        </tr>`;
      }).join("");
    };

    const loadDetail = async (memberId, inPlace = false) => {
      const detailRow = rowsEl.querySelector(`[data-lp-detail="${memberId}"]`);
      if (!detailRow) return;
      const td = detailRow.querySelector("td");
      if (!td) return;

      // Toggle close (only when not doing an in-place refresh)
      if (!inPlace && expandedId === memberId) {
        detailRow.style.display = "none";
        expandedId = null;
        return;
      }
      if (!inPlace && expandedId) {
        rowsEl.querySelector(`[data-lp-detail="${expandedId}"]`)?.style?.setProperty("display", "none");
      }
      expandedId = memberId;
      detailRow.style.display = "";
      if (!inPlace) {
        td.innerHTML = `<div style="padding:12px;background:#f9f9f9;border-top:1px solid var(--tmp-line);">Loading…</div>`;
      } else {
        td.style.opacity = "0.4";
      }

      try {
        const data = await api(`/members/${memberId}/level-status`);
        const sp   = data.speech_progress;
        const lvl  = data.level;

        const chips = sp ? (sp.speeches || []).map((s) =>
          `<span class="tmp-speech-chip">${esc(s.role_name)} <small>${esc(s.meeting_date)}</small></span>`
        ).join("") : "";

        const spHtml = sp ? `
          <div style="margin-bottom:12px;">
            <p style="font-weight:600;font-size:0.88rem;margin:0 0 6px;">Speeches (Level ${lvl})</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">${chips || "<span style=\"color:var(--tmp-muted)\">None recorded yet</span>"}</div>
            <p style="font-size:0.85rem;color:var(--tmp-muted);margin:0;">${sp.done}/${sp.needed} done${sp.offset ? ` (includes ${sp.offset} pre-system)` : ""}</p>
            <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
              <span style="font-size:0.85rem;">Pre-system offset:</span>
              <button class="tmp-small-button" data-offset-dec="${memberId}" data-offset-lvl="${lvl}">−</button>
              <span data-offset-val="${memberId}">${sp.offset}</span>
              <button class="tmp-small-button" data-offset-inc="${memberId}" data-offset-lvl="${lvl}">+</button>
            </div>
          </div>` : "";

        const rolesHtml = `<div style="margin-bottom:12px;">
          <p style="font-weight:600;font-size:0.88rem;margin:0 0 6px;">Club Roles (Level ${lvl}) <small style="font-weight:400;color:var(--muted);">— auto-detected from meeting history</small></p>
          ${(data.role_gaps || []).map((g) => `
            <div style="font-size:0.88rem;display:flex;align-items:center;gap:6px;margin:3px 0;">
              <span style="color:${g.met ? "var(--teal)" : "var(--muted)"};">${g.met ? "✅" : "○"}</span>
              <span style="color:${g.met ? "var(--ink)" : "var(--muted)"};">${esc(g.label)}</span>
              ${g.met ? "" : `<span style="font-size:0.75rem;color:var(--muted);">(not yet completed)</span>`}
            </div>`).join("")}
        </div>`;

        td.style.opacity = "";
        td.innerHTML = `<div style="padding:14px;background:#f9f9f9;border-top:1px solid var(--tmp-line);">
          ${spHtml}${rolesHtml}
          <p style="font-size:0.88rem;font-weight:600;color:${data.ready_to_advance ? "var(--tmp-teal)" : "#e65100"};">
            ${data.ready_to_advance ? "🟢 Ready to advance" : "🟡 " + (data.verdict_detail || []).join(" · ")}
          </p>
        </div>`;
      } catch (err) {
        td.style.opacity = "";
        td.innerHTML = `<div style="padding:12px;color:var(--tmp-burgundy);">Could not load: ${esc(err.message)}</div>`;
      }
    };

    rowsEl.addEventListener("click", async (e) => {
      const expandBtn = e.target.closest("[data-expand-lp]");
      const incBtn    = e.target.closest("[data-offset-inc]");
      const decBtn    = e.target.closest("[data-offset-dec]");

      if (expandBtn) {
        await loadDetail(expandBtn.dataset.expandLp);
        return;
      }

      if (incBtn || decBtn) {
        const mId   = (incBtn || decBtn).dataset[incBtn ? "offsetInc" : "offsetDec"];
        const lvl   = (incBtn || decBtn).dataset.offsetLvl;
        const valEl = rowsEl.querySelector(`[data-offset-val="${mId}"]`);
        let current = parseInt(valEl?.textContent || "0", 10);
        current = Math.max(0, current + (incBtn ? 1 : -1));
        if (valEl) valEl.textContent = current;
        try {
          await api(`/members/${mId}/pathway-offset`, {
            method: "POST",
            body: JSON.stringify({ level: parseInt(lvl, 10), offset: current }),
          });
          // Reload detail content in-place (inPlace=true skips Loading… replacement and toggle-close logic).
          await loadDetail(mId, true);
          // Quietly refresh summary row speech count in the background.
          api("/vpe/members/level-summary").then((fresh) => {
            if (!fresh) return;
            allData = fresh;
            populatePathwayFilter(allData);
            const m   = fresh.find((x) => String(x.member_id) === String(mId));
            const row = rowsEl.querySelector(`[data-lp-member="${mId}"]`);
            if (m && row && row.cells[2]) {
              row.cells[2].textContent = m.speech_done !== null
                ? `${m.speech_done}/${m.speech_needed} speeches`
                : "—";
            }
          }).catch(() => {});
        } catch (err) {
          alert("Could not save offset: " + err.message);
        }
      }
    });

    [pFilter, lFilter, sFilter].forEach((el) => el?.addEventListener("change", render));

    try {
      allData = await api("/vpe/members/level-summary");
      populatePathwayFilter(allData);
    } catch (err) {
      rowsEl.innerHTML = `<tr><td colspan="6" style="color:var(--burgundy);text-align:center;padding:16px;">Could not load level data: ${esc(err.message)}</td></tr>`;
      return;
    }
    render();
  }

  // ── Member: Rate Your Mentor ────────────────────────────────────────────────

  function initMentorRating() {
    const panel = qs('[data-tmp-mentor-rating-panel]');
    if (!panel) return;

    // Compute current-month period
    const now    = new Date();
    const pStart = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const pEnd   = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

    api(`/mentor-ratings?period_start=${pStart}&period_end=${pEnd}`).then((data) => {
      if (!data.has_mentor) return; // no mentor — keep panel hidden

      panel.style.display = '';
      const desc     = qs('[data-tmp-mentor-rating-desc]', panel);
      const form     = qs('[data-tmp-mentor-rating-form]', panel);
      const submitted = qs('[data-tmp-mentor-rating-submitted]', panel);
      const doneMsg  = qs('[data-tmp-mentor-rating-done-msg]', panel);
      const statusEl = qs('[data-tmp-mentor-rating-status]', panel);

      if (desc) desc.textContent = `Rate your mentor ${esc(data.mentor_name || '')} for this month.`;

      if (data.existing_rating) {
        submitted.style.display = '';
        if (doneMsg) doneMsg.textContent = `You rated your mentor ${data.existing_rating.rating}/5 this month. Thank you!`;
      } else {
        form.style.display = '';

        // Star picker highlight
        const stars = panel.querySelectorAll('[data-star]');
        let selectedRating = 0;
        stars.forEach((star) => {
          star.addEventListener('mouseover', () => highlightStars(stars, +star.dataset.star));
          star.addEventListener('mouseout',  () => highlightStars(stars, selectedRating));
          star.addEventListener('click', () => {
            selectedRating = +star.dataset.star;
            star.querySelector('input').checked = true;
            highlightStars(stars, selectedRating);
          });
        });

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const fd = new FormData(form);
          const rating = parseInt(fd.get('rating'), 10);
          if (!rating) { if (statusEl) statusEl.textContent = 'Please select a star rating.'; return; }
          if (statusEl) statusEl.textContent = 'Saving…';
          try {
            await api('/mentor-ratings', {
              method: 'POST',
              body: JSON.stringify({ rating, feedback: fd.get('feedback'), period_start: pStart, period_end: pEnd }),
            });
            form.style.display = 'none';
            submitted.style.display = '';
            if (doneMsg) doneMsg.textContent = `You rated your mentor ${rating}/5 this month. Thank you!`;
          } catch (err) {
            if (statusEl) statusEl.textContent = 'Error saving rating. Please try again.';
          }
        });
      }
    }).catch(() => {});
  }

  function highlightStars(stars, count) {
    stars.forEach((s) => { s.style.color = +s.dataset.star <= count ? '#f0b429' : '#ccc'; });
  }

  // ── Member: Recognition history ─────────────────────────────────────────────

  async function initMyRecognition() {
    const panel = qs('[data-tmp-my-recognition]');
    if (!panel) return;
    const listEl = qs('[data-tmp-my-recognition-list]', panel);
    if (!listEl) return;

    try {
      const me = await api('/me');
      if (!me || !me.id) return;

      const history = await api(`/recognition/awards?member_id=${me.id}&limit=20`).catch(() => []);
      const mine = Array.isArray(history) ? history.filter((a) => +a.member_id === +me.id) : [];
      if (!mine.length) return;

      panel.style.display = '';
      listEl.innerHTML = mine.map((a) => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--tmp-line);">
          <div>
            <strong>${esc(a.period_type === 'quarter' ? 'Toastmaster of the Quarter' : 'Toastmaster of the Month')}</strong>
            <span style="color:var(--tmp-muted);font-size:0.85rem;margin-left:8px;">${esc(a.period_label)}</span>
          </div>
          <span style="color:var(--tmp-teal);font-weight:700;">${parseFloat(a.score).toFixed(1)} pts</span>
        </div>
      `).join('');
    } catch (_) {}
  }

  // ── VPE: Recognition panel ──────────────────────────────────────────────────

  function initRecognitionPanel() {
    const panel = qs('[data-tmp-recognition-panel]');
    if (!panel) return;

    const typeEl    = qs('[data-tmp-recog-type]', panel);
    const startEl   = qs('[data-tmp-recog-start]', panel);
    const endEl     = qs('[data-tmp-recog-end]', panel);
    const computeBtn = qs('[data-tmp-recog-compute]', panel);
    const scoresEl  = qs('[data-tmp-recog-scores]', panel);
    const awardsEl  = qs('[data-tmp-recog-awards-list]', panel);

    // Default period: current month
    const now = new Date();
    const m   = String(now.getMonth() + 1).padStart(2, '0');
    const y   = now.getFullYear();
    const last = new Date(y, now.getMonth() + 1, 0).getDate();
    if (startEl) startEl.value = `${y}-${m}-01`;
    if (endEl)   endEl.value   = `${y}-${m}-${String(last).padStart(2, '0')}`;

    // Auto-set end date when type changes
    typeEl?.addEventListener('change', () => {
      if (!startEl?.value) return;
      const d = new Date(startEl.value + 'T00:00:00');
      if (typeEl.value === 'quarter') {
        const qEnd = new Date(d.getFullYear(), Math.floor(d.getMonth() / 3) * 3 + 3, 0);
        if (endEl) endEl.value = localDateStr(qEnd);
      } else {
        const mEnd = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        if (endEl) endEl.value = localDateStr(mEnd);
      }
    });

    computeBtn?.addEventListener('click', async () => {
      const pStart = startEl?.value;
      const pEnd   = endEl?.value;
      if (!pStart || !pEnd) { alert('Please set period start and end dates.'); return; }
      if (scoresEl) scoresEl.innerHTML = '<p style="color:var(--tmp-muted)">Computing…</p>';

      try {
        const scores = await api(`/recognition/scores?period_start=${pStart}&period_end=${pEnd}`);
        if (!scores.length) {
          scoresEl.innerHTML = '<p style="color:var(--tmp-muted)">No completed meetings found in this period.</p>';
          return;
        }
        renderScoreTable(scores, scoresEl, typeEl?.value, pStart, pEnd);
      } catch (err) {
        if (scoresEl) scoresEl.innerHTML = '<p style="color:var(--tmp-burgundy)">Error computing scores.</p>';
      }
    });

    loadPastAwards(awardsEl);
  }

  function renderScoreTable(scores, container, periodType, pStart, pEnd) {
    const periodLabel = buildPeriodLabel(periodType, pStart);
    container.innerHTML = `
      <table class="tmp-table" style="width:100%;font-size:0.85rem;">
        <thead>
          <tr>
            <th>#</th>
            <th>Member</th>
            <th>Lvl</th>
            <th title="Attendance score (25 pts max)">Attend</th>
            <th title="Service role score (35 pts max)">Service</th>
            <th title="Meeting win score (20 pts max)">Wins</th>
            <th title="Level-up bonus (15 pts)">Lvl-Up</th>
            <th title="Mentor rating bonus (5 pts)">Mentor</th>
            <th>Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${scores.map((s, i) => {
            const b = s.breakdown;
            const tip = `Attended ${b.attendance_meetings}/${b.total_meetings} meetings · ${b.service_meetings} service role meetings · ${b.wins} wins${b.leveled_up ? ' · Leveled up!' : ''}${b.mentor_avg_rating !== null ? ` · Mentor avg rating ${b.mentor_avg_rating}` : ''}`;
            return `<tr title="${esc(tip)}">
              <td>${i + 1}</td>
              <td><strong>${esc(s.member_name)}</strong></td>
              <td>${s.level}</td>
              <td>${b.attendance_score.toFixed(1)}</td>
              <td>${b.service_score.toFixed(1)}</td>
              <td>${b.win_score.toFixed(1)}</td>
              <td>${b.level_up_score > 0 ? '<span style="color:var(--tmp-teal)">+15</span>' : '—'}</td>
              <td>${b.mentor_score > 0 ? b.mentor_score.toFixed(1) : '—'}</td>
              <td><strong>${s.score.toFixed(1)}</strong></td>
              <td>
                <button class="tmp-small-button" style="white-space:nowrap;"
                  data-recog-declare="${s.member_id}"
                  data-recog-name="${esc(s.member_name)}"
                  data-recog-score="${s.score}"
                  data-recog-breakdown='${JSON.stringify(s.breakdown)}'
                  data-recog-period-type="${periodType || 'month'}"
                  data-recog-period-start="${pStart}"
                  data-recog-period-end="${pEnd}"
                  data-recog-label="${esc(periodLabel)}">
                  Declare Winner
                </button>
              </td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>`;

    container.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-recog-declare]');
      if (!btn) return;

      const memberName = btn.dataset.recogName;
      const emailOpt   = confirm(`Declare ${memberName} as ${btn.dataset.recogPeriodType === 'quarter' ? 'TM of the Quarter' : 'TM of the Month'} for ${btn.dataset.recogLabel}?\n\nClick OK to send email to winner only, or Cancel to skip email.`);

      const sendEmail = emailOpt ? confirm('Also send announcement to ALL members?') ? 'all' : 'winner' : false;

      try {
        btn.disabled = true;
        btn.textContent = 'Saving…';
        await api('/recognition/awards', {
          method: 'POST',
          body: JSON.stringify({
            member_id:   btn.dataset.recogDeclare,
            member_name: memberName,
            period_type: btn.dataset.recogPeriodType,
            period_label: btn.dataset.recogLabel,
            period_start: btn.dataset.recogPeriodStart,
            period_end:   btn.dataset.recogPeriodEnd,
            score:        parseFloat(btn.dataset.recogScore),
            breakdown:    JSON.parse(btn.dataset.recogBreakdown),
            display_on_homepage: true,
            send_email:  sendEmail,
          }),
        });
        btn.textContent = '✓ Declared';
        btn.style.color = 'var(--tmp-teal)';

        const awardsEl = qs('[data-tmp-recog-awards-list]');
        if (awardsEl) loadPastAwards(awardsEl);
      } catch (_) {
        btn.disabled = false;
        btn.textContent = 'Declare Winner';
        alert('Error declaring award. Please try again.');
      }
    }, { once: true });
  }

  function buildPeriodLabel(periodType, pStart) {
    const d = new Date(pStart + 'T00:00:00');
    if (periodType === 'quarter') {
      const q = Math.floor(d.getMonth() / 3) + 1;
      return `Q${q} ${d.getFullYear()}`;
    }
    return d.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  }

  async function loadPastAwards(container) {
    if (!container) return;
    try {
      const awards = await api('/recognition/awards?limit=20');
      if (!awards.length) {
        container.innerHTML = '<p style="color:var(--tmp-muted);font-size:0.85rem;">No awards declared yet.</p>';
        return;
      }
      container.innerHTML = `
        <table class="tmp-table" style="width:100%;font-size:0.85rem;">
          <thead><tr><th>Period</th><th>Type</th><th>Winner</th><th>Score</th><th>Homepage</th><th></th></tr></thead>
          <tbody>
            ${awards.map((a) => `
              <tr>
                <td>${esc(a.period_label)}</td>
                <td>${a.period_type === 'quarter' ? 'TM of Quarter' : 'TM of Month'}</td>
                <td><strong>${esc(a.member_name)}</strong></td>
                <td>${parseFloat(a.score).toFixed(1)}</td>
                <td>
                  <input type="checkbox" ${a.display_on_homepage == 1 ? 'checked' : ''}
                    data-award-homepage="${a.id}" title="Show on homepage" />
                </td>
                <td>
                  <button class="tmp-small-button tmp-danger" data-award-delete="${a.id}"
                    title="Revoke award">Revoke</button>
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>`;

      container.addEventListener('change', async (e) => {
        const cb = e.target.closest('[data-award-homepage]');
        if (!cb) return;
        await api(`/recognition/awards/${cb.dataset.awardHomepage}`, {
          method: 'POST',
          body: JSON.stringify({ display_on_homepage: cb.checked }),
        }).catch(() => {});
      });

      container.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-award-delete]');
        if (!btn) return;
        if (!confirm('Revoke this award? This cannot be undone.')) return;
        await api(`/recognition/awards/${btn.dataset.awardDelete}`, { method: 'DELETE' }).catch(() => {});
        loadPastAwards(container);
      });
    } catch (_) {
      container.innerHTML = '<p style="color:var(--tmp-muted)">Could not load past awards.</p>';
    }
  }

  function updateMembersTabBadge(root) {
    const badge = qs('[data-tab-badge="members"]', root);
    if (!badge) return;
    const total = (root._pendingLevelUpCount || 0) + (root._pendingRolesCount || 0);
    badge.textContent = total;
    badge.style.display = total > 0 ? "inline-flex" : "none";
  }

  function initVPETabs() {
    const root = qs("[data-tmp-vpe]");
    if (!root) return;
    const STORAGE_KEY = "tmp_vpe_tab";
    const activateTab = (tab) => {
      qsa("[data-tab]", root).forEach((btn) => {
        btn.classList.toggle("tmp-tab-btn--active", btn.dataset.tab === tab);
      });
      qsa("[data-tab-body]", root).forEach((body) => {
        body.style.display = body.dataset.tabBody === tab ? "" : "none";
      });
      try { sessionStorage.setItem(STORAGE_KEY, tab); } catch (_) {}
    };
    root.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-tab]");
      if (!btn || !btn.closest("[data-tmp-tab-nav]")) return;
      activateTab(btn.dataset.tab);
    });
    const saved = (() => { try { return sessionStorage.getItem(STORAGE_KEY); } catch (_) { return null; } })();
    activateTab(saved || "members");
  }

  // ===========================================================================
  // FEEDBACK FORM (public speech feedback page)
  // ===========================================================================
  async function initFeedbackForm() {
    const page = qs("[data-tmp-feedback-page]");
    if (!page) return;

    const aid    = page.dataset.tmpFeedbackAid;
    const hash   = page.dataset.tmpFeedbackHash;
    const header = qs("[data-tmp-feedback-header]", page);
    const body   = qs("[data-tmp-feedback-body]", page);
    const form   = qs("[data-tmp-feedback-form]", page);
    const done   = qs("[data-tmp-feedback-done]", page);
    const anonCb = qs("[data-tmp-feedback-anon]", page);
    const nameIn = qs("[data-tmp-feedback-name]", page);
    const status = qs("[data-tmp-feedback-status]", page);

    // Already submitted this session?
    let submitted = false;
    try { submitted = !!sessionStorage.getItem(`tmp_feedback_${aid}`); } catch (_) {}

    try {
      const info = await apiPublic(`/speech-feedback/form/${aid}?hash=${encodeURIComponent(hash)}`);
      const titleLine = info.speech_title ? `<br><span style="color:var(--tmp-muted);font-size:0.88rem;">"${esc(info.speech_title)}"</span>` : "";
      if (header) header.innerHTML = `<h2 style="margin:0 0 4px;">${esc(info.speaker_name)}${titleLine}</h2>
        <p style="color:var(--tmp-muted);margin:0;font-size:0.88rem;">${esc(info.meeting_date)} &middot; ${esc(info.meeting_theme)}</p>`;
      if (body) body.style.display = "";
      if (submitted) {
        if (form) form.style.display = "none";
        if (done) done.style.display = "";
      }
    } catch (err) {
      if (header) header.innerHTML = `<p style="color:#c62828;">Could not load feedback form. ${esc(err.message)}</p>`;
      return;
    }

    anonCb?.addEventListener("change", () => {
      if (!nameIn) return;
      nameIn.disabled = anonCb.checked;
      if (anonCb.checked) nameIn.value = "";
    });

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (status) { status.textContent = "Submitting…"; status.style.color = "var(--tmp-muted)"; }
      const submitBtn = form.querySelector("button[type=submit]");
      if (submitBtn) submitBtn.disabled = true;
      try {
        const fd = formData(form);
        await apiPublic(`/speech-feedback/submit/${aid}`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ hash, feedback_text: fd.feedback_text, respondent_name: anonCb?.checked ? "" : fd.respondent_name }),
        });
        try { sessionStorage.setItem(`tmp_feedback_${aid}`, "1"); } catch (_) {}
        if (form) form.style.display = "none";
        if (done) done.style.display = "";
      } catch (err) {
        if (status) { status.textContent = "Failed: " + err.message; status.style.color = "#c62828"; }
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  // ===========================================================================
  // EX COM MEETING-DAY PANEL
  // Shows the voting + wrap-up panels at the top of the member dashboard only
  // when: (a) user is Ex Com, (b) today has a meeting, (c) meeting is not complete.
  // The panels themselves are initialized by initVotingPanel() / initWrapUpPanel()
  // which auto-select today's meeting — this function only controls visibility.
  // ===========================================================================

  async function initExComPanel() {
    const wrapper = qs("[data-tmp-excom-panel]");
    if (!wrapper) return; // PHP did not render it (user is not Ex Com)

    try {
      const meetings = await api("/meetings");
      const today = localDateStr(new Date());
      const todayMeeting = (meetings || []).find(
        (m) => m.meeting_date === today && m.wrapped_up == 0
      );
      if (todayMeeting) {
        wrapper.style.display = "";
      }
    } catch (_) {
      // If API fails (permissions issue or network), panel stays hidden
    }
  }

  // ===========================================================================
  // EX COM: NEW MEMBER SPOTLIGHT (always visible to Ex Com — not meeting-gated)
  // ===========================================================================

  async function initExComSpotlightPanel() {
    const panel = qs("[data-tmp-excom-spotlight-panel]");
    if (!panel) return; // PHP did not render it (user is not Ex Com)

    const spotlightForm   = qs("[data-tmp-spotlight-form]", panel);
    const spotlightStatus = qs("[data-tmp-spotlight-status]", panel);
    if (!spotlightForm) return;

    const mSelect  = qs("[data-tmp-spotlight-member]", spotlightForm);
    const blurbEl  = qs("[data-tmp-spotlight-blurb]",  spotlightForm);
    const photoEl  = qs("[data-tmp-spotlight-photo]",  spotlightForm);
    const activeEl = qs("[data-tmp-spotlight-active]", spotlightForm);

    try {
      const members = await api("/members");
      (members || [])
        .slice().sort((a, b) => a.full_name.localeCompare(b.full_name))
        .forEach(m => {
          const opt = document.createElement("option");
          opt.value       = m.id;
          opt.textContent = `${m.full_name} (${m.pathway}, L${m.level})`;
          mSelect.appendChild(opt);
        });
    } catch (_) {
      // Member list failed to load — form still usable, just without options pre-filled
    }

    const saved = await api("/settings/new-member-spotlight").catch(() => null);
    if (saved) {
      mSelect.value    = String(saved.member_id || "");
      blurbEl.value    = saved.blurb     || "";
      photoEl.value    = saved.photo_url || "";
      activeEl.checked = !!saved.active;
    }

    spotlightForm.addEventListener("submit", async ev => {
      ev.preventDefault();
      spotlightStatus.textContent = "Saving…";
      try {
        await api("/settings/new-member-spotlight", {
          method: "POST",
          body: JSON.stringify({
            member_id: Number(mSelect.value),
            blurb:     blurbEl.value.trim(),
            photo_url: photoEl.value.trim(),
            active:    activeEl.checked,
          }),
        });
        spotlightStatus.textContent = "Saved!";
      } catch (e) {
        spotlightStatus.textContent = "Save failed: " + e.message;
      }
    });
  }

  function apiPublic(path, opts = {}) {
    const url = TMPortal.restUrl + path;
    return fetch(url, opts).then(async (r) => {
      const json = await r.json();
      if (!r.ok) throw new Error(json.message || r.statusText);
      return json;
    });
  }

  initMemberDashboard();
  initSAAAttendance();
  initSAAPollPanel();
  initMemberVoting();
  initVotingPage();
  initAdmin();
  initVPEducation();
  initLevelUpQueue();
  initEnrolment();
  initVotingPanel();
  initWrapUpPanel();
  initExComPanel();
  initExComSpotlightPanel();
  initRecognitionPanel();
  initMentorRating();
  initMyRecognition();
  initVPETabs();
  initFeedbackForm();

  // Hide the WordPress page title (rendered by the theme above our portal shortcode)
  // and strip the theme's content-area top padding so the topbar starts flush.
  document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.tmp-portal')) return;
    const titleEl = document.querySelector(
      '.entry-title, .page-title, h1.post-title, h1.wp-block-post-title, .wp-block-post-title'
    );
    if (titleEl) titleEl.style.display = 'none';
    // Remove top padding from common theme content wrappers
    const contentWrap = document.querySelector(
      '.entry-content, .page-content, .wp-block-post-content, article.page'
    );
    if (contentWrap) contentWrap.style.paddingTop = '0';
  });
})();
