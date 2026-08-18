# theme-overrides/

`astra-child/` is a genuine WordPress **child theme** for the club's site.
It holds the home page template, its assets, and small site-wide tweaks
(admin bar hidden on the front end, `<head>` cleanup) — everything the club
owns and customizes, isolated from Astra itself.

## Why a child theme

A child theme lives in its own folder
(`wp-content/themes/astra-child/`) that WordPress guarantees the parent
theme's own updater will never touch — updating Astra only ever replaces
`wp-content/themes/astra/`. That means this entire folder can be tracked in
git and deployed as a single unit, with none of the fragility of the old
setup (see "History" below): no per-file symlinks, no separate
`functions-custom.php` + `require_once` trick, and nothing to manually
re-link after every Astra update.

`astra/` (the old, superseded setup) is kept in the repo for reference
until the live site is confirmed running on `astra-child/` — safe to delete
after that.

## Structure

```
astra-child/
  style.css              — required child theme header (Template: astra)
  functions.php          — enqueues Astra's parent style + club customizations
  page-toastmasters-portal.php  — the home page template
  assets/
    toastmasters-portal.css
    toastmasters-portal.js
    logo.png, hero-photo.jpeg, club-hero.png, pic 01–04.jpeg
```

Other site pages (member-dashboard, meeting-vote, speech-feedback,
member-login) are unaffected — they keep rendering through Astra's own
page template, header, footer, and menu, which the child theme inherits
unmodified via `get_template_directory_uri()` / the parent style enqueue
in `functions.php`.

## One-time server setup (migrating an already-deployed site)

Run once, over SSH. Adjust paths to match the real domain/deploy location.

```bash
REPO_PATH=~/domains/tmcpunenorthwest.com/public_html/wp-content/plugins/toastmasters-portal
THEMES_PATH=~/domains/tmcpunenorthwest.com/public_html/wp-content/themes

# Symlink the whole child theme folder as one unit — no per-file linking.
ln -s "$REPO_PATH/theme-overrides/astra-child" "$THEMES_PATH/astra-child"

# Verify
ls -la "$THEMES_PATH/astra-child"
```

Then in wp-admin:

1. **Appearance → Themes** → activate **"Toastmasters Club Astra Child"**.
2. **Pages → Home → Edit → Page Attributes → Template** → re-select
   **"Toastmasters Member Portal"** (switching the active theme resets which
   theme's templates are available/selected, so this needs re-picking once).
3. Load the home page (fresh/incognito) and confirm styling, images, and nav
   all look correct.
4. Load one non-home page (e.g. member-dashboard) and confirm it still looks
   normal — it should be unaffected, still rendering through Astra's own
   chrome via the parent theme.

Once confirmed working, the old per-file symlinks inside
`wp-content/themes/astra/` (from the superseded setup below) can be removed,
and `theme-overrides/astra/` can be deleted from this repo.

After this one-time setup, edit these files only through this repo
(locally, then commit/push) — the live site picks up changes immediately
via the symlink, no extra deploy step.

## Local development (this Windows checkout)

This repo's git config has `symlinks = false`, so a real symlink is never
created here — only on the Linux server per the steps above. Edit files
directly under `theme-overrides/astra-child/` in this repo.

---

## History: `astra/` — the old per-file-symlink setup (superseded)

The original approach symlinked 3 individual files (later grew to 10)
directly into Astra's own **parent** theme folder
(`wp-content/themes/astra/`), because the repo only tracked the plugin
directory and didn't want to widen Hostinger's deploy target to cover a
whole theme folder.

**This turned out to be fragile in a way that directly caused an outage
(2026-08-18):** an Astra theme update deletes and fully re-extracts
`wp-content/themes/astra/` from the update zip, which silently destroys
anything living inside it that isn't part of the official Astra package —
including every one of these symlinks. The home page lost its styling and
images, and a related customization (`functions.php`'s custom
admin-bar/`<head>`-cleanup snippet, which had never been tracked in git at
all) was lost outright and had to be recovered from a 7-day-old Hostinger
backup.

`functions.php` specifically could never be symlinked directly — Astra's
own update zip always ships a real file by that exact name, so a symlink
there would just get overwritten by Astra's fresh copy on every update
regardless. The workaround was a separate `functions-custom.php` file
(tracked in git) pulled in via one `require_once` line manually re-added
to the bottom of the live `functions.php` after each update — better than
nothing, but still manual, still forgettable, and still didn't protect the
other 9 files from the same wipe.

The child theme approach above (`astra-child/`) fixes all of this at the
architecture level rather than working around it file-by-file — kept here
only as history/context for why this migration happened.

### Old recovery script (for reference only)

```bash
REPO_PATH=~/domains/yoursite.com/public_html/wp-content/plugins/toastmasters-portal
THEME_PATH=~/domains/yoursite.com/public_html/wp-content/themes/astra

rm -f "$THEME_PATH/page-toastmasters-portal.php"
rm -f "$THEME_PATH/assets/toastmasters-portal.js"
rm -f "$THEME_PATH/assets/toastmasters-portal.css"
ln -s "$REPO_PATH/theme-overrides/astra/page-toastmasters-portal.php" "$THEME_PATH/page-toastmasters-portal.php"
ln -s "$REPO_PATH/theme-overrides/astra/assets/toastmasters-portal.js" "$THEME_PATH/assets/toastmasters-portal.js"
ln -s "$REPO_PATH/theme-overrides/astra/assets/toastmasters-portal.css" "$THEME_PATH/assets/toastmasters-portal.css"

for f in logo.png hero-photo.jpeg "pic 01.jpeg" "pic 02.jpeg" "pic 03.jpeg" "pic 04.jpeg" club-hero.png; do
  rm -f "$THEME_PATH/assets/$f"
  ln -s "$REPO_PATH/theme-overrides/astra/assets/$f" "$THEME_PATH/assets/$f"
done

rm -f "$THEME_PATH/functions-custom.php"
ln -s "$REPO_PATH/theme-overrides/astra/functions-custom.php" "$THEME_PATH/functions-custom.php"
# Then manually add to the bottom of the live functions.php:
#   require_once __DIR__ . '/functions-custom.php';
```
