# theme-overrides/

This tracks the few files we own inside the Astra theme — the club's home page
template and its assets. Everything else in `wp-content/themes/astra/` is
stock Astra, server-managed, and not tracked here (same reasoning as the
`plugins/*` exclusion in `.gitignore`: don't check in vendor/third-party
code we don't author).

## Why not just track `wp-content/themes/astra/` directly?

Hostinger's deploy only points at this repo's `plugins/` output, and the
theme folder is large (stock Astra + builds). Rather than widen the deploy
target, the 3 files we actually maintain live here instead, and are
**symlinked** from their real location in `wp-content/themes/astra/` to
this repo's checked-out copy on the server. A `git pull` updates the repo
copy; the symlink means the live site picks it up immediately with no
extra copy/deploy step.

## Files

- `astra/page-toastmasters-portal.php` → symlinked to
  `wp-content/themes/astra/page-toastmasters-portal.php`
- `astra/assets/toastmasters-portal.js` → symlinked to
  `wp-content/themes/astra/assets/toastmasters-portal.js`
- `astra/assets/toastmasters-portal.css` → symlinked to
  `wp-content/themes/astra/assets/toastmasters-portal.css`

## One-time server setup (already-deployed sites)

Run once, over SSH, on the Hostinger server. Adjust `REPO_PATH` to wherever
this repo is checked out on the server, and `THEME_PATH` to the live
`wp-content/themes/astra` directory.

```bash
REPO_PATH=~/path/to/clubsite
THEME_PATH=~/domains/yoursite.com/public_html/wp-content/themes/astra

# Back up whatever's currently live before replacing with a symlink
cp "$THEME_PATH/page-toastmasters-portal.php" "$THEME_PATH/page-toastmasters-portal.php.bak"
cp "$THEME_PATH/assets/toastmasters-portal.js" "$THEME_PATH/assets/toastmasters-portal.js.bak"
cp "$THEME_PATH/assets/toastmasters-portal.css" "$THEME_PATH/assets/toastmasters-portal.css.bak"

rm "$THEME_PATH/page-toastmasters-portal.php"
rm "$THEME_PATH/assets/toastmasters-portal.js"
rm "$THEME_PATH/assets/toastmasters-portal.css"

ln -s "$REPO_PATH/theme-overrides/astra/page-toastmasters-portal.php" "$THEME_PATH/page-toastmasters-portal.php"
ln -s "$REPO_PATH/theme-overrides/astra/assets/toastmasters-portal.js" "$THEME_PATH/assets/toastmasters-portal.js"
ln -s "$REPO_PATH/theme-overrides/astra/assets/toastmasters-portal.css" "$THEME_PATH/assets/toastmasters-portal.css"

# Verify
ls -la "$THEME_PATH/page-toastmasters-portal.php" "$THEME_PATH/assets/toastmasters-portal.js" "$THEME_PATH/assets/toastmasters-portal.css"
```

After this one-time setup, edit these files only through this repo
(locally, then commit/push) — editing the live symlinked path works too
since it's the same file, but changes made only on the server and never
committed here will be silently lost on the next deploy that touches
these paths.

## Local development (this Windows checkout)

This repo's git config has `symlinks = false`, so a real symlink is never
created here — only on the Linux server per the steps above. Locally, keep
editing the actual files under
`C:\Toast Masters\wp-content\themes\astra\...` (outside this repo) as
before, and copy any change into `theme-overrides/astra/...` before
committing, so the tracked copy and the local live copy stay in sync.
