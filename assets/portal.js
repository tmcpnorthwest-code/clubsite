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

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const esc = (value) => String(value || "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  }[char]));

  async function api(path, options = {}) {
    const response = await fetch(`${TMPortal.restUrl}${path}`, {
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
        form.elements[key].value = value || "";
      }
    });
  }

  function clearForm(form) {
    form.reset();
    if (form.elements.id) {
      form.elements.id.value = "";
    }
  }

  function formatTime(totalMinutes) {
    const h = Math.floor(totalMinutes / 60) % 24;
    const m = totalMinutes % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  }

  function generatePrintView(meeting) {
    const printWindow = window.open('', '_blank');
    const [h, m] = (meeting.start_time || "18:30:00").split(':').map(Number);
    let runningTime = h * 60 + m;

    const agendaRows = (meeting.assignments || []).map((a) => {
      const start = formatTime(runningTime);
      runningTime += Number(a.duration || 0);
      const end = formatTime(runningTime);
      return `
        <tr>
          <td>${start} - ${end}</td>
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
            <thead><tr><th width="15%">Time</th><th width="30%">Role</th><th width="25%">Member</th><th width="30%">Speech Title / Notes</th></tr></thead>
            <tbody>${agendaRows}</tbody>
          </table>
          <div class="notes"><strong>Agenda Notes:</strong><br>${esc(meeting.agenda_notes || 'No additional notes.')}</div>
          <script>window.onload = () => { window.print(); window.close(); };</script>
        </body>
      </html>
    `);
    printWindow.document.close();
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

      const slots = await api("/meetings/open-slots");
      const reqForm = qs("[data-tmp-member-request-form]", root);
      const mSelect = qs("[data-tmp-req-meeting-select]", reqForm);
      const rSelect = qs("[data-tmp-req-role-select]", reqForm);

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

    mSelect.addEventListener("change", () => {
      const group = Object.values(root._groupedSlots || {}).find(g => String(g.id) === mSelect.value);
      rSelect.innerHTML = '<option value="">Select an available role...</option>' + 
        (group ? group.roles.map(r => `<option value="${esc(r.assignment_id)}">${esc(r.role_name)}</option>`).join("") : "");
    });

    reqForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!rSelect.value || !root._member) return;
      const btn = reqForm.querySelector('button');
      btn.disabled = true;
      try {
        await api("/assignments", {
          method: "POST",
          body: JSON.stringify({ id: rSelect.value, member_id: root._member.id, status: "Requested" })
        });
        alert("Role requested successfully!");
        await updateMemberDashboard();
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

    async function render() {
      const members = await api("/members");
      count.textContent = `${members.length} ${members.length === 1 ? "record" : "records"}`;
      table.innerHTML = members.map((member) => `
        <tr>
          <td><strong>${esc(member.full_name)}</strong></td>
          <td>${esc(member.customer_id || "")}</td>
          <td>${esc(member.email)}</td>
          <td>${esc(member.pathway)}</td>
          <td>Level ${esc(member.level)}</td>
          <td>${esc(member.state)}</td>
          <td>
            <div class="tmp-row-actions">
              <button class="tmp-small-button" type="button" data-edit-member="${esc(member.id)}">Edit</button>
              <button class="tmp-small-button tmp-danger" type="button" data-delete-member="${esc(member.id)}">Delete</button>
            </div>
          </td>
        </tr>
      `).join("");
      root._members = members;
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await api("/members", {
        method: "POST",
        body: JSON.stringify(formData(form)),
      });
      clearForm(form);
      await render();
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
      await render();
    });

    qs("[data-tmp-clear-member]", root)?.addEventListener("click", () => clearForm(form));

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
        await api(`/members/${del.dataset.deleteMember}`, { method: "DELETE" });
        await render();
      }
    });

    await render();
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
      memberSelect.innerHTML = `<option value="">Unassigned</option>` + members.map((member) =>
        `<option value="${esc(member.id)}">${esc(member.full_name)}</option>`
      ).join("");
    }

    async function renderMeetings() {
      const meetings = await api("/meetings") || [];
      root._meetings = Array.isArray(meetings) ? meetings : [];
      
      meetingCount.textContent = `${meetings.length} ${meetings.length === 1 ? "meeting" : "meetings"}`;
      meetingSelect.innerHTML = '<option value="">Select a meeting...</option>' + 
        meetings.map((meeting) =>
        `<option value="${esc(meeting.id)}">${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</option>`
      ).join("");
      updateRoles();

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting) => {
        const [h, m] = (meeting.start_time || "18:30:00").split(':').map(Number);
        const startTimeInMins = h * 60 + m;
        let runningTime = startTimeInMins;

        const agendaHtml = (meeting.assignments || []).map((assignment) => {
          const start = formatTime(runningTime);
          runningTime += Number(assignment.duration || 0);
          const end = formatTime(runningTime);
          
          return `
              <li>
                <span>
                  <small class="tmp-time-tag">${start} - ${end}</small>
                  <strong>${esc(assignment.role_name)}</strong> - 
                  ${assignment.member_name ? esc(assignment.member_name) : "<em>Unassigned</em>"}
                  ${assignment.speech_title ? `<br><small>Title: ${esc(assignment.speech_title)}</small>` : ""}
                </span>
                <span>
                  ${assignment.status === 'Requested' ? `<button class="tmp-small-button" style="background:#2e7d32; color:white;" type="button" data-approve-assignment="${esc(assignment.id)}">Approve</button>` : ""}
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
            <span class="tmp-tag">${esc(meeting.start_time ? meeting.start_time.substring(0,5) : "18:30")}</span>
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
          </div>
        </article>
      `}).join("")}</div>`;
    }

    const updateRoles = () => {
      const meeting = (root._meetings || []).find(m => String(m.id) === meetingSelect.value);
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
      if (!val) {
        toggleSpeechTitle('');
        return;
      }

      const meeting = (root._meetings || []).find(m => String(m.id) === meetingSelect.value);
      let selectedRoleName = '';

      if (val.startsWith('id:')) {
        const id = val.split(':')[1];
        const assignment = meeting?.assignments.find(a => String(a.id) === id);
        if (assignment) {
          fillForm(assignmentForm, assignment);
          selectedRoleName = assignment.role_name;
        }
      } else if (val.startsWith('name:')) {
        clearForm(assignmentForm);
        assignmentForm.elements.meeting_id.value = meetingSelect.value;
        selectedRoleName = val.split(':')[1];
        assignmentForm._tmp_role_name = selectedRoleName; 
      }
      toggleSpeechTitle(selectedRoleName);
    });

    meetingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const btn = event.target.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;

      try {
        await api("/meetings", {
          method: "POST",
          body: JSON.stringify(formData(meetingForm)),
        });
        alert("Meeting created successfully with role templates!");
        clearForm(meetingForm);
        await renderMeetings();
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
        
        // Logic check: If we are using a template role, ensure the role_name is correctly set
        if (assignmentForm._tmp_role_name && !data.id) {
            data.role_name = assignmentForm._tmp_role_name;
        } else if (roleSelect.value.startsWith('id:')) {
            // If editing an existing slot, ensure we don't accidentally pass "id:XX" as the role name
            const id = roleSelect.value.split(':')[1];
            const meeting = root._meetings.find(m => String(m.id) === data.meeting_id);
            const assignment = meeting?.assignments.find(a => String(a.id) === id);
            if (assignment) data.role_name = assignment.role_name;
        }

        await api("/assignments", {
          method: "POST",
          body: JSON.stringify(data),
        });
        alert("Assignment saved.");
        delete assignmentForm._tmp_role_name;
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

      if (del) {
        await api(`/assignments/${del.dataset.deleteAssignment}`, { method: "DELETE" });
        await renderMeetings();
      } else if (approve) {
        await api("/assignments", {
          method: "POST",
          body: JSON.stringify({ id: approve.dataset.approveAssignment, status: "Confirmed" })
        });
        await renderMeetings();
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
