(function () {
  'use strict';

  // ── Mobile nav ───────────────────────────────────────────────────────────────
  const menuButton = document.querySelector('[data-menu-toggle]');
  const nav        = document.querySelector('.nav');
  menuButton?.addEventListener('click', () => nav.classList.toggle('open'));
  nav?.addEventListener('click', (e) => { if (e.target.tagName === 'A') nav.classList.remove('open'); });

  // ── "Club Activity" nav dropdown ────────────────────────────────────────────
  document.querySelectorAll('[data-nav-dropdown]').forEach((dropdown) => {
    const toggle = dropdown.querySelector('[data-nav-dropdown-toggle]');
    toggle?.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('is-open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('[data-nav-dropdown].is-open').forEach((d) => d.classList.remove('is-open'));
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-nav-dropdown].is-open').forEach((d) => d.classList.remove('is-open'));
    }
  });

  // ── Meeting-day voting card ───────────────────────────────────────────────────
  const voteSection = document.querySelector('[data-tmc-vote-meeting]');
  if (voteSection) {
    const meetingId = parseInt(voteSection.dataset.tmcVoteMeeting, 10);
    const REST      = (typeof TMCPublic !== 'undefined' && TMCPublic.restUrl) || voteSection.dataset.tmcRest || '/wp-json/toastmasters/v1';
    const VOTED_KEY = (cat) => 'tmc_voted_' + meetingId + '_' + cat;

    // Unique per-browser token so members on the same WiFi don't share an IP-based token
    function getDeviceToken() {
      let t = localStorage.getItem('tmc_device_token');
      if (!t) {
        t = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        localStorage.setItem('tmc_device_token', t);
      }
      return t;
    }

    function esc(str) {
      const d = document.createElement('div');
      d.textContent = String(str ?? '');
      return d.innerHTML;
    }

    // Poll for updated nominees every 30 s (catches VPE-added TT speakers)
    function pollNominees() {
      fetch(REST + '/voting/nominees/' + meetingId)
        .then((r) => r.json())
        .then((data) => {
          if (!data.voting_open) return;
          updateVoteGrid(data.nominees);
        })
        .catch(() => {});
    }

    function updateVoteGrid(nominees) {
      ['main_role', 'aux_role', 'table_topics', 'speaker', 'evaluator'].forEach((cat) => {
        const card  = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
        if (!card) return;
        const list  = card.querySelector('[data-vote-list]');
        const empty = card.querySelector('[data-vote-empty]');
        const items = nominees[cat] || [];
        if (!items.length) return;

        // If we just got nominees for the first time (empty state was shown), build the list
        if (empty) empty.remove();
        if (!list) {
          const ul = document.createElement('ul');
          ul.className = 'vote-nominee-list';
          ul.dataset.voteList = '';
          buildNomineeList(ul, items, cat);
          card.appendChild(ul);
        } else {
          // Merge: add any new nominees (TT speakers added live)
          const existing = new Set([...list.querySelectorAll('input[type="radio"]')].map((r) => r.value));
          items.forEach((nom) => {
            if (!existing.has(String(nom.id))) {
              list.appendChild(buildNomineeLi(nom, cat));
            }
          });
          // Update vote counts if already voted in this category
          if (localStorage.getItem(VOTED_KEY(cat))) {
            items.forEach((nom) => {
              const countEl = list.querySelector('[data-vote-count="' + nom.id + '"]');
              if (countEl) countEl.textContent = nom.vote_count + ' vote' + (nom.vote_count !== 1 ? 's' : '');
            });
          }
        }
      });
    }

    const CATS          = ['main_role', 'aux_role', 'table_topics', 'speaker', 'evaluator'];
    const mainSubmitBtn = voteSection.querySelector('#tmc-vote-submit');
    const allStatus     = voteSection.querySelector('[data-vote-all-status]');

    function buildNomineeLi(nom, cat) {
      const li = document.createElement('li');
      li.className = 'vote-nominee';
      li.dataset.nomineeId = nom.id;
      const voted = !!localStorage.getItem(VOTED_KEY(cat));
      li.innerHTML = `
        <label class="vote-option${voted ? ' voted' : ''}">
          <input type="radio" name="vote_${esc(cat)}" value="${nom.id}"${voted ? ' disabled' : ''} />
          <span class="vote-name">${esc(nom.display_name)}</span>
          <span class="vote-role">${esc(nom.role_name)}</span>
        </label>
        <span class="vote-count" data-vote-count="${nom.id}" style="${voted ? '' : 'display:none;'}">${nom.vote_count} vote${nom.vote_count !== 1 ? 's' : ''}</span>`;
      return li;
    }

    function buildNomineeList(ul, items, cat) {
      items.forEach((nom) => ul.appendChild(buildNomineeLi(nom, cat)));
    }

    function markCatVoted(cat) {
      const card = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
      if (!card) return;
      card.querySelectorAll('input[type="radio"]').forEach((r) => { r.disabled = true; });
      card.querySelectorAll('.vote-option').forEach((el) => el.classList.add('voted'));
      card.querySelectorAll('[data-vote-count]').forEach((el) => { el.style.display = ''; });
    }

    function refreshMainBtn() {
      if (!mainSubmitBtn) return;
      if (CATS.every((c) => !!localStorage.getItem(VOTED_KEY(c)))) {
        mainSubmitBtn.style.display = 'none';
        if (allStatus && !allStatus.textContent) allStatus.textContent = '✓ Your votes are recorded. Thank you!';
      }
    }

    const CAT_LABELS = { main_role: 'Best Main Role', aux_role: 'Best Auxiliary Role', table_topics: 'Best Table Topics', speaker: 'Best Speaker', evaluator: 'Best Evaluator' };

    function castAllVotes() {
      CATS.forEach((cat) => {
        const card = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
        if (card) card.classList.remove('vote-card--missing');
      });

      const missing  = [];
      const toSubmit = [];

      CATS.forEach((cat) => {
        const card    = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
        const hasList = card && card.querySelector('[data-vote-list]');
        if (!hasList) return;                                       // no nominees yet — skip silently
        if (localStorage.getItem(VOTED_KEY(cat))) return;          // already voted this session
        const sel = card.querySelector('input[type="radio"]:checked');
        if (!sel) { missing.push(cat); }
        else      { toSubmit.push({ cat, nomineeId: parseInt(sel.value, 10) }); }
      });

      if (missing.length) {
        missing.forEach((cat) => {
          const card = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
          if (card) card.classList.add('vote-card--missing');
        });
        if (allStatus) allStatus.textContent = 'Please pick a nominee in: ' + missing.map((c) => CAT_LABELS[c]).join(', ');
        return;
      }
      if (!toSubmit.length) {
        if (allStatus) allStatus.textContent = '✓ All categories already voted.';
        return;
      }

      if (mainSubmitBtn) mainSubmitBtn.disabled = true;
      if (allStatus) allStatus.textContent = 'Submitting votes…';

      const deviceToken = getDeviceToken();

      Promise.all(toSubmit.map(({ cat, nomineeId }) =>
        fetch(REST + '/voting/vote', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ meeting_id: meetingId, nominee_id: nomineeId, voter_token: deviceToken }),
        }).then((r) => r.json()).then((data) => ({ cat, data }))
      ))
      .then((results) => {
        const errs = [];
        results.forEach(({ cat, data }) => {
          if (data.code === 'already_voted') { errs.push(CAT_LABELS[cat]); return; }
          localStorage.setItem(VOTED_KEY(cat), '1');
          markCatVoted(cat);
          const card = voteSection.querySelector('[data-vote-cat="' + cat + '"]');
          ((data.nominees || {})[cat] || []).forEach((nom) => {
            const el = card && card.querySelector('[data-vote-count="' + nom.id + '"]');
            if (el) el.textContent = nom.vote_count + ' vote' + (nom.vote_count !== 1 ? 's' : '');
          });
        });
        if (errs.length) {
          if (allStatus) allStatus.textContent = 'Already voted in: ' + errs.join(', ');
          if (mainSubmitBtn) mainSubmitBtn.disabled = false;
        } else {
          if (allStatus) allStatus.textContent = '✓ Votes recorded! Thank you.';
        }
        refreshMainBtn();
      })
      .catch(() => {
        if (mainSubmitBtn) mainSubmitBtn.disabled = false;
        if (allStatus) allStatus.textContent = 'Something went wrong. Please try again.';
      });
    }

    // Init: mark already-voted categories; wire single submit button
    CATS.forEach((cat) => { if (localStorage.getItem(VOTED_KEY(cat))) markCatVoted(cat); });
    refreshMainBtn();
    if (mainSubmitBtn) mainSubmitBtn.addEventListener('click', castAllVotes);

    pollNominees(); // immediate first poll to get latest TT speakers
    setInterval(function() {
      if (!document.hidden) pollNominees();
    }, 30000);
  }

  // ── Upcoming Meeting Agenda (always fetched fresh — bypasses page cache) ─────
  (function renderAgenda() {
    var mount = document.getElementById('tmc-upcoming');
    if (!mount) return;

    var cfg      = window.TMCPublic || {};
    var REST     = (cfg.restUrl || '').replace(/\/$/, '');

    function esc(v) {
      var d = document.createElement('div');
      d.textContent = String(v == null ? '' : v);
      return d.innerHTML;
    }

    function escNl2br(v) {
      return String(v || '').split('\n').map(esc).join('<br>');
    }

    function parseTimeMins(t) {
      if (!t) return null;
      var parts = String(t).split(':').map(Number);
      return parts[0] * 60 + (parts[1] || 0);
    }

    function fmtMins(totalMins) {
      if (totalMins === null || totalMins === undefined) return '';
      var h    = Math.floor(totalMins / 60);
      var m    = totalMins % 60;
      var ampm = h < 12 ? 'AM' : 'PM';
      var h12  = h % 12 || 12;
      return h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
    }

    fetch(REST + '/meetings/published-agenda')
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(a) {
        if (!a) return;

        // Parse date without timezone shift
        var parts = String(a.meeting_date).split('-').map(Number);
        var dt    = new Date(parts[0], parts[1] - 1, parts[2]);
        var days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        var dateFmt = days[dt.getDay()] + ', ' + months[dt.getMonth()] + ' ' + dt.getDate();

        // Compute wall-clock times for each slot
        var startMins = parseTimeMins(a.start_time);
        var clock     = startMins;
        var rows      = a.assignments || [];
        var rowTimes  = rows.map(function(row) {
          var t   = clock;
          var dur = Math.max(0, parseInt(row.duration || 0, 10));
          if (clock !== null && dur > 0) clock += dur;
          return t;
        });
        var endMins = clock;

        var startFmt = startMins !== null ? fmtMins(startMins) : '';
        var endFmt   = (endMins !== null && startMins !== null) ? fmtMins(endMins) : '';

        // Ordinal suffix helper (476th, 477th, etc.)
        function ordinalSuffix(n) {
          var s = ['th','st','nd','rd'], v = n % 100;
          return n + (s[(v - 20) % 10] || s[v] || s[0]);
        }

        // Meta header
        var metaHtml = '';
        if (a.chapter_number) {
          metaHtml += '<span class=”upcoming-chapter”>' + esc(ordinalSuffix(parseInt(a.chapter_number, 10)) + ' Chapter Meeting') + '</span>';
        }
        metaHtml += '<span class=”upcoming-date”>' + esc(dateFmt) + '</span>';
        if (a.theme)    metaHtml += '<span class=”upcoming-theme”>”' + esc(a.theme) + '”</span>';
        if (a.venue)    metaHtml += '<span class=”upcoming-venue”>' + esc(a.venue) + '</span>';
        if (a.maps_url) metaHtml += '<a class=”upcoming-directions” href=”' + esc(a.maps_url) + '” target=”_blank” rel=”noopener noreferrer”>Get directions &#x2197;</a>';
        if (startFmt) {
          var timeStr = startFmt + (endFmt ? ' – ' + endFmt : '');
          metaHtml += '<span class=”upcoming-time”>' + esc(timeStr) + '</span>';
        }

        // Agenda rows — filter and render
        var rowsHtml = '';
        rows.forEach(function(row, idx) {
          var noteMatch = String(row.role_name).match(/\(([^)]+)\)/);
          var slotNote  = noteMatch ? noteMatch[1] : '';
          if (/^Introduces?\s/i.test(slotNote)) return;

          var slotBase = String(row.role_name).replace(/\s*\(.*\)/g, '').trim().toLowerCase();
          var isBreak  = (slotBase === 'break');
          var timeStr  = rowTimes[idx] !== null ? fmtMins(rowTimes[idx]) : '';
          var slotDur  = Math.max(0, parseInt(row.duration || 0, 10));

          if (isBreak) {
            rowsHtml += '<tr class="upcoming-break-row">' +
              '<td class="upcoming-slot-time">' + esc(timeStr) + '</td>' +
              '<td colspan="3" class="upcoming-break-label">&#9749; Break &mdash; Networking' +
              (slotDur > 0 ? ' <span class="upcoming-break-dur">(' + slotDur + ' min)</span>' : '') +
              '</td></tr>';
          } else {
            var durDisplay = '';
            var tg = parseInt(row.time_green || 0, 10);
            var tr = parseInt(row.time_red   || 0, 10);
            if (tg > 0) {
              var gMin = Math.round(tg / 60);
              var rMin = Math.round(tr / 60);
              durDisplay = gMin === rMin ? gMin + ' min' : gMin + '–' + rMin + ' min';
            } else if (slotDur > 0) {
              durDisplay = slotDur + ' min';
            }
            var memberCell  = row.member_name ? esc(row.member_name) : '<em class="upcoming-tba">TBA</em>';
            var speechTitle = row.speech_title ? '<span class="upcoming-speech-title">' + esc(row.speech_title) + '</span>' : '';
            var pathwayTag  = row.pathway_label ? '<span class="upcoming-pathway">[' + esc(row.pathway_label) + ']</span>' : '';

            rowsHtml += '<tr>' +
              '<td class="upcoming-slot-time">' + esc(timeStr) + '</td>' +
              '<td>' + esc(row.role_name) + speechTitle + pathwayTag + '</td>' +
              '<td>' + memberCell + '</td>' +
              '<td class="upcoming-dur">' + esc(durDisplay) + '</td>' +
              '</tr>';
          }
        });

        mount.innerHTML =
          '<p class="eyebrow">Coming Up Next</p>' +
          '<h2>Meeting Agenda</h2>' +
          '<div class="upcoming-meta">' + metaHtml + '</div>' +
          (a.agenda_notes ? '<p class="upcoming-agenda-notes">' + escNl2br(a.agenda_notes) + '</p>' : '') +
          (rowsHtml
            ? '<div class="upcoming-agenda-wrap"><table class="upcoming-agenda-table">' +
              '<thead><tr><th class="col-time">Time</th><th>Agenda Item</th><th>Member</th><th class="col-dur">Duration</th></tr></thead>' +
              '<tbody>' + rowsHtml + '</tbody></table></div>'
            : '');

        mount.style.display = '';
      })
      .catch(function() {});
  }());

  // ── Meeting Pulse live refresh ────────────────────────────────────────────
  (function pollPulse() {
    const pulseSection = document.querySelector('[data-tmc-pulse]');
    if (!pulseSection) return;

    var cfg      = window.TMCPublic || window.TMPortal || {};
    var REST_BASE = (cfg.restUrl || '').replace(/\/$/, '') + '/';
    var nonce     = cfg.nonce || '';

    function esc(v) {
      return String(v || '').replace(/[&<>"']/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];
      });
    }

    const CAT_LABELS = {
      main_role:'Best Main Role', aux_role:'Best Auxiliary Role',
      table_topics:'Best Table Topics', speaker:'Best Speaker', evaluator:'Best Evaluator'
    };

    function fetchPulse() {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', REST_BASE + 'meetings/pulse', true);
      if (nonce) xhr.setRequestHeader('X-WP-Nonce', nonce);
      xhr.onload = function() {
        if (xhr.status !== 200) return;
        try { applyPulse(JSON.parse(xhr.responseText)); } catch(e) {}
      };
      xhr.send();
    }

    function applyPulse(data) {
      // Attendance
      var attEl = pulseSection.querySelector('[data-tmc-pulse-attendance]');
      if (attEl) {
        var cnt = parseInt(data.attendance_count || data.participants || 0, 10);
        attEl.innerHTML = cnt + '<span>members</span>';
      }
      // Guests
      var gEl = pulseSection.querySelector('[data-tmc-pulse-guests]');
      if (gEl) {
        var gc = parseInt(data.guest_count || 0, 10);
        gEl.textContent = gc ? gc + ' guest' + (gc !== 1 ? 's' : '') : 'No guests';
      }
      // Roles
      var rEl = pulseSection.querySelector('[data-tmc-pulse-roles]');
      if (rEl && Array.isArray(data.roles_covered)) {
        if (data.roles_covered.length) {
          rEl.innerHTML = data.roles_covered.map(function(r) {
            return '<span class="role-tag">' + esc(r) + '</span>';
          }).join('');
        } else {
          rEl.innerHTML = '<p style="color:#999;font-size:0.88rem;">No roles recorded yet.</p>';
        }
      }
      // Winners
      var wEl = pulseSection.querySelector('[data-tmc-pulse-winners]');
      if (wEl && Array.isArray(data.winners)) {
        var eyebrow = wEl.querySelector('.eyebrow');
        wEl.innerHTML = '';
        if (eyebrow) wEl.appendChild(eyebrow);
        else { var ep = document.createElement('p'); ep.className='eyebrow'; ep.textContent='🏆 Meeting Winners'; wEl.appendChild(ep); }
        if (data.winners.length) {
          var ul = document.createElement('ul');
          ul.className = 'pulse-winners-list';
          data.winners.forEach(function(w) {
            var li = document.createElement('li');
            li.className = 'pulse-winner-row';
            li.innerHTML = '<span class="pulse-winner-cat">' + esc(CAT_LABELS[w.category] || w.category) + '</span>' +
              '<span class="pulse-winner-name">' + esc(w.display_name) + '</span>' +
              (w.role_name ? '<span class="pulse-winner-role">' + esc(w.role_name) + '</span>' : '');
            ul.appendChild(li);
          });
          wEl.appendChild(ul);
        } else {
          var ph = document.createElement('p');
          ph.style.cssText = 'color:#999;font-size:0.88rem;margin-top:8px;';
          ph.textContent = 'Winners will appear after the meeting wrap-up.';
          wEl.appendChild(ph);
        }
      }
    }

    // Poll every 60 seconds; skip when tab is hidden to avoid background load
    fetchPulse();
    setInterval(function() {
      if (!document.hidden) fetchPulse();
    }, 60000);
  }());

  // ── Leaderboard (TM of Month / Quarter — public, live-scored) ────────────────
  (function renderLeaderboard() {
    var section = document.querySelector('[data-tmc-leaderboard]');
    if (!section) return;

    var cfg  = window.TMCPublic || {};
    var REST = (cfg.restUrl || '').replace(/\/$/, '');

    var listEl    = section.querySelector('[data-lb-list]');
    var tabs      = section.querySelectorAll('[data-lb-tab]');
    var viewAllBtn = section.querySelector('[data-lb-view-all]');

    var modal      = document.querySelector('[data-lb-modal]');
    var modalTabs  = modal ? modal.querySelectorAll('[data-lb-modal-tab]') : [];
    var modalTable = modal ? modal.querySelector('[data-lb-modal-table]') : null;
    var modalClose = modal ? modal.querySelector('[data-lb-modal-close]') : null;

    var cache = {}; // period -> response

    function esc(v) {
      var d = document.createElement('div');
      d.textContent = String(v == null ? '' : v);
      return d.innerHTML;
    }

    function fetchPeriod(period) {
      if (cache[period]) return Promise.resolve(cache[period]);
      return fetch(REST + '/public/leaderboard?period=' + period + '&limit=100')
        .then(function (r) { return r.json(); })
        .then(function (data) { cache[period] = data; return data; })
        .catch(function () { return { leaders: [] }; });
    }

    function renderBoard(period) {
      listEl.innerHTML = '<p style="color:#999;">Loading...</p>';
      fetchPeriod(period).then(function (data) {
        var leaders = (data.leaders || []).slice(0, 5);
        if (!leaders.length) {
          listEl.innerHTML = '<p style="color:#999;">No scores yet for this period.</p>';
          return;
        }
        listEl.innerHTML = leaders.map(function (m, i) {
          return '<div class="leaderboard-row">' +
            '<span class="leaderboard-rank">' + (i + 1) + '</span>' +
            '<div class="leaderboard-info">' +
              '<strong>' + esc(m.member_name) + '</strong>' +
              '<small>' + esc(m.pathway || '') + '</small>' +
            '</div>' +
            '<span class="leaderboard-score">' + esc(m.score) + ' <small>pts</small></span>' +
          '</div>';
        }).join('');
      });
    }

    function scoreCell(score, max, cls) {
      var pct = Math.max(0, Math.min(100, (score / max) * 100));
      return '<td class="lb-cell">' +
        '<div class="lb-cell-track"><div class="lb-cell-fill ' + cls + '" style="width:' + pct + '%;"></div></div>' +
        '<span class="lb-cell-value">' + esc(score) + '</span>' +
      '</td>';
    }

    function renderModalTable(period) {
      modalTable.innerHTML = '<p style="color:#999;">Loading...</p>';
      fetchPeriod(period).then(function (data) {
        var leaders = data.leaders || [];
        if (!leaders.length) {
          modalTable.innerHTML = '<p style="color:#999;">No scores yet for this period.</p>';
          return;
        }
        var rows = leaders.map(function (m, i) {
          var b = m.breakdown || {};
          var mentorScore = b.mentor_avg_rating != null ? b.mentor_score : 0;
          return '<tr class="' + (i < 3 ? 'lb-top' + (i + 1) : '') + '">' +
            '<td class="lb-rank">' + (i + 1) + '</td>' +
            '<td class="lb-name">' + esc(m.member_name) + '</td>' +
            scoreCell(b.attendance_score, 40, 'lb-fill-attendance') +
            scoreCell(b.service_score, 40, 'lb-fill-service') +
            scoreCell(b.win_score, 10, 'lb-fill-wins') +
            scoreCell(b.level_up_score, 5, 'lb-fill-levelup') +
            scoreCell(mentorScore, 5, 'lb-fill-mentor') +
            '<td class="lb-total">' + esc(m.score) + '</td>' +
          '</tr>';
        }).join('');
        modalTable.innerHTML =
          '<table class="leaderboard-table">' +
            '<thead><tr>' +
              '<th>#</th>' +
              '<th>Name</th>' +
              '<th>Attendance<span class="lb-th-max">/ 40</span></th>' +
              '<th>Service<span class="lb-th-max">/ 40</span></th>' +
              '<th>Wins<span class="lb-th-max">/ 10</span></th>' +
              '<th>Level-Up<span class="lb-th-max">/ 5</span></th>' +
              '<th>Mentor<span class="lb-th-max">/ 5</span></th>' +
              '<th>Total<span class="lb-th-max">/ 100</span></th>' +
            '</tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
          '</table>' +
          '<p class="lb-legend">Attendance &amp; Service = meetings rate &times; weight &middot; Service includes qualifying speeches once you attend consistently &middot; Wins = vote rate &times; weight &middot; Level-Up = flat bonus &middot; Mentor = mentee rating &times; weight</p>';
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
        tab.classList.add('is-active');
        tab.setAttribute('aria-selected', 'true');
        renderBoard(tab.dataset.lbTab);
      });
    });

    if (modal) {
      modalTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          modalTabs.forEach(function (t) { t.classList.remove('is-active'); t.setAttribute('aria-selected', 'false'); });
          tab.classList.add('is-active');
          tab.setAttribute('aria-selected', 'true');
          renderModalTable(tab.dataset.lbModalTab);
        });
      });

      viewAllBtn?.addEventListener('click', function () {
        var activeTab = section.querySelector('[data-lb-tab].is-active');
        var period = activeTab ? activeTab.dataset.lbTab : 'month';
        modalTabs.forEach(function (t) {
          var isMatch = t.dataset.lbModalTab === period;
          t.classList.toggle('is-active', isMatch);
          t.setAttribute('aria-selected', isMatch ? 'true' : 'false');
        });
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        renderModalTable(period);
      });

      modalClose?.addEventListener('click', closeModal);
      modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });

      function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    }

    renderBoard('month');
  }());
}());
