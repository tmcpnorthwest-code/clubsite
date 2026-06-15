(function () {
  'use strict';

  const API = (typeof TMPublic !== 'undefined' && TMPublic.restUrl) || '/wp-json/toastmasters/v1';

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }

  function qs(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function levelBadge(n) {
    return `<span class="tmp-level-badge tmp-level-badge--${n}">L${n}</span>`;
  }

  function relativeDate(dateStr) {
    const d   = new Date(dateStr.replace(' ', 'T'));
    const now = new Date();
    const days = Math.floor((now - d) / 86400000);
    if (days === 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 30)  return `${days} days ago`;
    if (days < 365) return `${Math.floor(days / 30)} months ago`;
    return d.toLocaleDateString('en-IN', { month: 'short', year: 'numeric' });
  }

  // ── Recognition wall (standalone [tm_recognition_wall] shortcode) ────────────

  function initRecognitionWall(root) {
    const list = qs('[data-tmp-level-ups-list]', root);
    if (!list) return;

    fetch(`${API}/public/recognition`)
      .then((r) => r.json())
      .then((data) => renderLevelUps(list, data.level_ups || []))
      .catch(() => { list.innerHTML = '<p style="color:#999">Could not load recognition data.</p>'; });
  }

  function renderLevelUps(container, items) {
    if (!items.length) {
      container.innerHTML = '<p style="color:var(--tmp-muted)">No level-ups recorded yet.</p>';
      return;
    }
    container.innerHTML = `<div class="tmp-recognition-grid">
      ${items.map((item) => `
        <div class="tmp-recognition-card">
          <div class="tmp-recognition-card__badges">
            ${levelBadge(item.old_level)} <span class="tmp-recognition-arrow">&#8594;</span> ${levelBadge(item.new_level)}
          </div>
          <strong class="tmp-recognition-card__name">${esc(item.member_name)}</strong>
          <small class="tmp-recognition-card__pathway">${esc(item.pathway)}</small>
          <span class="tmp-recognition-card__date">${esc(relativeDate(item.leveled_up_at))}</span>
        </div>
      `).join('')}
    </div>`;
  }

  // ── Meeting report ────────────────────────────────────────────────────────────

  function renderMeetingSummary(container, data) {
    if (!data) {
      container.innerHTML = '<p style="color:var(--tmp-muted)">No meeting data available yet.</p>';
      return;
    }

    const dist    = data.level_distribution || {};
    const total   = Object.values(dist).reduce((s, n) => s + n, 0);
    const colours = { '0': '#90a4ae', '1': '#0f766e', '2': '#18324a', '3': '#8f1737', '4': '#d69b24', '5': '#5c2d91' };
    const labels  = { '0': 'L0', '1': 'L1', '2': 'L2', '3': 'L3', '4': 'L4', '5': 'L5' };

    const barSegments = Object.entries(dist)
      .filter(([, cnt]) => cnt > 0)
      .map(([lvl, cnt]) => {
        const pct = total ? ((cnt / total) * 100).toFixed(1) : 0;
        return `<div class="tmp-level-bar" style="width:${pct}%;background:${colours[lvl] || '#ccc'};"
                     title="${labels[lvl]}: ${cnt} member${cnt !== 1 ? 's' : ''}">
                  <span class="tmp-level-bar__label">${labels[lvl]} ${cnt}</span>
                </div>`;
      }).join('');

    const rolesHtml = data.roles_covered && data.roles_covered.length
      ? data.roles_covered.map((r) => `<span class="tmp-tag">${esc(r)}</span>`).join(' ')
      : '<span style="color:var(--tmp-muted)">None recorded</span>';

    const levelUpsHtml = data.level_ups && data.level_ups.length
      ? `<div class="tmp-meeting-levelups">
          <strong>Level-ups this meeting:</strong>
          ${data.level_ups.map((u) => `
            <span class="tmp-meeting-levelup-item">
              ${esc(u.member_name)} ${levelBadge(u.old_level)} &#8594; ${levelBadge(u.new_level)}
            </span>`).join('')}
         </div>`
      : '';

    const dateStr = data.meeting_date
      ? new Date(data.meeting_date).toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
      : '—';

    container.innerHTML = `
      <div class="tmp-meeting-report">
        <div class="tmp-meeting-report__meta">
          <span class="tmp-eyebrow">${esc(dateStr)}</span>
          <h3 style="margin:4px 0 12px;">${esc(data.theme || 'Meeting')}</h3>
          <p style="margin:0 0 16px;color:var(--tmp-muted);">
            <strong>${data.participants || 0}</strong> participant${data.participants !== 1 ? 's' : ''}
          </p>
        </div>

        <div class="tmp-meeting-report__dist">
          <p class="tmp-eyebrow" style="margin-bottom:8px;">Club level distribution</p>
          <div class="tmp-level-dist">${barSegments || '<span style="color:var(--tmp-muted)">No data</span>'}</div>
          <div class="tmp-level-dist-legend">
            ${Object.entries(dist).filter(([, c]) => c > 0).map(([lvl, cnt]) =>
              `<span style="color:${colours[lvl] || '#ccc'}">&#9632; ${labels[lvl]}: ${cnt}</span>`
            ).join('')}
          </div>
        </div>

        <div class="tmp-meeting-report__roles">
          <p class="tmp-eyebrow" style="margin-bottom:8px;">Roles covered</p>
          <div>${rolesHtml}</div>
        </div>

        ${levelUpsHtml}
      </div>`;
  }

  // ── Role diversity leaders ────────────────────────────────────────────────────

  function renderDiversityLeaders(container, leaders) {
    if (!leaders.length) {
      container.innerHTML = '<p style="color:var(--tmp-muted)">No participation history yet.</p>';
      return;
    }
    container.innerHTML = `
      <ol class="tmp-diversity-list">
        ${leaders.map((m, i) => `
          <li class="tmp-diversity-row">
            <span class="tmp-diversity-rank">${i + 1}</span>
            <div class="tmp-diversity-info">
              <strong>${esc(m.full_name)}</strong>
              <small>${esc(m.pathway)} &middot; ${levelBadge(m.level)}</small>
            </div>
            <div class="tmp-diversity-roles">
              <span class="tmp-diversity-count">${m.distinct_roles} roles</span>
              <small class="tmp-diversity-role-list">${esc(m.roles_played || '')}</small>
            </div>
          </li>`).join('')}
      </ol>`;
  }

  // ── Full public dashboard (mounts on [data-tmp-public-dashboard]) ─────────────

  function initPublicDashboard(root) {
    const levelUpsEl  = qs('[data-tmp-public-level-ups]', root);
    const meetingEl   = qs('[data-tmp-public-meeting]', root);
    const diversityEl = qs('[data-tmp-public-diversity]', root);

    Promise.all([
      fetch(`${API}/public/recognition`).then((r) => r.json()).catch(() => ({ level_ups: [] })),
      fetch(`${API}/public/meeting-summary`).then((r) => r.ok ? r.json() : null).catch(() => null),
      fetch(`${API}/public/role-diversity`).then((r) => r.json()).catch(() => ({ leaders: [] })),
    ]).then(([recog, meeting, diversity]) => {
      if (levelUpsEl)  renderLevelUps(levelUpsEl, recog.level_ups || []);
      if (meetingEl)   renderMeetingSummary(meetingEl, meeting);
      if (diversityEl) renderDiversityLeaders(diversityEl, diversity.leaders || []);
    });
  }

  // ── Boot ──────────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    const dashboard = document.querySelector('[data-tmp-public-dashboard]');
    if (dashboard) initPublicDashboard(dashboard);

    const wall = document.querySelector('[data-tmp-recognition-wall]');
    if (wall && !dashboard) initRecognitionWall(wall);
  });
}());
