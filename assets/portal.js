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

  // Shared VPE refresher
  let refreshVPE = () => {};

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = (value) => String(value || "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }[char]));

  function formatTime(totalMinutes) {
    const h = Math.floor(totalMinutes / 60) % 24;
    const m = totalMinutes % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  }

  function generatePrintView(meeting) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      alert("Please allow pop-ups for this site to print the agenda.");
      return;
    }
    const [h, m] = (meeting.start_time || "18:30:00").split(':').map(Number);
    let runningTime = h * 60 + m;

    const agendaRows = (meeting.assignments || []).map((a) => {
      const start = formatTime(runningTime);
      const dur = Number(a.duration || 0);
      runningTime += dur;
      const end = formatTime(runningTime);
      return `
        <tr>
          <td>${start}</td>
          <td>${dur}m</td>
          <td>${end}</td>
          <td><strong>${esc(a.role_name)}</strong></td>
          <td>${esc(a.member_name || 'Unassigned')}</td>
          <td>${esc(a.speech_title || '')}</td>
        </tr>`;
    }).join('');

    printWindow.document.write(`
      <html>
        <head>
          <title>Meeting Agenda - ${esc(meeting.meeting_date)}</title>
          <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 50px; line-height: 1.6; color: #333; }
            h1 { color: #004165; border-bottom: 2px solid #004165; padding-bottom: 10px; margin-bottom: 20px; }
            .details { margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-radius: 5px; border-left: 5px solid #004165; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #004165; color: white; padding: 12px; text-align: left; }
            td { border-bottom: 1px solid #ddd; padding: 12px; vertical-align: top; }
            .notes { margin-top: 40px; white-space: pre-wrap; color: #555; font-style: italic; border-top: 1px solid #eee; padding-top: 20px; }
            @media print { .no-print { display: none; } }
          </style>
        </head>
        <body>
          <h1>Toastmasters Meeting Agenda</h1>
          <div class="details">
            <strong>Date:</strong> ${esc(meeting.meeting_date)} | 
            <strong>Theme:</strong> ${esc(meeting.theme)} | 
            <strong>Venue:</strong> ${esc(meeting.venue || 'TBD')}
          </div>
          <table>
            <thead><tr><th width="10%">Start</th><th width="10%">Dur</th><th width="10%">End</th><th width="25%">Role</th><th width="20%">Member</th><th width="25%">Speech Title / Notes</th></tr></thead>
            <tbody>${agendaRows}</tbody>
          </table>
          <div class="notes"><strong>Agenda Notes:</strong><br>${esc(meeting.agenda_notes || 'No additional notes.')}</div>
          <script>
            setTimeout(() => {
              window.focus();
              window.print();
              window.onafterprint = () => window.close();
            }, 500);
          </script>
        </body>
      </html>
    `);
    printWindow.document.close();
  }

  async function api(path, options = {}) {
    let url = `${TMPortal.restUrl}${path}`;
    if (!options.method || options.method === 'GET') {
      url += (url.includes('?') ? '&' : '?') + '_=' + Date.now();
    }
    const response = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": TMPortal.nonce,
        ...(options.headers || {}),
      },
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || "Request failed");
    }
    return data;
  }

  function formData(form) {
    const fd = new FormData(form);
    const data = {};
    for (let [key, value] of fd.entries()) {
      if (key.endsWith('[]')) {
        const cleanKey = key.slice(0, -2);
        if (!data[cleanKey]) data[cleanKey] = [];
        data[cleanKey].push(value);
      } else {
        data[key] = value;
      }
    }
    return data;
  }

  function fillForm(form, record) {
    Object.entries(record).forEach(([key, value]) => {
      if (form.elements[key]) {
        let val = value || "";
        if (form.elements[key].type === 'datetime-local' && val) {
          val = val.replace(' ', 'T').substring(0, 16);
        }
        if (form.elements[key].type === 'checkbox') {
          form.elements[key].checked = !!val;
        } else {
          form.elements[key].value = val;
        }
      }
    });
  }

  function clearForm(form) {
    form.reset();
    if (form.elements.id) {
      form.elements.id.value = "";
    }
  }

  /**
   * Fetches data and updates the Member Dashboard UI.
   * Does NOT attach event listeners.
   */
  async function updateMemberDashboard() {
    const root = qs("[data-tmp-member-dashboard]");
    if (!root) return;

    try {
      const member = await api("/me");
      const level = Number(member.level || 1);
      const progress = Math.max(20, Math.min(100, level * 20));

      qs("[data-tmp-member-name]", root).textContent = member.full_name;
      qs("[data-tmp-member-summary]", root).textContent = `${member.pathway} - Level ${level}`;
      qs("[data-tmp-progress]", root).textContent = `${progress}%`;
      qs("[data-tmp-progress-bar]", root).style.width = `${progress}%`;
      qs("[data-tmp-state]", root).textContent = member.state || "Active";
      qs("[data-tmp-project]", root).textContent = member.current_project || "Not assigned";
      qs("[data-tmp-mentor]", root).textContent = member.mentor || "Not assigned";
      qs("[data-tmp-next-action]", root).textContent = member.next_action || "No next action recorded.";
      qs("[data-tmp-notes]", root).textContent = member.officer_notes || "No officer notes yet.";
      qs("[data-tmp-levels]", root).innerHTML = levels.map((label, index) => {
        const number = index + 1;
        const className = number < level ? "tmp-done" : number === level ? "tmp-active" : "";
        return `<li class="${className}">${esc(label)}</li>`;
      }).join("");

      const requests = await api("/me/requests").catch(() => []);
      qs("[data-tmp-active-requests]", root).innerHTML = requests.length ? `
        <div class="tmp-table-wrap">
          <table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>${requests.map(r => {
              const isApproved = String(r.assigned_id) === String(member.id) && r.assignment_status === 'Confirmed';
              const isDenied = r.assigned_id && r.assigned_id != 0 && String(r.assigned_id) !== String(member.id) && r.assignment_status === 'Confirmed';
              
              let statusLabel = 'Pending';
              let statusStyle = 'background:#eee; color:#333;';
              
              if (isApproved) {
                statusLabel = 'Approved';
                statusStyle = 'background:#2e7d32; color:white;';
              } else if (isDenied) {
                statusLabel = 'Denied';
                statusStyle = 'background:#c62828; color:white;';
              }
              
              return `
              <tr>
                <td>${esc(r.meeting_date)} - ${esc(r.theme)}</td>
                <td>${esc(r.role_name)}</td>
                <td><span class="tmp-tag" style="background:#f5f5f5">Priority ${esc(r.priority)}</span></td>
                <td><span class="tmp-tag" style="${statusStyle}">${statusLabel}</span></td>
                <td><button class="tmp-small-button tmp-danger" data-cancel-request="${esc(r.id)}">Cancel</button></td>
              </tr>
            `}).join("")}</tbody>
          </table>
        </div>
      ` : "<p>You have no active role requests.</p>";

      const history = await api("/me/requests/history").catch(() => []);
      qs("[data-tmp-request-history]", root).innerHTML = history.length ? `
        <div class="tmp-table-wrap">
          <table class="tmp-table">
            <thead><tr><th>Meeting</th><th>Role</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>${history.map(r => {
              const isApproved = String(r.assigned_id) === String(member.id) && r.assignment_status === 'Confirmed';
              const isDenied = r.assigned_id && r.assigned_id != 0 && String(r.assigned_id) !== String(member.id) && r.assignment_status === 'Confirmed';
              
              let statusLabel = 'Unprocessed';
              let statusStyle = 'background:#eee; color:#333;';
              
              if (isApproved) {
                statusLabel = 'Approved';
                statusStyle = 'background:#2e7d32; color:white;';
              } else if (isDenied) {
                statusLabel = 'Denied';
                statusStyle = 'background:#c62828; color:white;';
              }
              
              return `
              <tr>
                <td>${esc(r.meeting_date)} - ${esc(r.theme)}</td>
                <td>${esc(r.role_name)}</td>
                <td><span class="tmp-tag" style="background:#f5f5f5">Priority ${esc(r.priority)}</span></td>
                <td><span class="tmp-tag" style="${statusStyle}">${statusLabel}</span></td>
              </tr>
            `}).join("")}</tbody>
          </table>
        </div>
      ` : "<p>No request history found.</p>";

      const roleHistory = await api("/me/participation-history").catch(() => []);
      let roleHistoryHtml = '';
      if (Object.keys(roleHistory).length > 0) {
        for (const level in roleHistory) {
          roleHistoryHtml += `
            <h4>Level ${esc(level)}</h4>
            <div class="tmp-table-wrap">
              <table class="tmp-table">
                <thead><tr><th>Role</th><th>Count</th><th>Last Completed</th></tr></thead>
                <tbody>${roleHistory[level].map(r => `
                  <tr>
                    <td>${esc(r.role_name)}</td>
                    <td>${esc(r.count)}</td>
                    <td>${esc(r.last_completed_date)}</td>
                  </tr>
                `).join("")}</tbody>
              </table>
            </div>
          `;
        }
      } else {
        roleHistoryHtml = "<p>No role history found.</p>";
      }
      qs("[data-tmp-role-history]", root).innerHTML = roleHistoryHtml;

      // Fetch open slots and calculate suitability per role instead of filtering them out
      const response = await api("/meetings/open-slots");
      const memberLevel = Number(response.member_level || 1);
      const participation = response.member_participation || {};
      const currentLevelHistory = participation[memberLevel] || {};

      const slots = (response && Array.isArray(response.slots)) ? response.slots
        .filter(s => !s.role_name.toLowerCase().includes('presiding officer'))
        .map(s => {
        const role = s.role_name.toLowerCase();
        let qualified = true;
        let requirement = "";

        // Rule 1: Tiered Hard Gating
        if (role.includes('general')) {
          qualified = memberLevel >= 4;
          requirement = "Level 4+ (Meeting Management)";
        } else if (role.includes('toastmaster') || role.includes('topics master')) {
          qualified = memberLevel >= 3;
          requirement = "Level 3+ (Meeting Leadership)";
        } else if (role.includes('grammarian')) {
          qualified = memberLevel >= 2;
          requirement = "Level 2+ (Language Skills)";
        } else if (role.includes('educational presentation')) {
          qualified = memberLevel >= 3;
          requirement = "Level 3+ (Teaching Requirement)";
        }

        // Rule 2: Goal Identification (Required for current level but not yet done)
        const isDoneInLevel = !!currentLevelHistory[s.role_name];
        return { ...s, qualified, requirement, isGoal: qualified && !isDoneInLevel };
      }) : [];

      const reqForm = qs("[data-tmp-member-request-form]", root);
      const mSelect = qs("[data-tmp-req-meeting-select]", reqForm);
      const rSelect = qs("[data-tmp-req-role-select]", reqForm);

      if (reqForm && reqForm.closest('article')) {
        // Hide the request form section if there are no open slots
        reqForm.closest('article').style.display = slots.length ? 'block' : 'none';
      }

      root._groupedSlots = slots.reduce((acc, s) => {
        const key = `${s.meeting_date} - ${s.theme}`;
        if (!acc[key]) acc[key] = { id: s.meeting_id, text: key, roles: [] };
        acc[key].roles.push(s);
        return acc;
      }, {});

      mSelect.innerHTML = '<option value="">Select a meeting...</option>' + 
        Object.values(root._groupedSlots).map(g => `<option value="${esc(g.id)}">${esc(g.text)} (${g.roles.length} roles open)</option>`).join("");
      rSelect.innerHTML = '<option value="">Select a meeting first...</option>';

      const recs = await api("/me/recommendations").catch(() => []);
      qs("[data-tmp-recommendations]", root).innerHTML = recs.length ? recs.map(r => `
        <div class="tmp-rec-item">
          <strong>${esc(r.title)}</strong>
          <small>${esc(r.type)}</small>
          <p>${esc(r.note)}</p>
        </div>
      `).join("") : "<p>No recommendations today.</p>";

      root._member = member;
    } catch (error) {
      root.innerHTML = `<div class="tmp-panel"><h2>Dashboard unavailable</h2><p>${esc(error.message)}</p></div>`;
    }
  }

  async function initMemberDashboard() {
    const root = qs("[data-tmp-member-dashboard]");
    if (!root) return;

    const reqForm = qs("[data-tmp-member-request-form]", root);
    const mSelect = qs("[data-tmp-req-meeting-select]", reqForm);
    const rSelect = qs("[data-tmp-req-role-select]", reqForm);

    await updateMemberDashboard();

    const activeRequestsList = qs("[data-tmp-active-requests]", root);
    activeRequestsList.addEventListener("click", async (e) => {
      const cancelBtn = e.target.closest("[data-cancel-request]");
      if (!cancelBtn) return;

      if (confirm("Cancel this role request?")) {
        cancelBtn.disabled = true;
        try {
          await api(`/requests/${cancelBtn.dataset.cancelRequest}`, { method: "DELETE" });
          await updateMemberDashboard();
          refreshVPE();
        } catch (err) {
          alert(err.message);
          cancelBtn.disabled = false;
        }
      }
    });

    mSelect.addEventListener("change", () => {
      const group = Object.values(root._groupedSlots || {}).find(g => String(g.id) === mSelect.value);

      const uniqueRoles = [];
      const seen = new Set();

      if (group) {
        group.roles.forEach(r => {
          // Generic display name: remove trailing digits and parenthetical segments
          const display = r.role_name.replace(/\s+\d+(\s*\(.*?\))?$/, '').replace(/\s*\(.*?\)\s*/g, '').trim();
          if (!seen.has(display)) {
            seen.add(display);
            uniqueRoles.push({ ...r, display });
          } else {
            const existing = uniqueRoles.find(x => x.display === display);
            if (r.isGoal) existing.isGoal = true;
          }
        });
      }

      const roleOptions = '<option value="">(None)</option>' + 
        uniqueRoles.map(r => {
          const disabled = !r.qualified ? 'disabled' : '';
          let label = r.display;
          if (!r.qualified) label += ` (${r.requirement})`;
          else if (r.isGoal) label += ` ⭐ Goal`;

          return `<option value="${esc(r.assignment_id)}" ${disabled}>${esc(label)}</option>`;
        }).join("");
      
      const allRoleSelects = qsa("[data-tmp-req-role-select]", reqForm);
      allRoleSelects.forEach(sel => {
        sel.innerHTML = roleOptions;
      });

      // Update the "Learn More" info box for locked roles
      const lockedRoles = group ? group.roles.filter(r => !r.qualified) : [];
      const infoBox = qs("[data-tmp-role-info]", reqForm);
      if (infoBox) {
        infoBox.innerHTML = lockedRoles.map(r => `
          <div style="margin-top:5px; font-size:11px; color:var(--tmp-muted);">
            <strong>${esc(r.role_name)}</strong> requires ${esc(r.requirement)}. <a href="https://www.toastmasters.org/membership/club-meeting-roles" target="_blank" style="color:var(--tmp-burgundy); text-decoration:underline;">Learn More</a>
          </div>
        `).join("");
      }
    });

    reqForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!rSelect.value || !root._member) return;
      const btn = reqForm.querySelector('button');
      btn.disabled = true;
      try {
        const data = formData(reqForm);
        await api("/requests", {
          method: "POST",
          body: JSON.stringify({ 
            meeting_id: data.meeting_id,
            member_id: root._member.id,
            priorities: data.priorities
          })
        });
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

  async function initAdmin() {
    const root = qs("[data-tmp-admin]");
    if (!root) {
      return;
    }

    const form = qs("[data-tmp-member-form]", root);
    const importForm = qs("[data-tmp-import-form]", root);
    const importStatus = qs("[data-tmp-import-status]", root);
    const table = qs("[data-tmp-member-table]", root);
    const count = qs("[data-tmp-member-count]", root);

    async function render(force = false) {
      if (force === true || !root._members) {
        root._members = await api("/members");
      }
      const members = root._members;

      const searchTerm = qs("[data-tmp-admin-search]", root)?.value.toLowerCase() || "";
      const groupKey = qs("[data-tmp-admin-group-by]", root)?.value || "none";
      const statusFilter = qs("[data-tmp-admin-status]", root)?.value || "all";
      const levelFilter = qs("[data-tmp-admin-level]", root)?.value || "all";

      const filtered = members.filter(m => 
        (!searchTerm || 
          m.full_name.toLowerCase().includes(searchTerm) || 
          m.email.toLowerCase().includes(searchTerm)) &&
        (statusFilter === "all" || m.state === statusFilter) &&
        (levelFilter === "all" || String(m.level) === levelFilter)
      );

      count.textContent = `${filtered.length} ${filtered.length === 1 ? "record" : "records"}`;

      const memberToRow = (member) => `
        <tr>
          <td><strong>${esc(member.full_name)}</strong></td>
          <td>${esc(member.customer_id || "")}</td>
          <td>${esc(member.email)}</td>
          <td>${esc(member.pathway)}</td>
          <td>Level ${esc(member.level)}</td>
          <td>${esc(member.state)}</td>
          <td>${member.is_exempt_from_unpaid_block ? 'Yes' : 'No'}</td>
          <td>
            <div class="tmp-row-actions">
              <button class="tmp-small-button" type="button" data-edit-member="${esc(member.id)}">Edit</button>
              <button class="tmp-small-button tmp-danger" type="button" data-delete-member="${esc(member.id)}">Delete</button>
            </div>
          </td>
        </tr>`;

      if (groupKey === "none") {
        table.innerHTML = filtered.map(memberToRow).join("");
      } else {
        const groups = filtered.reduce((acc, m) => {
          const key = (groupKey === 'level' ? `Level ${m.level}` : m[groupKey]) || "Unassigned";
          if (!acc[key]) acc[key] = [];
          acc[key].push(m);
          return acc;
        }, {});

        table.innerHTML = Object.keys(groups).sort().map(groupName => `
          <tr class="tmp-group-row"><td colspan="8" style="background:#f5f5f5; font-weight:bold; padding:8px; border-bottom:1px solid #ccc;">${esc(groupName)} (${groups[groupName].length})</td></tr>
          ${groups[groupName].map(memberToRow).join("")}
        `).join("");
      }
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await api("/members", {
        method: "POST",
        body: JSON.stringify(formData(form)),
      });
      clearForm(form);
      await render(true);
    });

    importForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      importStatus.textContent = "Importing...";

      const response = await fetch(`${TMPortal.restUrl}/members/import`, {
        method: "POST",
        headers: {
          "X-WP-Nonce": TMPortal.nonce,
        },
        body: new FormData(importForm),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        importStatus.textContent = data.message || "Import failed.";
        return;
      }

      importStatus.textContent = `Imported ${data.imported_members} members. Created ${data.created_users} users, updated ${data.updated_users}.`;
      importForm.reset();
      await render(true);
    });

    qs("[data-tmp-clear-member]", root)?.addEventListener("click", () => clearForm(form));

    qs("[data-tmp-admin-search]", root)?.addEventListener("input", () => render());
    qs("[data-tmp-admin-group-by]", root)?.addEventListener("change", () => render());
    qs("[data-tmp-admin-status]", root)?.addEventListener("change", () => render());
    qs("[data-tmp-admin-level]", root)?.addEventListener("change", () => render());

    table.addEventListener("click", async (event) => {
      const edit = event.target.closest("[data-edit-member]");
      const del = event.target.closest("[data-delete-member]");

      if (edit) {
        const member = root._members.find((item) => String(item.id) === edit.dataset.editMember);
        if (member) {
          fillForm(form, member);
          form.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      }

      if (del) {
        if (confirm("Are you sure you want to delete this member?")) {
          const row = event.target.closest("tr");
          if (row) row.style.display = "none";
          await api(`/members/${del.dataset.deleteMember}`, { method: "DELETE" });
          await render(true);
        }
      }
    });

    await render(true);
  }

  async function initVPEducation() {
    const root = qs("[data-tmp-vpe]");
    if (!root) {
      return;
    }

    const meetingForm = qs("[data-tmp-meeting-form]", root);
    const assignmentForm = qs("[data-tmp-assignment-form]", root);
    const meetingSelect = qs("[data-tmp-meeting-select]", root);
    const roleSelect = qs("[data-tmp-role-select]", root);
    const memberSelect = qs("[data-tmp-member-select]", root);
    const meetingList = qs("[data-tmp-meeting-list]", root);
    const meetingCount = qs("[data-tmp-meeting-count]", root);

    async function renderMembers() {
      const members = await api("/members");
      memberSelect.innerHTML = `<option value="">Unassigned</option>` + members
        .filter(m => m.is_eligible)
        .map((member) =>
        `<option value="${esc(member.id)}">${esc(member.formatted_name)}</option>`
      ).join("");
    }

    // Hook up shared refresher
    refreshVPE = () => renderMeetings().catch(console.error);

    async function renderMeetings(selectedId = null) {
      const meetings = await api("/meetings") || [];
      root._meetings = Array.isArray(meetings) ? meetings : [];
      
      meetingCount.textContent = `${meetings.length} ${meetings.length === 1 ? "meeting" : "meetings"}`;
      meetingSelect.innerHTML = '<option value="">Select a meeting...</option>' + 
        meetings.map((meeting) =>
        `<option value="${esc(meeting.id)}">${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</option>`
      ).join("");

      renderPendingRequests(root).catch(() => {});

      if (selectedId) {
        meetingSelect.value = selectedId;
      }

      updateRoles();

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting) => {
        const [h, m] = (meeting.start_time || "18:30:00").split(':').map(Number);
        const startTimeInMins = (h * 60) + (m || 0);
        let runningTime = startTimeInMins;

        const agendaHtml = (meeting.assignments || []).map((assignment) => {
          const start = formatTime(runningTime);
          const duration = Number(assignment.duration || 0);
          runningTime += duration;
          const end = formatTime(runningTime);
          
          return `
              <li>
                <span>
                  <strong>${esc(assignment.role_name)}</strong> / ${assignment.member_name ? esc(assignment.member_name) : (assignment.first_requester ? `<em>Req by ${esc(assignment.first_requester)}</em>` : "Unassigned")} / ${esc(assignment.status)} / <small class="tmp-time-tag">${start} / ${duration}m / ${end}</small>
                  ${assignment.status === 'Requested' ? ' <span class="tmp-tag" style="background:#ffd700; padding:2px 4px; border-radius:3px; font-size:10px;">PRIORITY</span>' : ''}
                  ${assignment.request_count > 1 ? ` <span class="tmp-tag" style="background:#ff5722; color:white; padding:2px 4px; border-radius:3px; font-size:10px;">CONFLICT (${assignment.request_count})</span>` : ''}
                  ${assignment.request_count > 0 && assignment.status !== 'Confirmed' && !assignment.member_id ? ` <span class="tmp-tag" style="background:#2196f3; color:white; padding:2px 4px; border-radius:3px; font-size:10px;">PENDING REQ</span>` : ''}
                  ${assignment.suitability ? `<span class="tmp-tag" style="background:${assignment.suitability.suitable ? '#e1f5fe' : '#ffebee'}; color:${assignment.suitability.suitable ? '#01579b' : '#b71c1c'}; padding:2px 4px; border-radius:3px; font-size:10px; margin-left:5px;">${esc(assignment.suitability.reason)}</span>` : ""}
                  ${assignment.speech_title ? `<br><small>Title: ${esc(assignment.speech_title)}</small>` : ""}
                </span>
                <span>
                  ${assignment.request_count > 0 ? `<button class="tmp-small-button" style="background:#01579b; color:white; font-weight:bold;" type="button" data-view-conflicts="${esc(assignment.id)}">Review Requests (${assignment.request_count})</button>` : ""}
                  <button class="tmp-small-button tmp-danger" type="button" data-delete-assignment="${esc(assignment.id)}">Delete</button>
                </span>
              </li>
          `;
        }).join("");

        const totalAssigned = runningTime - startTimeInMins;
        const limit = Number(meeting.total_duration || 120);
        const warning = (totalAssigned > limit) ? 
          `<p class="tmp-tag" style="background:#b71c1c; color:white; display:block; margin:10px 0; text-align:center; padding:5px; border-radius:4px;">
            Warning: Agenda (${totalAssigned}m) exceeds limit (${limit}m)
          </p>` : '';

        return `
        <article class="tmp-agenda-card">
          <div class="tmp-card-head">
            <h4>${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</h4>
            <div>
              <span class="tmp-tag">${esc(meeting.start_time ? meeting.start_time.substring(0,5) : "18:30")}</span>
              ${meeting.requests_close_at ? `<span class="tmp-tag" style="background:#607d8b">Closes: ${esc(meeting.requests_close_at.substring(0, 16))}</span>` : ''}
            </div>
          </div>
          <p>${esc(meeting.venue || "Venue not set")}</p>
          <p>${esc(meeting.agenda_notes || "")}</p>
          ${warning}
          <ul class="tmp-assignment-list">
            ${agendaHtml || "<li><span>No roles scheduled yet.</span></li>"}
          </ul>
          <div style="display:flex; gap:10px; margin-top:15px;">
            <button class="tmp-button tmp-secondary tmp-small" data-suggest-roles="${meeting.id}">Get Intelligent Suggestions</button>
            <button class="tmp-button tmp-secondary tmp-small" data-print-agenda="${meeting.id}">Print Agenda</button>
            <button class="tmp-button tmp-danger tmp-small" data-delete-meeting="${meeting.id}">Delete Meeting</button>
          </div>
        </article>
      `}).join("")}</div>`;
    }

    const updateRoles = () => {
      const currentMeetingId = meetingSelect.value;
      clearForm(assignmentForm);
      assignmentForm.elements.role_name.value = "";
      assignmentForm.elements.meeting_id.value = currentMeetingId;
      delete assignmentForm._tmp_role_name;
      toggleSpeechTitle('');

      const meeting = (root._meetings || []).find(m => String(m.id) === currentMeetingId);
      let html = '<option value="">-- Existing Slots (Select to Edit) --</option>';
      html += (meeting?.assignments || []).map(a => 
        `<option value="id:${esc(a.id)}">${esc(a.role_name)} ${a.member_name ? '('+esc(a.member_name)+')' : '(Unassigned)'}</option>`
      ).join("");

      html += '<option value="">-- Standard Roles (Create New) --</option>';
      html += Object.keys(TMPortal.standardRoles).map(role => 
        `<option value="name:${esc(role)}">${esc(role)}</option>`
      ).join("");
      
      roleSelect.innerHTML = html;
    };

    const speechTitleWrapper = qs("[data-tmp-speech-title-wrapper]", assignmentForm);
    const toggleSpeechTitle = (roleName) => {
      if (roleName && roleName.toLowerCase().includes('speaker')) {
        speechTitleWrapper.style.display = 'block';
      } else {
        speechTitleWrapper.style.display = 'none';
      }
    };

    meetingSelect.addEventListener("change", updateRoles);

    roleSelect.addEventListener("change", () => {
      const val = roleSelect.value;
      const currentMeetingId = meetingSelect.value;

      if (!val) {
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = currentMeetingId;
        assignmentForm.elements.role_name.value = "";
        toggleSpeechTitle('');
        return;
      }

      const meeting = (root._meetings || []).find(m => String(m.id) === currentMeetingId);
      let selectedRoleName = '';

      if (val.startsWith('id:')) {
        const id = val.split(':')[1];
        const assignment = meeting?.assignments.find(a => String(a.id) === id);
        if (assignment) {
          fillForm(assignmentForm, assignment);
          assignmentForm.elements.meeting_id.value = currentMeetingId;
          roleSelect.value = val; // Selection is safe because roleSelect doesn't have name="role_name"
          selectedRoleName = assignment.role_name;
        }
      } else if (val.startsWith('name:')) {
        const templateName = val.split(':')[1];
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = currentMeetingId;
        roleSelect.value = val;
        assignmentForm.elements.role_name.value = templateName;
        selectedRoleName = templateName;
      }
      toggleSpeechTitle(selectedRoleName);
    });

    meetingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const btn = event.target.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      try {
        const newMeeting = await api("/meetings", {
          method: "POST",
          body: JSON.stringify(formData(meetingForm)),
        });
        alert("Meeting created successfully with role templates!");
        clearForm(meetingForm);
        await renderMeetings(newMeeting.id);
      } catch (err) {
        alert("Failed to save meeting: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    assignmentForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const btn = event.target.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      try {
        const data = formData(assignmentForm);
        await api("/assignments", {
          method: "POST",
          body: JSON.stringify(data),
        });

        alert("Assignment saved.");
        clearForm(assignmentForm);
        toggleSpeechTitle('');
        await renderMeetings();
      } catch (err) {
        alert("Failed to save assignment: " + err.message);
      } finally {
        if (btn) btn.disabled = false;
      }
    });

    qs("[data-tmp-clear-meeting]", root)?.addEventListener("click", () => clearForm(meetingForm));
    qs("[data-tmp-clear-assignment]", root)?.addEventListener("click", () => clearForm(assignmentForm));

    meetingList.addEventListener("click", async (e) => {
      const del = e.target.closest("[data-delete-assignment]");
      const approve = e.target.closest("[data-approve-assignment]");
      const suggest = e.target.closest("[data-suggest-roles]");
      const print = e.target.closest("[data-print-agenda]");
      const viewConflicts = e.target.closest("[data-view-conflicts]");
      const delMeeting = e.target.closest("[data-delete-meeting]");
      const approveReq = e.target.closest("[data-vpe-approve-req]");

      if (del) {
        if (confirm("Remove this role from the agenda? Subsequent role timings will adjust automatically.")) {
          const li = e.target.closest("li");
          if (li) li.style.display = "none";
          await api(`/assignments/${del.dataset.deleteAssignment}`, { method: "DELETE" });
          await renderMeetings();
          updateMemberDashboard().catch(() => {});
        }
      } else if (approve) {
        await api("/assignments", {
          method: "POST",
          body: JSON.stringify({ id: approve.dataset.approveAssignment, status: "Confirmed" })
        });
        await renderMeetings();
      } else if (delMeeting) {
        if (confirm("Are you sure you want to delete this meeting? This will permanently remove the agenda and all assignments.")) {
          const card = e.target.closest("article");
          if (card) card.style.display = "none";
          await api(`/meetings/${delMeeting.dataset.deleteMeeting}`, { method: "DELETE" });
          // Reset UI state before re-rendering
          meetingSelect.value = "";
          updateRoles();
          await renderMeetings();
          updateMemberDashboard().catch(() => {});
        }
      } else if (approveReq) {
        await api("/assignments", {
          method: "POST",
          body: JSON.stringify({ id: approveReq.dataset.vpeApproveReq, member_id: approveReq.dataset.vpeMemberId, status: "Confirmed" })
        });
        await renderMeetings();
      } else if (viewConflicts) {
        const assignmentId = viewConflicts.dataset.viewConflicts;
        const conflicts = await api(`/assignments/${assignmentId}/conflicts`);
        if (conflicts.length === 0) {
          alert("No other members have requested this role.");
        } else {
          const options = conflicts.map((c, i) => {
            const isP1 = String(c.priority) === '1';
            const label = isP1 ? `⭐ ${c.member_name} (PRIORITY 1)` : `${c.member_name} (Priority ${c.priority})`;
            return `${i + 1}. ${label}`;
          }).join("\n");
          const choice = prompt(`Conflicting Requests for this slot:\n\n${options}\n\nEnter the number of the member to assign, or Cancel:`);
          
          const index = parseInt(choice, 10) - 1;
          if (!isNaN(index) && conflicts[index]) {
            const selected = conflicts[index];
            await api("/assignments", {
              method: "POST",
              body: JSON.stringify({ id: assignmentId, member_id: selected.member_id, status: "Confirmed" })
            });
            await renderMeetings();
          }
        }
      } else if (print) {
        const meeting = root._meetings.find(m => String(m.id) === print.dataset.printAgenda);
        if (meeting) {
          generatePrintView(meeting);
        }
      } else if (suggest) {
        const data = await api(`/meetings/${suggest.dataset.suggestRoles}/suggestions`);
        const suggestions = data.suggestions || [];
        const trace = data.trace || [];

        // Debugging aid: view raw data in Browser Console (F12)
        console.log("Suggestions Trace:", trace);
        console.table(suggestions);

        const logOutput = trace.length ? "\n\nTRAVERSAL LOG:\n" + trace.join("\n") : "";
        
        if (!suggestions.length) {
          return alert("No suggestions found." + logOutput);
        }

        const summary = suggestions.map(s => `• ${s.role_name} → ${s.suggested_member_name}`).join("\n");
        if (confirm("RECOMMENDED ASSIGNMENTS:\n\n" + summary + "\n\nWould you like to apply these suggestions to the agenda?")) {
          for (const s of suggestions) {
            await api("/assignments", {
              method: "POST",
              body: JSON.stringify({ 
                id: s.id, 
                member_id: s.suggested_member_id, 
                status: "Confirmed" 
              })
            });
          }
          await renderMeetings();
        }
      }
    });

    try {
      // Run these independently so one failure doesn't block the other
      renderMembers().catch(err => console.error("Members load failed:", err));
      await renderMeetings();
    } catch (err) {
      console.error("VPE Dashboard Initialization Error:", err);
      meetingList.innerHTML = `<div class="tmp-panel tmp-danger"><h3>Error loading agendas</h3><p>${esc(err.message)}</p></div>`;
    }
  }

  async function renderPendingRequests(root) {
    const reqs = await api("/meetings/requests").catch(() => []);
    const count = qs("[data-tmp-request-count]", root);
    const list = qs("[data-tmp-vpe-requests]", root);
    
    if (!count || !list) return;

    count.textContent = `${reqs.length} pending`;
    list.innerHTML = reqs.length ? `
      <div class="tmp-table-wrap">
        <table class="tmp-table">
          <thead><tr><th>Meeting</th><th>Role</th><th>Member</th><th>Priority</th><th>Action</th></tr></thead>
          <tbody>${reqs.map(r => `
            <tr>
              <td>${esc(r.meeting_date)}</td>
              <td>${esc(r.role_name)}</td>
              <td><strong>${esc(r.member_name)}</strong></td>
              <td><span class="tmp-tag" style="background:#eee">Priority ${esc(r.priority)}</span></td>
              <td><button class="tmp-small-button" style="background:#2e7d32; color:white;" data-vpe-approve-req="${esc(r.assignment_id)}" data-vpe-member-id="${esc(r.member_id)}">Approve</button></td>
            </tr>
          `).join("")}</tbody>
        </table>
      </div>
    ` : "<p>No pending requests across upcoming meetings.</p>";
  }

  async function initEnrolment() {
    const form = qs("[data-tmc-enrol-form]");
    const status = qs("[data-tmc-form-status]");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      status.textContent = "Submitting application...";
      
      try {
        const data = formData(form);
        await api("/enrol", {
          method: "POST",
          body: JSON.stringify(data)
        });
        status.textContent = `Thank you, ${data.name}. Your application has been received!`;
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
