# Admin panel security audit (06.10.2026)

This document is for the site owner and the developer. The improvement plan is in [admin-roadmap.md](admin-roadmap.md), the staff instructions are in [posibnyk-administratora.md](posibnyk-administratora.md). Earlier versions of the audit and plans have been removed: the project has changed a lot since then (bilingual content, file mirroring, document cards), so the audit was redone against the current code.

The file is kept in the repository: high-severity findings were fixed in the same change and pinned by tests; the independent review (section 8) found bypasses in the first version of the sanitizer, after which it was replaced with a DOM-based library. A repeated independent review of the current working tree (section 9) found open boundaries: legacy HTML, an independent deploy run, and unconfirmed file protection on the hosting. The status "fixed in code" does not mean that the deployed site's security is confirmed.

## 1. Summary

The admin panel is Filament 3 at `/admin`. Before the audit the basic hygiene was already in place: CSRF on all forms, bcrypt hashes, server-side sessions in the database, no self-registration and no password reset, a limit of 5 login attempts per minute per IP, HTTPS and HSTS, and the hosting document root is `public/` (`.env`, `.git`, `vendor/` are not reachable from outside).

The main problem was not a "hole" but the **lack of defense in depth**: any account was a superadmin, any file could be uploaded into the public directory, the dependencies contained 27 known vulnerabilities, and the security headers did not reach `/admin`. Compromising a single staff password (phishing, brute force, an infected computer) meant full control over the site.

The code now implements the main high-priority measures and part of the medium-priority ones. The boundaries of the old content, acceptance on the hosting, and the deploy chain remain open (section 9):

| # | Finding | Level | Status |
|---|---|---|---|
| В1 | No roles: every user is a superadmin | High | **Fixed**: `admin`/`editor` roles, policies, safeguards |
| В2 | File uploads of any type into the public `/storage/` | High | **Fixed in code** (extension and MIME whitelist for all uploads, execution blocked in `public/.htaccess` via mod_rewrite and in `storage/app/public/.htaccess`); **acceptance on the hosting** — section 6 |
| В3 | 27 vulnerabilities in dependencies, `composer audit` did not block CI | High | **Fixed**: updated, audit is empty and blocking |
| В4 | Security headers not applied to `/admin` | High | **Fixed** + `frame-ancestors`, `noindex` |
| В5 | Stored XSS from the content editor, staff biographies and link fields | High (with roles) | **Fixed**: DOM sanitizer `symfony/html-sanitizer` on save (including `Staff.bio`), `JSON_HEX_TAG`, `SafeUrl` rule; old content checked with `otfk:sanitize-content` (dry-run on the hosting: no dangerous constructs), cleanup — after deploy |
| С1 | No second factor | Medium | **Fixed 07.10.2026**: mandatory TOTP for all users, recovery codes, reset by an administrator/console, emergency switch (section 19) |
| С2 | No password policy; password/email change without the current password | Medium | Policy **fixed** (12+ characters, breach check); current password requirement — plan |
| С3 | No log of logins and changes | Medium | Login and lockout log **fixed**; change log — plan |
| С4 | Telegram token in plain text in `settings` | Medium | Mitigated (administrator only); moving to `.env` — plan |
| С5 | Default password `password` in the seeder and instructions | Medium | **Fixed**: the seeder requires `ADMIN_PASSWORD` outside local/testing |
| С6 | Push to `master` = production without review and without waiting for CI | Medium | **Partially fixed**: `deploy.yml` first runs tests and `composer audit` and deploys the same SHA to the server; review/branch protection — process (stage 1) |
| С7 | Backups only on the same hosting | Medium | Plan (stage 3) |
| Н1 | "Remember me" lives 400 days (`SessionGuard`, 576000 minutes) | Low | Plan (stage 2) |
| Н2 | Full CSP is absent | Low | Deliberately not done (Livewire/Alpine) |
| Н3 | `robots.txt` mentions `/admin` | Info | Accepted: the path is standard, protection does not rely on secrecy |

Checks after the change: `php artisan test` (the whole suite is green, new contracts — `AdminRolesTest`, `AdminSecurityTest`, `AdminExposureTest`), `composer audit` — 0 advisories. On the hosting, after deploy, the checklist in section 6 must be run (in particular, that `.htaccess` is read and `/storage/*.php` is not executed).

## 2. Scope and method

- Code: `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php`, all of `app/Filament/**`, middleware, routes, session/log/Livewire configs, seeders, workflows, `.htaccess`.
- Dependencies: `composer audit`, `composer outdated`.
- Live test hosting just-test.shop (06.10.2026, read-only): headers via `curl -I` for `/` and `/admin/login`; response codes for `/.env`, `/storage/`, `/admin/register`, `/up`; tables `users`, `sessions`, `settings`, `migrations` via a direct MySQL connection (parameters are in the git-ignored `.env.hosting.local`).
- Threat model — below. Not done: penetration testing with exploitation attempts on the hosting, verification of the Apache/nginx configuration over SSH, review of GitHub accounts and the hosting control panel.

## 3. Threat model: "a student against the college website"

| Scenario | What it was | What it is now |
|---|---|---|
| Password brute force via `/admin/login` | 5 attempts/min per IP (Filament); the password could be anything, even `password` | The same + a policy of 12+ characters with letters and digits; in production a check against a breach database (haveibeenpwned, k-anonymity); each failed attempt (`auth.failed`, a Laravel event) and each lockout (`auth.lockout`, a custom page `App\Filament\Auth\Login`: the Filament rate-limiting package does not dispatch a `Lockout` event) are written to `storage/logs/security-*.log` |
| Login lockout for everyone at once (if the hosting put one IP in front of all clients) | Not checked | Checked via `sessions.ip_address` on the hosting: the application sees the real client IPs, the limit is indeed per IP |
| Phishing of a staff password | Full control over the site | An editor cannot create an admin, change settings or the menu; the login is visible in the log with the IP; 2FA — next stage. Remaining: an editor publishes news, and publishing triggers an auto-post to the Telegram channel (spam/defacement of the channel with a stolen account — disabled on the "Telegram" page) |
| Upload of a web shell through any form with a file or an attachment in the editor | Any file type, including `.php`/`.html`/`.svg`, into the public directory | Extension **and** content MIME-type whitelist for all uploads (Livewire), `acceptedFileTypes` on programs and documents, `.htaccess` in `storage/app/public` blocks script execution and isolates HTML/SVG via CSP `sandbox` |
| Stored XSS via news/page/biography (editor → administrator, or insider → visitors) | HTML from the editor and `Staff.bio` was output as is | `HtmlSanitizer` (DOM parser `symfony/html-sanitizer`, whitelist based on the W3C Sanitizer API) on save: removes `<script>`/`<style>`/`<object>`, `on*` handlers, `javascript:` links, foreign `<iframe>` (only YouTube, Google Maps/Docs/Drive, own site), escapes "bare" `<` in text; JSON-LD escapes `<`/`>`; URL fields of menus/tiles/banners/announcements accept only relative paths and `http(s)/mailto/tel` |
| Clickjacking of the login page or the panel | There were no headers on `/admin` (verified on the hosting) | `X-Frame-Options: SAMEORIGIN` and `Content-Security-Policy: frame-ancestors 'self'` on all panel responses |
| Discovery of the admin panel | A link "Адмінпанель" (Admin panel) in the public header | The link was removed (test `AdminExposureTest`); `/admin*` and `/livewire/*` return `X-Robots-Tag: noindex, nofollow`. The path `/admin` remains standard — path secrecy is not considered protection |
| Session hijacking | Cookie `Secure; HttpOnly; SameSite=Lax` (verified on the hosting), HSTS 180 days | Unchanged; `SESSION_SECURE_COOKIE=true` added to the production template as an explicit setting |
| Known vulnerability in a library (for example, XSS in the Filament RichEditor ≤ 3.3.52) | 27 advisories, CI only warned | 0 advisories; `composer audit --locked` is blocking in CI |

## 4. What was already good (confirmed)

- Authentication only through Filament Login; `/admin/register` and `/admin/forgot-password` return 404 (test `AdminExposureTest`).
- Passwords are bcrypt (`'password' => 'hashed'`); `AuthenticateSession` logs out other devices when the password changes; sessions are stored in the database.
- CSRF on all POST requests, including Livewire; `APP_DEBUG=false` in the production template.
- The hosting document root is `public/`: `/.env` → 404, directory listing of `/storage/` → 403.
- `DocumentResource` already restricted MIME types (PDF/DOC/XLS) and size.
- Previews of drafts and unsaved forms are available only to logged-in users (`abort_unless(auth()->check(), 404)`); a form snapshot lives 10 minutes in the cache under a random token.
- Mirroring of files from the old site — a whitelist of hosts, extensions and signature checks (`otfk:mirror-files`).
- `TelegramPoster` calls `api.telegram.org`; the password check in production also calls `api.pwnedpasswords.com` (see С2). `LinkChecker` works on the database and disk, without outgoing requests.
- `robots.txt` disallows `/admin` and `/livewire`; the admin panel is not included in visit statistics.
- The test hosting has one account; the seeder's default password is not used; the Telegram token is empty.

## 5. Findings in detail

### В1. No roles and access policies — fixed

- **Was:** `User::canAccessPanel()` always returned `true`, there were no Policies; `UserResource` was available to everyone — any staff member could create and delete administrators.
- **Now:** the column `users.role` (`admin`|`editor`, migration `2026_10_06_220000`; all existing users became `admin`, new ones default to `editor`). `UserPolicy`, `SettingPolicy`, `MenuItemPolicy` (inherit `AdminOnlyPolicy`) and `canAccess()` on `UserResource`, `SettingResource`, `MenuItemResource` and the base of the settings pages `SettingsFormPage` (plus a repeated check in `save()` and `sendTest()` in case the role changes after the page is opened). An editor gets 403 and does not see these sections in the menu. `User::booted()` forbids deleting oneself, deleting or demoting the last administrator, and assigning an unknown role. A "Role" field in the form; one's own role cannot be edited. Bulk deletion of users is disabled.
- **Boundaries:** an editor has access to all content sections, including "Плитки на головній" (Homepage tiles), "Банери" (Banners) ("Оголошення" (Announcements) is not included — it is a settings page) and "Розклад дзвінків" (Bell schedule) (it writes the `bells_*` settings directly, without a policy — deliberately).
- **Test:** `AdminRolesTest`.

### В2. File uploads of any type into the public directory — fixed

- **Was:** RichEditor attachments (pages, news, specialties, departments) were saved to `/storage/<dir>` under the name `hashName()` with an extension guessed **from the content** (`<?php` → `.php`); `ProgramResource` accepted any file with a client-side extension; `->image()` accepted SVG. Execution of PHP in `/storage/` on the hosting was not verified.
- **Now, three layers:**
  1. `config/livewire.php` is published: unified server-side rules for temporary uploads for all forms — `extensions:` (jpg/png/gif/webp/pdf/doc(x)/xls(x)/ppt(x)/odt/ods/odp) **and** `mimetypes:` based on the actual content (fileinfo), `max:20480`; `svg` removed from `preview_mimes`. PHP under the name `photo.jpg` is rejected by MIME.
  2. `ProgramResource` — `acceptedFileTypes` PDF/DOC/DOCX and `maxSize(20480)`; `DocumentResource` was already restricted.
  3. `storage/app/public/.htaccess` (now in git, `!.htaccess` in the directory's `.gitignore`): a ban on `.php|.phtml|.phar|.pl|.py|.cgi|.sh|.shtml` (`Require all denied` under `IfModule mod_authz_core`, otherwise `Deny from all`), `php_flag engine off` under `IfModule mod_php*`, `X-Content-Type-Options: nosniff`, and for `.html|.svg|.xml|.js` — `Content-Security-Policy: sandbox allow-scripts allow-popups` (a script in such a file runs in an isolated origin without cookies and without access to `/livewire`). Each directive is under `IfModule`, so that a missing module does not produce a 500; the risk of `AllowOverride` without `AuthConfig`/`FileInfo` remains and is checked by the checklist. On ukraine.com.ua static files may be served by nginx directly: then the headers from `.htaccess` do not apply, but PHP in `/storage/` is also not executed (only `.php` is proxied to Apache). Files with dangerous extensions can no longer be uploaded through forms, but the `mirror/` directory of the old site may contain them.
- **Side effect:** the favicon/logo now accepts only PNG/JPG/WebP (uploading SVG and ICO is rejected; previously uploaded files continue to work).
- **Requires verification on the hosting** (section 6): that `.htaccess` is read without a 500 error and that a script in `/storage/` is not executed.
- **Test:** `AdminSecurityTest::test_livewire_upload_rules…`, `…test_uploads_directory_forbids_script_execution`.

### В3. Vulnerable dependencies — fixed

- **Was:** `composer audit` — 27 advisories in 7 packages: `filament/forms` (CVE-2026-55409, XSS via RichEditor, ≤ 3.3.52), `guzzlehttp/guzzle` ×9, `guzzlehttp/psr7` ×2, `league/commonmark` ×12, `livewire/livewire`, `laravel/framework`, `league/flysystem`. In CI the step had `continue-on-error`.
- **Now:** `composer config platform.php 8.3.0` (the lock is installed for the hosting's PHP), update without downgrades: Filament 3.3.52 → 3.3.56, Laravel 12.61.1 → 12.69.3, Livewire 3.8.1 → 3.8.10, Guzzle 7.11 → 7.15.5, psr7 2.11 → 2.13.1, CommonMark 2.8.2 → 2.10.3, Flysystem 3.34 → 3.36, Symfony 7.4.x patches. `composer audit` — 0. In `tests.yml` the step became blocking (`composer audit --no-interaction --locked`).
- **Routine:** monthly `composer audit`, `composer outdated --direct`, `npm audit`; only patch/minor updates under `platform.php`.

### В4. Security headers not applied to `/admin` — fixed

- **Was:** `SecurityHeaders` only in the `web` group; Filament routes are registered with the panel's stack — confirmed by `curl -I https://just-test.shop/admin/login` (not a single security header).
- **Now:** `SecurityHeaders` is added to the panel's `->middleware([...])`; `Content-Security-Policy: frame-ancestors 'self'` is added to all responses; for `/admin*`, `/admin-preview/*`, `/livewire/*` — `X-Robots-Tag: noindex, nofollow`.
- **Test:** `AdminSecurityTest::test_admin_pages_get_security_headers_and_noindex`.

### В5. Stored XSS via content and links — new records protected, old ones require checking

- **Was:** editor HTML was output via `{!! !!}` without cleaning; JSON-LD in `breadcrumbs.blade.php` and `news/show.blade.php` lacked `JSON_HEX_TAG` (a title `</script><script>…` broke out of the script); URL fields of menus/tiles/banners/announcements accepted `javascript:`. Before roles this was considered acceptable ("the content is admin-made"); with the editor role it is a path to escalate to administrator and an attack on visitors.
- **Now:** the cast `App\Casts\SafeHtml` (→ `App\Support\HtmlSanitizer`) on `body`/`body_en` of pages and news, `description`/`description_en` of specialties and departments, `bio`/`bio_en` of staff — applied when the attribute is written, that is, for Filament as well as for `saveQuietly()`/imports. The engine is `symfony/html-sanitizer` (DOM parser, whitelist based on the W3C Sanitizer API, explicitly added to `composer.json`): text markup, lists, tables, native `<details>`, images (`http(s)`/relative/`data:image`), links `http(s)/mailto/tel`/relative, attributes `class/id/style/title/alt` and table formatting remain; `<iframe>` — only by the whitelist of `App\Support\Sanitizer\IframeSourceSanitizer` (`youtube.com/embed`, `youtube-nocookie.com/embed`, `google.com/maps`, `docs.google.com/(document|presentation|spreadsheets)`, `drive.google.com/file`, relative `/storage/...`; `sites.google.com` and Google Forms — no, these are ready-made phishing pages). Everything else is removed: `<script>`, `<style>`, `<object>`, `<embed>`, `on*` handlers, `srcdoc`, `javascript:`/`vbscript:` URLs. Text with a "bare" `<` (`a < b`) is escaped rather than turned into a tag; invalid UTF-8 is normalized rather than passed through. The parser discards comments, so the markers `<!--imported-from:…-->` are kept separately and appended at the end (as in the original — with or without a line break). Content that is already saved is not rewritten — it will be cleaned the next time the record is saved. `JSON_HEX_TAG` is added to all JSON-LD. The rule `App\Rules\SafeUrl` applies to URLs of menus, tiles, banners, announcements and to `type=url` values in "Розширені налаштування" (Advanced settings).
- **History:** the first version based on regular expressions was rejected by the independent review (bypasses `<img/src=x onerror>`, `<scr<script>ipt>`, fail-open on invalid UTF-8, corruption of text with `<`) — see section 8; `AdminSecurityTest` now contains these payloads.
- **Side effect:** the sanitizer normalizes markup on save (self-closing `<img />`, `<tbody>` in tables, attribute quotes; `&#61;`/`&#64;` produced by the parser are decoded back to `=`/`@`) — this does not affect display; "exact HTML preservation" tests (`ContentTranslationTest`, `AcademicTranslationTest`, `LocalizationNavigationTest`) were switched to the normalized expected HTML. `translation_source_hash` of an old item will be recalculated after its first re-save.
- **Remaining:** `map_embed` is output into the iframe `src` escaped (not an XSS sink), but without an allowlist of origins — left in the plan.
- **Test:** `AdminSecurityTest::test_rich_text_is_sanitized…`, `…test_json_ld_escapes…`, `…test_link_fields_reject_dangerous_schemes`.

### С1. No second factor — plan

Phishing remains the most likely scenario. The solution is TOTP (Google Authenticator/Aegis), mandatory for the `admin` role and desirable for `editor`; options are `jeffgreco13/filament-breezy ^2` for Filament 3 or an in-house implementation. Details — stage 2 of the roadmap.

### С2. Password policy and change without confirmation — partially fixed

- **Fixed:** `Password::defaults()` in `AppServiceProvider` (12+ characters, letters and digits; in production `uncompromised()` — a synchronous request to api.pwnedpasswords.com when a user is created or the password is changed; if the service is unavailable the check is skipped; with a slow outgoing channel the form may be delayed by several seconds); `UserResource` uses `Password::default()`; the Filament profile page applies it automatically.
- **Remaining:** the Filament profile allows changing the email and password without entering the current password — a stolen session becomes permanent access. Plan: a custom profile page with a "current password" field (stage 2).

### С3. Security log — partially fixed

- **Fixed:** `App\Listeners\LogAuthenticationEvents` (event discovery) writes `auth.login`/`auth.failed`/`auth.logout`, and the custom login page `App\Filament\Auth\Login` writes `auth.lockout` (Filament uses `danharrin/livewire-rate-limiting`, which does not dispatch a `Lockout` event) to the `security` channel (`storage/logs/security-YYYY-MM-DD.log`, level `info` fixed, 90 days) — only the email, IP, User-Agent, without passwords. `users.last_login_at`/`last_login_ip` are updated on login and shown in the "Користувачі" (Users) table.
- **Remaining:** a log of content changes (who changed what) — `spatie/laravel-activitylog`, stage 3; notifications to the owner about a login from a new IP.

### С4. Telegram token in the `settings` table — mitigated

The field is masked on the "Telegram" page, but it is visible in plain text in "Розширені налаштування" (Advanced settings). Now both pages are available only to the administrator. Moving the token to `.env` (`TELEGRAM_BOT_TOKEN`) with changes to `TelegramPoster` — stage 3; on the test hosting the token is empty.

### С5. Default credentials in the seeder — fixed

`DatabaseSeeder` throws an exception if `ADMIN_PASSWORD` is empty outside the `local`/`testing` environments; it uses `firstOrCreate` (a repeated seeding does not overwrite the password) and assigns the `admin` role. `DEPLOY.md` no longer suggests logging in with `password`. The `password` fallback remains only for local development and tests.

### С6. Deploy: push to `master` = production — partially fixed

- **Was:** `deploy.yml` started on push independently of `tests.yml`; the server ran `git reset --hard origin/master`, so it could receive a revision newer than the one the frontend was built for.
- **Now:** `deploy.yml` has a job `tests` (PHP 8.3, `composer install`, `composer audit --locked`, `php artisan test`) and `deploy` with `needs: tests`; the server checks out exactly `${{ github.sha }}` — the same commit that passed the tests and for which `public/build` was built.
- **Remaining (process):** branch protection (PR-only, required check, no force-push), mandatory 2FA on GitHub for everyone with write access — stage 1 of the roadmap.

### С7. Backups — plan

`otfk:backup` stores dumps on the same hosting (`storage/app/backups`, 14 copies). If the hosting is compromised, the copies are lost together with the site. An external copy of the **database and files with the same RPO** is needed (a daily dump + a daily incremental sync of `storage/app/public`, otherwise a restored database references missing attachments), independent keys, protection against deletion, an alert for an overdue copy, and a rehearsal of restoring the database and files in an isolated environment without Telegram — stage 3.

### Н1. "Remember me"

Laravel's remember cookie lives 400 days; on a shared computer this is permanent access. Plan: shorten it to 30 days (`auth.guards.web.remember = 43200` before the guard is created, or `SessionGuard::setRememberDuration(43200)`; the top-level `auth.remember` is not used) or remove the checkbox for the `admin` role — stage 2. For now — a rule for staff in the instructions.

### Н2. CSP

A full CSP with nonces would break Livewire/Alpine/Filament; we limit ourselves to `frame-ancestors`. Not planned without a separate task.

### Н3. `robots.txt` and the `/admin` path

`Disallow: /admin` is standard for any Laravel/Filament site; the path is easy to guess, so protection is not built on hiding it. The public link is removed, indexing is forbidden by a header — this is sufficient.

## 6. Checklist for verification on the hosting after deploy

- [ ] `curl -I https://домен/admin/login` contains `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy: frame-ancestors 'self'`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag: noindex, nofollow`, `Strict-Transport-Security`.
- [ ] Check that an existing PDF/image is reachable, and separately check the actual prohibitions/headers on safe test files. HTTP 200 for static files does not prove that `.htaccess` is read: nginx may be serving them. On a 500, check `AllowOverride` and the modules with the hosting provider; do not remove the prohibitions without equivalent protection at the server level. `IfModule` checks for the presence of a module, not for permission to override.
- [ ] Over SSH: `echo '<?php echo 1;' > storage/app/public/_probe.php`, request `/storage/_probe.php` → expected 403 (not "1"), then delete the file. This is the only proof of `/storage/` protection: an opening PDF only shows the absence of a 500. Two independent mechanisms: `RewriteRule … [F]` in `public/.htaccess` (mod_rewrite on the hosting definitely works — the whole site is routed through it) and `Require`/`Deny` in `storage/app/public/.htaccess`. If "1" is printed — urgently restrict `AllowOverride`/ask the hosting provider and temporarily block file uploads.
- [ ] Old content: `php artisan otfk:sanitize-content` (report), then `--apply` (backup in `storage/app/private/sanitize-backup-*.json`, `saveQuietly` without Telegram and without changing `updated_at`, the hash of current translations is preserved). Dry-run on the test hosting 06.10.2026: 904 records, 729 would change only by markup normalization, 4 — with markup removal, and all four are legitimate: an iframe of a PDF from the old site (added to the whitelist), a typo `htth://` in a link, `value` on `<li>` and `data-trix-*` on attachments (both allowed). The previous report did not flag dangerous constructs, but its classifier has false negatives (section 11); this is not confirmation of the absence of dangerous content. This is the only proof of `/storage/` protection: an opening PDF only shows the absence of a 500. Two independent mechanisms: `RewriteRule … [F]` in `public/.htaccess` (mod_rewrite on the hosting definitely works — the whole site is routed through it) and `Require`/`Deny` in `storage/app/public/.htaccess`. If "1" is printed — urgently restrict `AllowOverride`/ask the hosting provider and temporarily block file uploads.
- [ ] In the admin panel: an attempt to upload `.php`, `.html`, `.svg` in "Освітні програми" (Educational programs) or as an attachment in the news text is rejected with a validation message; `.pdf` and `.jpg` are uploaded.
- [ ] `php artisan migrate --force` has run: in "Налаштування → Користувачі" (Settings → Users) there are columns "Роль" (Role) and "Останній вхід" (Last login), and the existing account is "Адміністратор" (Administrator).
- [ ] A wrong password on `/admin/login` → an `auth.failed` line in `storage/logs/security-*.log`; six in a row → `auth.lockout`.
- [ ] Password recovery for the last administrator (if needed): `php artisan tinker` → `App\Models\User::where('email', '…')->update(['password' => Hash::make('новий-пароль-12+')])` (new password, 12+ characters); the seeder does not overwrite the password.
- [ ] `SESSION_SECURE_COOKIE=true` in the hosting's `.env` (value from `.env.production.example`).
- [ ] Personal accounts for staff with the "Редактор" (Editor) role are created; the shared account is not used.

## 7. Development rules (to be enforced)

- A new Filament Resource or Page — decide the role immediately: content → available to the editor; settings/structure/users → `canAccess()` + a Policy inheriting `AdminOnlyPolicy` + a test.
- A new `FileUpload` — `acceptedFileTypes` + `maxSize`; the extension must be in the whitelist of `config/livewire.php`, otherwise the upload will fail.
- A new HTML field of the editor — the cast `SafeHtml`; a new `{!! !!}` in a template — only for a sanitized field or `json_encode(..., JSON_HEX_TAG)`.
- A new link field — the rule `SafeUrl`.
- `Http::withoutVerifying()` is forbidden; external requests — only to explicitly listed hosts.
- A red `composer audit` = a red CI; fix it by updating the lock under `platform.php`, not by disabling the step.

## 8. Independent review (06.10.2026)

After the first version of the changes, an independent critic agent was run with the task of finding bypasses and regressions. Critical and high remarks were fixed in the same change:

| # | Remark | What was done |
|---|---|---|
| 1 | The regex sanitizer could be bypassed (`<img/src=x onerror>`, `<svg/onload>`, `<scr<script>ipt>`, fail-open on invalid UTF-8, `/\evil` in an iframe, `<style>`), it corrupted text with `<` | Replaced with `symfony/html-sanitizer` (DOM, whitelist); the payloads were added to `AdminSecurityTest` |
| 2 | `Staff.bio` was output with `{!! !!}` without cleaning | `SafeHtml` cast on `bio`/`bio_en`, test of the `/personal/{slug}` page |
| 3 | The iframe whitelist allowed `sites.google.com`/Google Forms (phishing) | `IframeSourceSanitizer`: only `youtube*/embed`, `google.com/maps`, `docs.google.com/(document|presentation|spreadsheets)`, `drive.google.com/file`, relative paths; only `https` |
| 4 | Documents claimed "fixed" too early | Texts updated to match the facts (this section, В5, С3) |
| 5 | `auth.lockout` was not written: Filament does not dispatch a `Lockout` event | A custom `App\Filament\Auth\Login::getRateLimitedNotification()` writes the lockout; a test for 6 failed attempts |
| 6 | `.htaccess` could produce a 500 if `AllowOverride AuthConfig` is forbidden | `IfModule` and a fallback were added, but the risk of a forbidden override remains; server-side verification is required (section 9) |
| 8 | `User::create()` without a role threw an exception | `$attributes['role'] = editor` |
| 9 | A model exception when deleting the last admin was shown as a form error | `UserResource::guardDeletion()`: a notification and the action is cancelled before deletion |
| 10 | `->dehydrated()` on the role field allowed changing one's own role via Livewire | `dehydrated` only for other users' records |
| 11 | The seeder test depended on a local `ADMIN_PASSWORD` | `phpunit.xml` sets an empty `ADMIN_PASSWORD` |

Accepted without code changes: an editor can publish news with an auto-post to Telegram (disabled on the "Telegram" page, described in the threat model); `uncompromised()` is a synchronous call (described in С2); the `.ico` favicon is no longer uploaded; password recovery for the last administrator — via `tinker` (checklist).

## 9. Repeated independent review of the working tree (06.10.2026)

The current uncommitted changes, the application code, vendor and workflows were checked. `AdminRolesTest`, `AdminSecurityTest`, `AdminExposureTest` were run: **20 passed, 271 assertions** in SQLite `:memory:`; a production config cache was absent. Local probes of the sanitizer were run without writing to the database. The hosting, GitHub settings and the current advisory database were not checked in this review. The results of the previous audit are not presented as new measurements.

1. **High priority: CI is not a gate for deploy.** `.github/workflows/deploy.yml` starts independently on push and manually, without a dependency on a successful PHP test and audit. In addition, the assets relate to the checkout of the run, while the PHP code relates to the current `origin/master`: the next push may yield different revisions. A check of successful CI for the specific SHA and installation of this same SHA on the server are needed; branch protection complements but does not replace this contract.
2. **High priority before В5 is closed: old HTML is not protected by the new cast.** `SafeHtml::get()` returns the stored text without cleaning; a local probe saved `<img src=x onerror="alert(1)">` and it was returned on read. This is evidence of a boundary, not a claim about an infected database. An inventory of both locales and old URLs, a backup, a dry-run report and controlled cleanup with a check of links/translations are needed. Direct SQL/Query Builder updates also bypass casts. News cannot be mass-resaved without taking the Telegram observer into account.
3. **High priority for acceptance: storage protection is not yet confirmed by the server.** The test `test_uploads_directory_forbids_script_execution` checks the lines of `.htaccess`, not the execution of the prohibitions. A static PDF with HTTP 200 and `IfModule` does not prove that the protection works. В2 should be closed only after the real web server has been checked, script execution is forbidden, and old active files are isolated. There are no negative results of hosting checks in this revision: they were not performed.
4. **Medium priority: the 2FA plan could create a blind spot in the tests.** A global bypass via `runningUnitTests()` would exclude the real barrier from Feature tests. Tests are needed for an unconfirmed session for direct URLs and Livewire, a wrong/repeated code, rate limit, recovery codes, disabling/resetting the factor and remember-sessions. A confirmed factor is set only for the fixtures of content tests. For editors with publishing rights the phishing risk remains significant; making 2FA optional is the owner's decision, with acceptance of this risk.
5. **Medium priority: the external backup criterion is incomplete.** A daily database and monthly files allow losing almost a month of uploads; a restored database may reference missing files. Separate RPO/RTO for the database and storage are needed, sufficiently frequent file copying, independent restore keys, retention/protection against deletion, and an alert for an overdue copy. A rehearsal must include the database and files in an isolated environment without sending Telegram messages.
6. **Medium residual risk: the sanitizer does not prevent CSS interface spoofing.** Locally `HtmlSanitizer::clean()` kept `style="position:fixed;inset:0;z-index:2147483647;background:white"`. This is not a proven JavaScript XSS, but an editor can cover the page with arbitrary content. The solution is to restrict the allowed CSS properties with a check of imported markup, or to explicitly accept the risk from the publisher role; banning Google Forms by itself does not eliminate phishing through content.

Additional accuracy corrections: the installed `SessionGuard` sets **576000 minutes = 400 days**, not five years; `AuthManager` reads `remember` from the guard's configuration, not from `auth.remember`. The check for absence of known vulnerabilities is a result as of the date of the run, not a permanent property of the project.

Conclusion: the basic measures are useful and the profile tests are green, but the statement "all high risks are closed" is premature. First complete the acceptance of storage, old content and the deploy chain; then confirmation of sensitive actions, 2FA and recovery. The estimates of the stages should be considered preliminary until the compatibility of 2FA and the capabilities of external storage are verified.

## 10. Response to section 9

| # | Remark | Status | What was done |
|---|---|---|---|
| 1 | Deploy does not wait for CI; the server takes the current `master` | Confirmed, **fixed** | `deploy.yml`: job `tests` (tests + `composer audit --locked`) → `deploy` with `needs`; on the server `git reset --hard ${{ github.sha }}` |
| 2 | Old HTML without cleaning | Confirmed, **fixed** | The command `otfk:sanitize-content` (report / `--apply` with a backup, without Telegram and `updated_at`, with preservation of the translation hash); dry-run on the hosting — no dangerous content (see section 6); cleanup after deploy |
| 3 | Protection of `/storage/` not proven on the hosting | Confirmed, **partially** | A second mechanism was added on the reliably working mod_rewrite (`public/.htaccess`, `[F]` for scripts under `/storage/`); a test fixes the rule. The proof is only the `_probe.php` probe on the hosting (section 6); В2 is marked "fixed in code, acceptance on the hosting" |
| 4 | 2FA plan with a bypass in the tests | Confirmed | The roadmap was rewritten: enforcement is not disabled globally; separate tests for an unconfirmed session (URL and Livewire), wrong/repeated code, rate limit, recovery codes, disabling the factor, remember-sessions; a confirmed factor only for the fixtures of content tests |
| 5 | Monthly file backup | Confirmed | Roadmap: a daily incremental copy of `storage/app/public` with the same RPO as the database; separate RPO/RTO, protection against deletion, alerts, rehearsal of the database + files |
| 6 | Inline CSS allows covering the page | Confirmed, **fixed** | `StyleAttributeSanitizer`: a whitelist of CSS properties (colour, font, alignment, sizes, margins, borders, lists); `position`, `z-index`, `inset`, `transform`, `opacity`, `display`, `url()` are discarded; test |
| — | Remember 400 days, not 5 years; `remember` is read from the guard | Confirmed | Н1 is fixed |

Author's conclusion for the response: the main measures are implemented; В2 and cleanup of old content require acceptance on the hosting. The repeated check of section 10 revealed defects in the cleanup command and a remaining CSS-covering path (section 11); it is not yet possible to consider all high findings closed.

## 11. Re-review of the responses to section 10 (06.10.2026)

The current working tree was checked. `AdminRolesTest`, `AdminSecurityTest`, `AdminExposureTest` were re-run: **22 passed, 284 assertions** (testing, SQLite `:memory:`, the config cache was absent). Local probes of the sanitizer/classifier were run without writing to the database. The generated CSS and the workflow sources were checked. The hosting and an actual GitHub Actions run were not checked; `--apply` on real data was not performed.

### Remarks blocking closure

1. **P1 — the backup is created after the database is changed.** In `SanitizeContent::handle()` the originals accumulate in an array, each record is already saved via `saveQuietly()`, and only after all models are processed is `file_put_contents()` executed. A crash, an exception or lack of memory would leave a partially cleaned database without a copy. The result of the file write is not checked; the old `translation_source_hash` and the selected connection are not included in the copy. Before `--apply` is used, a reliably written and verified backup before the first mutation is needed (or a journal before each write), the metadata required for restoration, and a test of a failed write/interruption. One successful test does not verify this guarantee.
2. **P1 — "normalization only" is not proof of safety.** A direct call of `removedMarkup()` for `<img/src=x onerror=alert(1)>` returns an empty list, although the DOM sanitizer removes `onerror`. For `style="position:fixed;color:red"` the removal of `position` is also classified as normalization: the attribute names have not changed. The sanitizer itself performs the protection in the first probe; the defect is in the report and in the conclusions about the old database. A full structural diff that takes values into account is needed, or an honest status "changed, requires review" for unclassified differences. A re-evaluation of old content is required; this check does not claim that the database is infected. The command also does not inventory old URLs of menus/banners/tiles/settings, previously covered by remark 9.2.
3. **P2 — CSS covering is preserved through `class`.** `HtmlSanitizer::clean('<div class="fixed inset-0 z-50 bg-white">Fake login</div>')` returns the string unchanged. The locally built CSS contains the corresponding rules `position:fixed`, `inset:0`, `z-index:50` and a white background; these classes are used by the public layout. The filter for inline `style` eliminates the specific probe but not the stated risk of interface spoofing. A policy for allowed content classes/isolation of styling and a regression test are needed, or an explicit acceptance of the publisher's risk. This is CSS spoofing, not a proven JavaScript XSS.

### What is accepted

- **Response 1 (CI/SHA): accepted by the code.** `deploy` has `needs: tests`, the audit and PHP tests run in the same workflow; the server installs `${{ github.sha }}`. For a manual run from a branch other than master an operational caveat remains: the server only does `git fetch origin master`, so the presence of the selected SHA is not guaranteed. Manual dispatch needs to be restricted to master, or the selected revision must be obtained explicitly. The actual CI run was not checked.
- **Response 2 (old HTML): partial.** The command exists and successfully cleans test material, but the P1 items above do not allow the controlled cleanup to be considered ready for production.
- **Response 3 (storage): partial, as the author indicated.** The second rule is present; a server probe is mandatory. The fact that routing works does not prove the handling of existing files by the same path through nginx/Apache. The isolation of old active content needs to be verified separately from the prohibition on PHP.
- **Responses 4 and 5 (2FA and backups): accepted as a correction of the plan, not as an implementation.** The roadmap has no global test bypass for 2FA; RPO/RTO and file restoration were added. The wording of section 10 about the already chosen daily incremental copy is more precise than the roadmap itself: the roadmap leaves the storage frequency to be agreed.
- **Response 6 (CSS): partial; the path through classes remains.** The clarification about the remember-cookie is accepted.

Conclusion: progress is confirmed, but closing section 10 as a whole is premature. First fix the backup guarantee and the reliability of the report, define the policy for CSS classes, then carry out acceptance on the hosting. The application code was not changed during the re-review.

## 12. Response to section 11

| # | Remark | Status | What was done |
|---|---|---|---|
| P1 | `sanitize-content --apply` wrote the backup after the changes; the success of the write was not checked | Confirmed, **fixed** | Three phases: a full read-only pass → writing the backup JSON with a check of size and a re-parse (on failure the command exits with code 1, the database is untouched) → writing. A test checks that the backup contains the originals of all changed records |
| P1 | The "normalization only" classifier did not see `<img/src=x onerror=…>` | Confirmed, **fixed** | The inventory of elements/attributes is built by the HTML5 DOM parser (the same one the sanitizer uses), not by a regular expression; a test with this payload expects `← видалено: body [<img onerror>]` (removed). A repeated dry-run on the hosting — below |
| P2 | Page covering with Tailwind classes (`fixed inset-0 z-50`) that exist in the site's CSS | Confirmed, **fixed** | `ClassAttributeSanitizer`: classes of positioning, layers, opacity, transformations and full-screen sizes are discarded (including the `md:`/`hover:` variants and negative `-translate-*`); other classes, including imported ones, are kept; a test on the string from the remark |

Repeated dry-run `otfk:sanitize-content --connection=hosting` after these changes (read-only): 904 records, 729 would change (markup normalization), with removal of elements/attributes — 1: the page `spysok-posylan-ekonomika`, a link with the typo `htth://minrd.gov.ua/` (non-working even before cleanup). Neither `on*`, nor `<script>`, nor overlay classes were found in the database by the DOM classifier.

## 13. Verification of the fixes from section 12 (06.10.2026)

The profile tests were re-run: **22 passed, 290 assertions**, testing/SQLite `:memory:`, the config cache was absent. Local probes of `HtmlSanitizer::clean()` and `removedMarkup()` without writing to the database. The hosting, actual cleanup and GitHub Actions were not run.

**Accepted:** the backup was moved before the write to the database, the record size and JSON readability are checked; the DOM classifier now notices the removal of `onerror` from `<img/src=x onerror=alert(1)>`; the earlier payload `class="fixed inset-0 z-50 bg-white"` is cleaned to `class="bg-white"`. This closes the specific reproduced examples, but not all the remarks.

1. **P1 — loss of concurrent edits during cleanup.** In phase 1 `before`/`after` are saved; then, after the full pass and the backup, phase 3 re-reads the record via `find()` but, without comparing with `before`, applies the old `after`. An editor's change between the phases will be overwritten and will not get into the backup. This follows from the order of operations in `SanitizeContent::handle()`; a concurrent run on the hosting was not performed. An atomic check of the original values/record version with skipping of conflicts, or a row lock and comparison in a transaction, is needed. A simple check before a separate save leaves a race. A test of an edit between the phases is needed.
2. **P2 — false classification of value changes remains.** The DOM inventory counts only tag/attribute names, not values. Repeated probes: `style="position:fixed;color:red"` → `style="color: red"`, `class="fixed inset-0 z-50 bg-white"` → `class="bg-white"`; in both cases `removedMarkup()` returns `[]`, and the report states "only normalization". Therefore the conclusion of section 12 "no overlay classes found" is not proven by this counter. A check of values or a neutral classification "changed, requires review" is needed. The sanitizer works in these probes; the defect relates to the report, not to the execution of the cleanup.
3. **P2 — the backup is still incomplete for exact restoration.** The JSON lacks the selected connection and the original `translation_source_hash`, although the command may change the hash. The file name has one-second precision and the write allows overwriting: two runs within the same second can destroy the first copy. Unique creation without overwriting, database metadata and the old hash are needed. The current test checks a successful backup but not a failed backup write, a run conflict, or restoration of the metadata.

Conclusion: the fixes are partially accepted. The backup order and the specific CSS payload are fixed; before application to live content the write conflict, completeness of restoration and reliability of the report remain. Acceptance of storage on the hosting remains a separate open item. The application code was not changed during this review.

## 14. Response to section 13

| # | Remark | Status | What was done |
|---|---|---|---|
| P1 | An editor's change between the scan and the write was overwritten | Confirmed, **fixed** | Before saving, each record is re-read and compared with the scanned original (all fields, `updated_at`, `translation_source_hash`); a changed record is skipped with a warning, the command exits with code 1 and suggests a retry; a test simulates a concurrent edit via the hook `SanitizeContent::$afterScan` and checks that the new edit is preserved |
| P2 | The classifier counted the removal of `position:fixed` or of the class `z-50` as normalization while the attribute was kept | Confirmed, **fixed** | The DOM inventory takes values into account: for `style` — each property (`<div style:position>`), for `class` — each class (`<div class:z-50>`); a test expects exactly such labels |
| P2 | The backup had no translation hash or connection; two runs in the same second overwrote the file | Confirmed, **fixed** | The JSON contains `created_at`, `connection`, `database` and, for each record, `translation_source_hash` and `updated_at`; the file name has microseconds and a random suffix, an existing file is not overwritten (an error before the database is changed); a test performs two consecutive runs and checks for two files |

Repeated dry-run on the hosting taking `style`/`class` values into account: 904 records, 729 normalization, with removals — 5: the earlier typo `htth://` and four news items with `aspect-ratio` on YouTube iframes; the property `aspect-ratio` was added to the style whitelist as safe, after which one legitimate change remains.

## 15. Verification of the fixes from section 14 (06.10.2026)

**P1 remains: the conflict check is not atomic.** `find()` → comparison of the original → `saveQuietly()` are separate operations without a transactional lock or a conditional UPDATE. The new check protects against changes up to the last SELECT, but an edit after the SELECT is still overwritten. This was explicitly noted in section 13.

Reproduced locally in SQLite `:memory:` with a temporary Feature test: the record contains `<p>old</p><script>x</script>`; after the scan a `DB::listen` is registered which, after executing the final SELECT by ID (before the result is returned to the model), writes `<p>editor new</p>`. The command returns success, the final body is `<p>old</p>`. The test confirms the loss of the new edit, not the correctness of the protection. It used a separate temporary storage; the test and its backups were deleted after verification. The hosting was not touched.

**Required fix:** on the same connection, a transaction with `lockForUpdate()` on the re-read, then comparison and saving under the lock (to be verified on MySQL/InnoDB), or an atomic UPDATE with a predicate on the version/original values and a check of the number of changed rows. A separate SELECT with comparison before the regular save is insufficient. The `afterScan` test covers the early window; a contract for concurrent writes in the final phase is also needed.

**Accepted:** the earlier examples with removal of `position:fixed` and of the classes `fixed/inset-0/z-50` are now correctly flagged by the classifier (repeated direct probes); the backup includes connection/database, the earlier translation_source_hash and updated_at; microseconds and a random suffix eliminate the earlier systematic collision of two runs within a second. Technical clarification: `file_exists` + `file_put_contents(LOCK_EX)` does not guarantee an atomic "create only a new file"; for a strict guarantee, exclusive creation with `fopen(..., 'x')` should be used. A random collision is now unlikely; this is not a separate blocking defect of this review.

Checks: **21 existing profile tests + 1 temporary reproduction = 22 passed, 277 assertions**. The existing combined test of the command was not run: it deletes all `sanitize-backup-*.json` files from the shared storage, including potentially unrelated backups; it should be isolated in a temporary directory. The testing database is in memory, the config cache was absent. A new hosting/CI run, actual cleanup and a current dependency audit were not performed. The application was not changed. Conclusion: one blocking item remains regarding concurrent writes; acceptance of storage on the hosting is separate.

## 16. Response to section 15

| # | Remark | Status | What was done |
|---|---|---|---|
| P1 | `find()` → comparison → `saveQuietly()` were not atomic: an edit after the last SELECT was overwritten | Confirmed, **fixed** | The write is performed by a single conditional `UPDATE … WHERE id = ? AND updated_at = ? AND translation_source_hash = ? AND <field> = <scanned original>` (on MySQL the comparison is `BINARY`, so that case and trailing spaces are not treated as equal; `NULL` — via `IS NULL`). The check and the write are one DBMS operation: 0 changed rows = the write is skipped, exit code 1, repeat the command. A transaction with `lockForUpdate()` is not needed: there is no gap between the comparison and the write. The new translation hash is calculated from the re-read state, and its change between the SELECT and the UPDATE is rejected by the condition on `updated_at`/the hash. A test was added for the narrowest window: the hook `SanitizeContent::$beforeWrite` changes the body between the repeated SELECT and the UPDATE — the command reports a skip, the editor's edit remains |

A repeated dry-run on the hosting is not required: the reporting part was not changed. Profile tests: `AdminSecurityTest` — 13 passed (243 assertions); the full set — see "Last completed task" in CLAUDE.md.

## 17. Verification of the fix from section 16 (06.10.2026)

**The earlier blocking remark on the write is closed by the code:** the Query Builder performs one UPDATE with a predicate on the ID, the original cleaned fields, `updated_at` and `translation_source_hash`; NULL is compared via IS NULL, and on MySQL string comparisons use BINARY. A change of a cleaned field after the final SELECT leads to a skip, not an overwrite. The number of changed rows is checked; observers and `updated_at` are not affected. The new beforeWrite test checks exactly the late race window. This is acceptance of a specific fix, not a claim of a full penetration test of the site.

**A remark on the test remains (P1 if working backups exist):** `AdminSecurityTest::test_sanitize_content_command_reports_and_cleans_legacy_html_without_side_effects()` at the start deletes all `storage/app/private/sanitize-backup-*.json`. Testing/SQLite isolates the database, but not this directory. Running the regular test set after a real cleanup may destroy its backup copies. A separate temporary storage and the deletion of only its own files are required. During the review the test was run through a temporary subclass with an isolated directory; the application was not changed.

The server acceptance of `/storage/`, a real MySQL and GitHub Actions were not run in this review. The strict exclusive creation of the backup file noted earlier remains a non-blocking hardening. The currency of advisories was not re-checked.

Checks completed: **22 passed, 301 assertions** (21 profile tests + the existing cleanup test inherited by a temporary subclass with separate storage). The early and late conflicts, cleanup, translation hash, metadata and several backups were verified. The temporary subclass and its files were deleted. `node DocsHtml/generate.mjs` updated the HTML after fixing an outdated mention of saveQuietly in ARCHITECTURE; `git diff --check` and the common parts of AGENTS/CLAUDE were verified.

## 18. Response to section 17

| # | Remark | Severity | What was done |
|---|---|---|---|
| P1 (conditional) | The cleanup test deleted all `storage/app/private/sanitize-backup-*.json` | Confirmed. The tests are not run on the hosting (CI runs on GitHub, the deploy does not run tests on the server), so the real copies on production were not at risk; the risk applied only to a developer who cleaned content locally and then ran the test set. **Fixed** | The command received an option `--backup-dir=` (default `storage/app/private`); the test creates its own temporary directory in `sys_get_temp_dir()`, passes it to all `--apply` runs, checks that nothing appeared in the working backup directory, and deletes only its own files. The remains of earlier test runs in `storage/app/private` (two files with `"database": ":memory:"`) were deleted manually |

Strict exclusive creation of the backup file (`fopen` with `x`) remains a non-blocking hardening: the name already contains microseconds and a random suffix, and an existing file is checked before writing.

## 19. Second factor (07.10.2026)

- **Implementation:** in-house, based on `pragmarx/google2fa` + `bacon/bacon-qr-code` (SVG, no GD). Fields `users.two_factor_secret`/`two_factor_recovery_codes` (cast `encrypted`), `two_factor_confirmed_at`, `two_factor_last_used` (the step of the last accepted code — a repeat of the same code is rejected). Middleware `App\Http\Middleware\RequireTwoFactor` in the panel's `authMiddleware` **and** in Livewire's `persistentMiddleware`: without a confirmed factor the user is redirected to `/admin/two-factor-setup`, with a confirmed one — to `/admin/two-factor-challenge`, and any Livewire call to other components gets 403 (verified by a real POST to `/livewire/update` with a snapshot from an authenticated session). The pages are `App\Filament\Auth\TwoFactorSetup`/`TwoFactorChallenge` (a limit of 5 attempts/min per code, the `2fa.*` log in `security`).
- **Mandatoriness:** for all roles, at the first login after deploy. The secret becomes active only after the first correct code — a user cannot "lock themselves out" with a partially set up app.
- **Protection against self-lockout (owner's requirement):** 10 one-time recovery codes (shown once, regenerated with the current code); a "Скинути 2FA" (Reset 2FA) button for an administrator for other users (not for oneself); the command `otfk:two-factor {email} --reset` and `--status` over SSH; the emergency switch `TWO_FACTOR_ENFORCE=false` in `.env`. The last administrator cannot reset their own factor via the UI — the recovery codes, the console and the switch remain. All paths are verified by `TwoFactorTest`.
- **Tests:** enforcement is not disabled in tests; `TestCase::actingAs()` sets a fixture confirmed factor and the session mark only as a fixture, while `TwoFactorTest` logs in via `actingAsWithoutTwoFactor()` and checks redirects, Livewire 403, a wrong/repeated code, one-time use of recovery codes, reset, the switch, regeneration and reconnection.
- **Remaining:** "Remember me" and password/email change without the current password — stage 2 of the roadmap; trusted devices (skipping the code for 30 days) are deliberately not implemented: the code is entered at every login.
