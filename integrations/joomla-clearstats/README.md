# ClearStats for Joomla

A Joomla **System** plugin that adds the ClearStats tracking snippet to every frontend page of a Joomla 4 or 5 site. It does not add cookies, browser storage, or any tracking behavior beyond what `track.js` itself does — see [`docs/tracking-guide.md`](../../docs/tracking-guide.md) in the main ClearStats repository for the full data model.

## Requirements

- Joomla **4.2+** or **5.x**. This plugin uses the modern namespaced plugin architecture (`services/provider.php`, PSR-4 `src/`) and will not install on Joomla 3, which uses an older, incompatible plugin structure.
- A ClearStats installation you control, with the site already created there (Sites → Add site in the ClearStats dashboard) so you have a Site ID.

## ⚠️ Verification status

This plugin is written against Joomla's documented plugin API and conventions, but it has **not been installed and click-tested against a running Joomla site** — there was no Joomla instance available in the environment it was built in. Before relying on it in production:

1. Install it on a staging Joomla site first.
2. Confirm the `<script>` tag appears in the page source with the attributes you expect (view source, or your browser's network tab, on a frontend page).
3. If you enabled **Track 404 pages**, deliberately visit a broken URL on the staging site and confirm the request to `/api/event` actually carries `data-404` (network tab). Joomla's exact timing of setting the HTTP 404 status relative to the `onBeforeCompileHead` event can vary by version/configuration — the plugin checks `http_response_code()` at that point, and if your Joomla sets it later in the request lifecycle, 404 tracking will silently not fire rather than fire on the wrong pages. If it doesn't work, the reliable fallback is a **dedicated site** in ClearStats for the error template, with the main snippet's `data-404` attribute added manually to that one template.

## Install

1. Build the installable zip: `php package.php` (needs the PHP `zip` extension). This writes `dist/plg_system_clearstats-<version>.zip` with `clearstats.xml` correctly placed at the zip root, not nested in a subfolder. Re-run it after any change to the plugin's source files.
2. In Joomla: **System → Manage → Install**, upload the zip.
3. **System → Manage → Plugins**, find **System - ClearStats**, open it.
4. Fill in:
   - **ClearStats installation URL** — the base URL of your self-hosted ClearStats instance (e.g. `https://analytics.example.com`).
   - **Site ID** — from this site's Install page in the ClearStats dashboard.
   - The three tracking toggles, if wanted (see the verification note above for 404).
5. **Enable** the plugin and save.

## What it does

On every frontend page render (`onBeforeCompileHead`), it adds:

```html
<script defer data-site-id="YOUR_SITE_ID" src="https://your-clearstats-instance/js/track.js"></script>
```

with `data-outbound-links`/`data-file-downloads`/`data-404` appended only when the corresponding toggle is on — matching exactly how the plugin's own `data-*` opt-in attributes work when installing `track.js` by hand. It never runs in the Joomla administrator.

## License

MIT, same as the rest of ClearStats. See `../../LICENSES/MIT.txt`.
