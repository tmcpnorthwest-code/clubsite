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
    return Object.fromEntries(new FormData(form).entries());
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

  async function initMemberDashboard() {
    const root = qs("[data-tmp-member-dashboard]");
    if (!root) {
      return;
    }

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
    } catch (error) {
      root.innerHTML = `<div class="tmp-panel"><h2>Dashboard unavailable</h2><p>${esc(error.message)}</p></div>`;
    }
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
      const meetings = await api("/meetings");
      meetingCount.textContent = `${meetings.length} ${meetings.length === 1 ? "meeting" : "meetings"}`;
      meetingSelect.innerHTML = meetings.map((meeting) =>
        `<option value="${esc(meeting.id)}">${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</option>`
      ).join("");

      meetingList.innerHTML = `<div class="tmp-agenda">${meetings.map((meeting) => `
        <article class="tmp-agenda-card">
          <h4>${esc(meeting.meeting_date)} - ${esc(meeting.theme)}</h4>
          <p>${esc(meeting.venue || "Venue not set")}</p>
          <p>${esc(meeting.agenda_notes || "")}</p>
          <ul class="tmp-assignment-list">
            ${(meeting.assignments || []).map((assignment) => `
              <li>
                <span>
                  <strong>${esc(assignment.role_name)}</strong>
                  ${assignment.member_name ? ` - ${esc(assignment.member_name)}` : ""}
                  ${assignment.speech_title ? `<br>${esc(assignment.speech_title)}` : ""}
                </span>
                <span>
                  ${esc(assignment.status)}
                  <button class="tmp-small-button tmp-danger" type="button" data-delete-assignment="${esc(assignment.id)}">Delete</button>
                </span>
              </li>
            `).join("") || "<li><span>No roles scheduled yet.</span></li>"}
          </ul>
        </article>
      `).join("")}</div>`;
    }

    meetingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      await api("/meetings", {
        method: "POST",
        body: JSON.stringify(formData(meetingForm)),
      });
      clearForm(meetingForm);
      await renderMeetings();
    });

    assignmentForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      await api("/assignments", {
        method: "POST",
        body: JSON.stringify(formData(assignmentForm)),
      });
      clearForm(assignmentForm);
      await renderMeetings();
    });

    qs("[data-tmp-clear-meeting]", root)?.addEventListener("click", () => clearForm(meetingForm));
    qs("[data-tmp-clear-assignment]", root)?.addEventListener("click", () => clearForm(assignmentForm));

    meetingList.addEventListener("click", async (event) => {
      const button = event.target.closest("[data-delete-assignment]");
      if (!button) {
        return;
      }
      await api(`/assignments/${button.dataset.deleteAssignment}`, { method: "DELETE" });
      await renderMeetings();
    });

    await renderMembers();
    await renderMeetings();
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
