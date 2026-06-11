(function () {
  'use strict';

  const cfg     = window.TMLogin || {};
  const form    = document.getElementById('tmp-login-form');
  const errorEl = document.getElementById('tmp-login-error');
  const googleBtn = document.getElementById('tmp-google-btn');

  function showError(msg) {
    if (!errorEl) return;
    errorEl.textContent = msg;
    errorEl.style.display = 'block';
    errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function clearError() {
    if (errorEl) errorEl.style.display = 'none';
  }

  // ── Password / Username login ─────────────────────────────────────────────

  if (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      clearError();

      const submitBtn = form.querySelector('.tmp-login-submit');
      const origText  = submitBtn.textContent;
      submitBtn.disabled    = true;
      submitBtn.textContent = 'Signing in…';

      const username   = (form.elements.username.value  || '').trim();
      const password   = (form.elements.password.value  || '');
      const remember   = form.elements.remember ? form.elements.remember.checked : false;
      const redirectTo = (form.elements.redirect_to ? form.elements.redirect_to.value : '') || cfg.redirectDefault || '/';

      if (!username || !password) {
        showError('Please enter your username and password.');
        submitBtn.disabled    = false;
        submitBtn.textContent = origText;
        return;
      }

      try {
        const resp = await fetch(cfg.loginUrl, {
          method:      'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   cfg.nonce,
          },
          body: JSON.stringify({ username, password, remember }),
        });

        const data = await resp.json();

        if (!resp.ok || !data.success) {
          showError(data.message || 'Invalid username or password. Please try again.');
          submitBtn.disabled    = false;
          submitBtn.textContent = origText;
          return;
        }

        // If a redirect_to was passed in the URL, honour it; otherwise use server's role-based redirect.
        window.location.href = (cfg.hasRedirectTo && redirectTo) ? redirectTo : (data.redirect || redirectTo);
      } catch (_err) {
        showError('Connection error. Please check your internet and try again.');
        submitBtn.disabled    = false;
        submitBtn.textContent = origText;
      }
    });
  }

  // ── Google OAuth ──────────────────────────────────────────────────────────

  if (googleBtn) {
    googleBtn.addEventListener('click', function () {
      const redirectTo = (new URLSearchParams(window.location.search)).get('redirect_to')
        || cfg.redirectDefault
        || '/';

      googleBtn.disabled    = true;
      googleBtn.textContent = 'Redirecting to Google…';

      window.location.href = cfg.googleUrl
        + '?redirect_to=' + encodeURIComponent(redirectTo);
    });
  }

  // ── Error messages from URL param (post-OAuth redirect) ───────────────────

  const urlParams = new URLSearchParams(window.location.search);
  const urlError  = urlParams.get('error');
  if (urlError && errorEl) {
    const messages = {
      google_failed:           'Google sign-in failed. Please try again.',
      google_no_account:       'No account found for that Google address. Contact your club admin.',
      google_email_unverified: 'Your Google email address is not verified.',
      google_not_configured:   'Google sign-in is not currently enabled. Use your username and password.',
    };
    showError(messages[urlError] || 'Sign-in failed. Please try again.');
  }

  // ── Password visibility toggle ────────────────────────────────────────────

  const toggleBtn = document.querySelector('.tmp-toggle-password');
  const pwdInput  = document.getElementById('tmp-password');

  if (toggleBtn && pwdInput) {
    toggleBtn.addEventListener('click', function () {
      const showing = pwdInput.type === 'text';
      pwdInput.type = showing ? 'password' : 'text';
      toggleBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      // Swap the eye icon fill to give visual feedback
      toggleBtn.style.color = showing ? '' : 'var(--tmp-teal, #0f766e)';
    });
  }
})();
