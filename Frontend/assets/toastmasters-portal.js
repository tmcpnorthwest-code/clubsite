(function () {
  'use strict';

  // ── Mobile nav ───────────────────────────────────────────────────────────────
  const menuButton = document.querySelector('[data-menu-toggle]');
  const nav        = document.querySelector('.nav');
  menuButton?.addEventListener('click', () => nav.classList.toggle('open'));
  nav?.addEventListener('click', (e) => { if (e.target.tagName === 'A') nav.classList.remove('open'); });

  // ── Enrolment form ───────────────────────────────────────────────────────────
  const enrolForm  = document.querySelector('[data-tmc-enrol-form]');
  const formStatus = document.querySelector('[data-tmc-form-status]');
  enrolForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(enrolForm).entries());
    if (formStatus) {
      formStatus.textContent = `Application received for ${data.name}. Our VP Membership will contact you at ${data.email}.`;
      formStatus.style.color = '#0f766e';
    }
    enrolForm.reset();
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
    setInterval(pollNominees, 30000);
  }
}());
