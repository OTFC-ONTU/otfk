# Admin panel improvement plan (security and operations)

Continuation of the [security audit](security-audit.md). Principles: hosting without Node, without a queue and without a confirmed cron — only synchronous solutions and `afterResponse()`; periodic tasks run through GitHub workflows; every behaviour change gets a Feature test; every stage ends with a deployment and verification on the test hosting. Estimates are pure developer time.

## Stage 0 — done 06.10.2026 (this edit)

- Roles `admin`/`editor` with policies and safeguards (last admin, own account, unknown role); the sections «Користувачі» (Users), «Налаштування» (Settings — all pages and «Розширені налаштування» (Advanced settings)), and «Меню навігації» (Navigation menu) are available to the administrator only.
- All uploads: a whitelist of extensions and content MIME types, up to 20 MB; `.htaccess` against script execution in `/storage/`; SVG is no longer uploaded.
- Protective headers on `/admin`, `frame-ancestors 'self'`, `noindex` for the panel; the public link to the admin is removed.
- Sanitizer for the editor's HTML (`symfony/html-sanitizer`, DOM parser with a whitelist; iframes only for YouTube/Google Maps/Docs/Drive) on save, including staff biographies; `JSON_HEX_TAG` in JSON-LD, the `SafeUrl` rule for link fields.
- Password policy 12+ (production — checked against breach databases), login and lockout journal `security-*.log` and «Останній вхід» (Last login) in the users table, a seeder without a default password.
- Independent review (section 8 of the audit): bypasses of the first sanitizer and other remarks fixed.
- Dependencies updated (0 advisories), `platform.php = 8.3.0`, `composer audit` blocking in CI; `deploy.yml` waits for the tests and deploys the same SHA to the server.
- Command `otfk:sanitize-content` for inventory and cleanup of old HTML: a DOM classifier that takes the values of `style`/`class` into account, a backup before writing (unique, with translation hashes and the connection, `--backup-dir=`), an atomic conditional UPDATE against overwriting parallel edits (sections 11–18 of the audit); a CSS property whitelist in `style` and a class whitelist in `class`. Dry run on hosting: no dangerous content, 1 legitimate fix (the typo `htth://`).

## Stage 1 — acceptance of the protection and the deployment chain (owner + developer; estimate after the hosting check)

1. Go through the checklist of section 6 of the audit on the hosting: headers, the probe `_probe.php` in `/storage/` (403, not «1»), the role migration, the login journal; then `php artisan otfk:sanitize-content` (report) → `--apply` on the real MySQL (acceptance of the conditional UPDATE with `BINARY` on the hosting).
2. Hosting `.env`: `SESSION_SECURE_COOKIE=true`, `ADMIN_PASSWORD` set (needed only for future seeding of an empty DB), `APP_DEBUG=false`.
3. Accounts: each employee gets a personal one with the role «Редактор» (Editor); there are two administrators (primary + backup); the shared `admin@…` account is renamed to a personal one or deleted after the replacement is created.
4. GitHub: branch protection on `master` (PR-only, required check «PHP 8.3 · php artisan test», no force-push); mandatory 2FA for all collaborators; review of the deployment secrets.
5. Hosting control panel: restrict remote access to MySQL by IP or disable it once direct edits of the test DB are finished; change the DB password after that.

6. Verify the implemented gate of `deploy.yml` on the first real run: job `tests` red → `deploy` does not start; the server receives exactly `${{ github.sha }}` (now `git fetch origin <sha>`, so a manual run from another branch also gets the selected revision).
7. After `--apply` on the hosting, selectively open the cleaned materials in both locales (UK/EN), check the `/en` links, PDF cards and Trix attachments; keep the backup outside the hosting.

Acceptance criterion: the checklist is ticked with actual HTTP results, file protection is verified on the server, old content is verified, and a failed CI run excludes deployment of the same SHA; the list of accounts matches the staff. Until then, Stage 0 means implementation in code, not completed acceptance on the hosting.

## Stage 2 — accounts (3–4 days)

1. ~~**2FA TOTP**~~ — **done 07.10.2026** (section 19 of the audit): mandatory for all roles, own implementation on `pragmarx/google2fa`, recovery codes, reset by an administrator or via console, `TWO_FACTOR_ENFORCE`. Original wording, kept for history: mandatory for `admin`, optional for `editor`: `jeffgreco13/filament-breezy ^2` (branch for Filament 3; check compatibility with `AuthenticateSession` and the rate limit) or an own implementation (`pragmarx/google2fa`, a code entry page in the `authMiddleware` stack, recovery codes). Tests: do not disable enforcement via `runningUnitTests()`. In content tests, explicitly set a confirmed factor; separately check an unconfirmed session on direct URLs and in Livewire, a wrong or repeated code, the rate limit, one-time recovery codes, reset/disabling of the factor and the remember-session. Check the package's compatibility and the full lifecycle of the factor before fixing the estimate. For editors who can publish on the site and to Telegram, making 2FA optional requires the owner to accept the risk.
2. **Profile**: an own `EditProfile` with a «Поточний пароль» (Current password) field for changing the password and e-mail; a `must_change_password` flag set when an account is created by the administrator (panel middleware with a whitelist of profile and logout routes).
3. **«Запам'ятай мене»** (Remember me): a 30-day duration instead of the current 400 days (`auth.guards.web.remember = 43200` before the guard is created, or `setRememberDuration(43200)`); for `admin` — disable the checkbox.
4. **Notifications** to the owner in Telegram: a login from a new IP, creation/deletion/role change of a user, 5 lockouts per hour (the data already exists in the `security` journal and `last_login_ip`).
5. **News publication and Telegram**: decide whether an editor needs auto-posting (currently, publishing a news item by an editor sends a message to the channel); the options are confirmation by an administrator or a separate right.
6. Documentation: `posibnyk-administratora.md` (sections «Вхід» (Login), «Користувачі» (Users)), ARCHITECTURE «Авторизация и роли» (Authorization and roles).

Acceptance criterion: administrator login without TOTP is impossible (**done**); a password change without the current password is rejected; tests are green.

## Stage 3 — observability and resilience (4–5 days)

1. **Content change log**: `spatie/laravel-activitylog ^4` on News/Page/Document/Specialty/Department/Staff/Setting/User (who, when, which fields); the resource «Журнал змін» (Change log) only for `admin`; scheduled cleanup via a workflow.
2. **Telegram token in `.env`** (`TELEGRAM_BOT_TOKEN`): `TelegramPoster` reads `config('services.telegram.token')`; the «Telegram» page shows only the status «токен задано на сервері» (token set on the server); the key is removed from `settings` by a migration; the token is rotated via @BotFather.
3. **External backups**: a scheduled workflow — daily `otfk:backup` over SSH, download of the fresh dump, encryption with `gpg`, upload to external storage (S3-compatible or the college's Google Drive); a storage copy at a frequency matching the acceptable data loss (RPO), not automatically once a month. Before implementation, define RPO/RTO, retention, protection of the copies from deletion from a compromised hosting, independent storage of the keys, and notifications about failures or overdue copies. Rehearse the restore of the DB and files in an isolated environment without Telegram, with an entry in DEPLOY.md.
4. **`map_embed`**: an allowlist of origins (`google.com/maps`); everything else is rejected on save.
5. **Retention of personal data**: IP addresses in the `security` log — 90 days (already in place); `sessions` — cleanup by `SESSION_LIFETIME`; a retention policy for `last_login_ip`.

Acceptance criterion: the journal shows who changed a page; there is no token in the DB; the DB and files fit the agreed RPO; an overdue copy triggers a notification; a restore of the DB and files has been performed locally and measured against the RTO.

## Stage 4 — process (ongoing)

- Monthly: `composer audit`, `composer outdated --direct`, `npm audit`; patch/minor updates within `platform.php`, deployment; review of `security-*.log` and of the account list («Останній вхід» — dismissed and inactive users).
- Quarterly: access review (dismissed staff removed on their last day), a restore rehearsal, external probes (`curl -I /admin/login`, `/.env`, `/.git/HEAD`, `/storage/_probe.php`), a check that the scheduled workflows have not been disabled by GitHub for inactivity.
- Yearly: a plan for major upgrades (PHP, Laravel, Filament 4/5), a review of the audit.
- Development rules — section 7 of the audit.

## Readiness for the production transfer without SEO — assessment of 07.10.2026

The application foundation is implemented: public sections, translations, roles, mandatory TOTP, upload protection and the CI deployment gate. Readiness for switching the domain requires a separate acceptance of the data and of the target hosting. The PoC list in ARCHITECTURE describes the sources of demo data, not the proven current content of the DB: part of the content has already been imported. In this assessment, remote reading of the test DB did not succeed; the actual presence of placeholders and the state of the file queue are not confirmed.

Before the domain is switched:

- Accept the actual content: contacts, staff, specialties/programmes, documents, banners, videos, statistics, FAQ and the bell schedule; remove the remaining demo records and the alpha badge. Check «Що наповнити» (What to fill in) in the admin; a short text on its own is not an error. Proofread the import and the English translations, or agree the scope of the English version. Decide on publishing the quiz with the methodologists.
- Check all attachments and old links: `otfk:check-links`, the states of `file_mirrors`, `otfk:mirror-files --verify`; retry failed downloads and selectively open PDFs and images. LinkChecker checks only the original bodies of Page/News, not the English fields and not all content of other entities; a full crawl of both locales remains a separate check.
- Rehearse the transfer of a current dump and public storage to MySQL/PHP 8.3, keeping APP_KEY, without seed, with sha256 verification and 2FA. For the duration of the final copy, stop editorial changes, define the rollback method and verify the restore. A code rollback does not automatically roll back the DB and files.
- Pass Stage 1 on the target hosting: HTTPS, the public document root, closed `.env`/`.git`, no execution in `/storage/`, cookies, login/logout/2FA and the journal, allowed and forbidden uploads, the report on old HTML. Set up personal accounts and a backup administrator.
- Check the site's PHP and CLI, extensions, storage:link, Filament/Vite assets, caches, cron, mysqldump, permissions and disk volume; reconfigure the Actions secrets for the new server. Check the manual deployment and the pages after it. The current deploy.yml changes the working directory step by step, without atomic release switching; agree the transfer window.
- Set up regular copies of the **DB and files** outside the hosting, and a notification of failure or overdue copies; verify the restore. Currently only a weekly DB dump on the same server is scheduled automatically; storage-export runs separately. Set up availability monitoring and a person responsible for incidents.
- Accept the site on phone and PC: menu, search UK/EN, the locale switcher, documents, tables, galleries, calendar, staff and editorial scenarios. A successful build and SQLite tests do not replace browser acceptance and checks on MySQL.

Additional improvements as separate tasks: a profile with the current password and a shorter remember-session; the change log; restricting the map_embed origin. If Telegram is used — moving the token from settings to the server configuration, rotation and verification of sending; currently `telegram_posted_at` is set before the HTTP request, so a failure requires a manual retry. If the channel is not needed at launch, auto-posting can be left off. A global redesign, cleanup of unused dependencies and removal of the import commands are not mandatory conditions for the transfer.

## What not to do

- Do not convert jobs to `ShouldQueue` — there is no worker.
- Do not enable self-registration or e-mail password reset while the production mail is `log`.
- Do not treat renaming `/admin` or an IP allowlist as protection: staff have dynamic IPs and the path can be guessed; this is acceptable only as an additional layer for `admin`.
- Do not introduce a full CSP with nonces without a separate task.
- Do not run `db:seed`/`SiteSeeder` on an environment that has content.
- Do not weaken the upload whitelist «because a file does not upload» — add a specific extension and MIME type deliberately, with a test.
- Do not disable the blocking `composer audit` in CI.

## Open questions for the owner

1. How many staff will work in the admin, and which of them is «Адміністратор» (Administrator)? (This determines the urgency of 2FA.)
2. Are staff ready for a TOTP app on their phone? If not — 2FA only for `admin`.
3. Where should external backups be stored?
4. Is an SVG favicon needed? Currently only raster formats are uploaded; the alternative is to place an SVG in `public/` manually.
5. Should «Запам'ятай мене» (Remember me) be kept for editors?
