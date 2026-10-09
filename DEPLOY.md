# Deploying OTFK to Hosting Ukraine (ukraine.com.ua)

Plan "Best" (Кращий): **SSH** is available, **Composer 2** (already installed), and a **PHP version** selector — so deployment follows the standard path.

> ✅ Before the production deployment, the following is already done in code: `User::canAccessPanel()` (otherwise Filament returns 403 in production) and the `.env.production.example` template.

> 🧭 **Two environments (since 2026-10-09):** `master` is the production branch: a push to it publishes to the Plesk subdomain `new.otfk.od.ua` via the `plesk-build` branch and Laravel Toolkit (see section "Plesk: new.otfk.od.ua" below); `test` is the test branch: a push to it deploys over SSH to the test hosting just-test.shop (repository secrets). Tasks are first merged into `test` and verified on just-test.shop; a release means moving `test` into `master`. The `prod` branch (2026-10-08…09) has been retired. The old site on otfk.od.ua keeps working as before in the meantime.
>
> 🚀 **Autodeploy (test hosting):** after the initial setup described in this document, updates are deployed automatically — the workflow `.github/workflows/deploy.yml` runs on every push to `test` (or manually via Run workflow from a branch other than `master`): after the tests and the audit in the same workflow succeed, it builds the frontend in CI, runs `git reset --hard ${{ github.sha }}` + `composer install` over SSH, uploads `public/build/` with rsync, and runs `migrate --force` + rebuilds the caches. Required repository secrets: `REMOTE_KEY` (private SSH key), `REMOTE_HOST`, `REMOTE_USER`, `REMOTE_PATH` (site directory), optionally `REMOTE_PORT`. The section "Updating the site later" below is the manual fallback path.
> Security re-review of 06.10.2026: `deploy.yml` implements its own `tests` job; `deploy` depends on it and installs the same SHA. A manual run outside `test` requires a separate check of how the selected SHA is obtained. The atomicity of the check and the write in `otfk:sanitize-content --apply` is implemented with a conditional UPDATE (sections 16–17 of the audit). Acceptance on the hosting remains mandatory; a local test of the command requires storage isolation so that it does not delete working backups.
>
> ⚠️ `public/build/` is **no longer** committed to git — the build lives only in CI/deploy; locally use `npm run build` or `npm run dev`. The admin theme imports CSS from `vendor/filament`, so `composer install` is required before the build (in `deploy.yml` — `composer install --no-dev --no-scripts`).

---

## 0. Local preparation (one time)

```bash
# 1) build the frontend (creates public/build — the hosting has no npm, so we build here; vendor/ is required — composer install)
npm run build

# 2) find out your APP_KEY (needed for .env on the server)
#    it is already in your local .env, the line APP_KEY=base64:...
```

PHP version: **8.3** (`platform.php = 8.3.0` is fixed in `composer.json`, CI tests on 8.3; `composer.lock` is built for this version).

---

## Option A — via Git + SSH (recommended: convenient for updates)

The server has no `vendor/` and no `public/build` (both are in `.gitignore`): install `vendor` with Composer on the server, and build `public/build` locally (`npm run build`) during the first deployment and upload it to `~/YOUR-DOMAIN/www/public/build` (scp/sftp); after that, autodeploy updates it.

### 1. Upload the code
```bash
# in the panel, select PHP version 8.3 for the site (Сайти → Налаштування → Версія PHP)
# connect over SSH and go to the site directory:
cd ~/YOUR-DOMAIN/www

# clone the repository (or upload the files with the file manager)
git clone https://github.com/YOUR_REPOSITORY.git .
```

### 2. Dependencies + .env
```bash
# Composer is already installed. If `php` in PATH is not the right version, specify the explicit path, e.g. /usr/local/php83/bin/php
composer install --no-dev --optimize-autoloader

cp .env.production.example .env
# edit .env: APP_URL, APP_KEY, DB_*
# when migrating an existing database, keep the APP_KEY of the source server (2FA secrets)
nano .env
```

### 3. Database
In the hosting panel: **MySQL → create a database + user**, and grant the user rights on the database. Put them into `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST=localhost`).

> ⚠️ Set your own `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` **before** this command: outside the `local`/`testing` environments, the seeder stops with an error without `ADMIN_PASSWORD` (there is no default password on the server). The password must be at least 12 characters long, with letters and digits. Run the seed **before** `php artisan optimize` (the config cache breaks reading env values in the seeder).

```bash
# creates all tables AND populates the site (menu, pages, demo content) + an admin from ADMIN_EMAIL/ADMIN_PASSWORD
php artisan migrate --seed --force
```

### 4. Assets, symlink, cache
```bash
php artisan filament:assets      # publish the admin CSS/JS to public (otherwise the admin has no styles)
php artisan storage:link         # symlink public/storage → storage/app/public (for uploaded photos)
php artisan optimize             # cache configs/routes/views (faster). If there are problems: php artisan optimize:clear
```
`public/build` is **not** stored in git: with autodeploy, CI builds it and uploads it via rsync; for the first manual deployment, build it locally (`npm run build`) and upload the directory yourself.

### 5. Site root → public
In the panel: **Сайти → Налаштування → Кореневий каталог** (Sites → Settings → Document root) → set it to `public` (an alternative is a `.htaccess` redirect to `public`, but changing the root in the panel is cleaner).

---

## Option B — archive (simpler for the first time, no Git)

1. **Locally:** `composer install --no-dev --optimize-autoloader` + `npm run build`.
2. Archive the project into a `.zip` **without** `node_modules`, `.git`, `.env` (but **with** `vendor/` and `public/build/`).
3. In the hosting file manager: upload the zip into the site directory and extract it.
4. Create the database in the panel; upload `.env` (from `.env.production.example`).
5. **Database:** either `php artisan migrate --seed --force` over SSH, or export the local database
   `mysqldump -u root otfk > otfk.sql` and import `otfk.sql` through **phpMyAdmin** in the panel.
6. Over SSH (once): `php artisan filament:assets && php artisan storage:link && php artisan optimize`.
7. Site root → `public` (as in Option A, step 5).

---

## Plesk: new.otfk.od.ua (branch `master`)

The new site lives on a subdomain of the otfk.od.ua subscription, next to the old one. Everything that depends on the domain is tied to `SEO_PRIMARY_HOST=otfk.od.ua`, so the subdomain is automatically `noindex`, without GA4 and without the www/HTTPS rules from `public/.htaccess`; the redirects of old addresses remain on the subdomain. **Do not change `SEO_PRIMARY_HOST` to the subdomain.**

**Why not SSH.** The subscription's SSH is `/bin/bash (chrooted)`, the user cannot change it; inside the chroot only PHP 7.2 is available. Therefore, the deployment is done by the Plesk **Laravel Toolkit** (Git + Composer + artisan + scheduler on the site's PHP 8.3), and there is no Node on the server — the frontend is built by CI.

**How the code travels:** a push to `master` → `deploy.yml` (job `tests`, then `deploy-plesk`) builds the frontend and publishes the **`plesk-build`** branch = the `master` tree + `public/build` (a commit with the parents "previous plesk-build" and "verified master SHA"; the branch only moves forward) → the webhook `PLESK_DEPLOY_WEBHOOK` (repository secret) starts the deployment in Plesk. Without the secret, the Plesk deploy button is pressed manually. Do not commit to `plesk-build` by hand.

**Plesk panel (subdomain `new.otfk.od.ua`):**
- "Сертифікати SSL/TLS" (SSL/TLS certificates): Let's Encrypt; in "Хостинг та DNS" (Hosting & DNS) — redirect HTTP → HTTPS.
- Document root: Toolkit installs the application into the current root and moves the root to its `public` by itself (`artisan` — in the parent directory). For a new installation, keep the root `new.otfk.od.ua`; the current installation's root was previously `…/public`, so the application is in `new.otfk.od.ua/public`, and the web root is `new.otfk.od.ua/public/public`.
- "PHP": 8.3, "FPM-застосунок обслуговується Apache" (FPM application served by Apache) (not nginx — `.htaccess` is needed), `upload_max_filesize` 25M, `post_max_size` 32M (the admin accepts files up to 20 MB).
- "Налаштування Apache і nginx" (Apache and nginx settings): disable "Обслуговувати статичні файли напряму через nginx" (Serve static files directly through nginx) — otherwise the `/storage/` files bypass `storage/app/public/.htaccess` (CSP `sandbox` for HTML/SVG).
- "Бази даних" (Databases): a separate database and user; do not touch the old site's database.
- Subscription quota — 10 GB for the old and new sites together (account for `storage/mirror` and backups).

**Initial installation (completed 2026-10-08):**
1. Laravel Toolkit ("Почніть роботу" (Get started) → Laravel) → from Git `https://github.com/gotthejuicee/otfk.git`. The wizard does not ask for a branch and takes `master` — after installation, switch the branch to **`plesk-build`** in the domain's "Git" settings and deploy again. Toolkit puts the application into the **current document root** and moves the root to its `public`: with the root `new.otfk.od.ua/public`, the application ended up in **`new.otfk.od.ua/public`**, and the web root is **`new.otfk.od.ua/public/public`** (left as is; `.env`/`composer.json` outside — 403).
2. `.env` ("Змінні середовища" (Environment variables) in Toolkit): Toolkit creates a local template (`APP_ENV=local`, `APP_DEBUG=true`, **SQLite**) — replace it entirely using `.env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://new.otfk.od.ua`, `DB_CONNECTION=mysql` + `DB_*`, `SESSION_SECURE_COOKIE=true`; do not change `SEO_PRIMARY_HOST=otfk.od.ua`. When migrating the DB — **the `APP_KEY` of the source server** (otherwise the 2FA secrets cannot be decrypted). After any change to `.env` — in "Виконавець" (Executor): `optimize:clear`, `config:cache` (deployment caches the config; a sign of an old config is `<title>Laravel` and the `laravel-session` cookie).
3. Data: a dump of the test hosting DB ("Бази даних" → "Імпортувати дамп" (Import dump), `.zip`), **not** `db:seed`; verification — `db:show` in "Виконавець".
4. The `storage/app/public` files (3.1 GB) were uploaded over FTP (`lftp mirror -R`, FTPS). **The subscription user's FTP opens in the `httpdocs` of the old site** — a relative path `new.otfk.od.ua/...` creates a folder inside the old site. The correct location is `/new.otfk.od.ua/public/storage/app/public/` (from the subscription root); move files within the server using "Файли" (Files) → "Перемістити" (Move). Do not put the files into the web root `public/public/storage` — there must be a **link** created by `storage:link` (otherwise there is no protective `.htaccess` and new uploads are not visible).
5. Toolkit's deployment script ("Розгортання" (Deployment)): stages maintenance mode + composer + "Запуск сценарію розгортання" (Run deployment script); **package.json is disabled** (Node 21 in Toolkit does not suit Vite 7; the build is already in the branch). The script runs in a chroot where `php` is 7.2, so use the **full path**:
   ```
   /opt/plesk/php/8.3/bin/php artisan migrate --force
   /opt/plesk/php/8.3/bin/php artisan optimize:clear
   /opt/plesk/php/8.3/bin/php artisan config:cache
   /opt/plesk/php/8.3/bin/php artisan route:cache
   /opt/plesk/php/8.3/bin/php artisan view:cache
   ```
   Do not enable the queue worker — the project uses `afterResponse()`.
6. "Виконавець" (Executor): `storage:link`.
7. Scheduler: enable "Заплановані завдання" (Scheduled tasks) in the Toolkit panel (`schedule:run`). `otfk:backup` requires `mysqldump`; if it is not in the task's environment, rely on Plesk's "Резервна копія та відновлення" (Backup and restore).
8. Automatic deployment: "Розгортання" (Deployment) → mode **"Автоматичний"** (Automatic); the webhook URL (`https://hosting9.tenet.ua:8443/modules/git/public/web-hook.php?uuid=…`) goes into the GitHub secret `PLESK_DEPLOY_WEBHOOK`. In manual mode, the webhook only pulls the code without deploying it.

`mirror-files.yml` works only over SSH of the test hosting; on Plesk, the `file_mirrors` queue is processed by the scheduler (or by the command `otfk:mirror-files` in the Artisan tab).

**Check:** `curl -sI https://new.otfk.od.ua/` — the response has `X-Robots-Tag: noindex, nofollow`; `/`, `/en`, `/admin` (login with 2FA) open; the probe `storage/app/public/_probe.php` → 403 (section 6 of `docs/security-audit.md`); manually `php artisan otfk:seo-smoke --base=https://new.otfk.od.ua --expect=closed` (not run in CI for Plesk — the deployment is asynchronous).

## Checklist — don't forget

- [ ] PHP **8.3** selected for the site and CLI (the CI version and `platform.php`)
- [ ] PHP extension **GD with WebP support** (for automatic image optimization). If it is missing, the site works, but images are not compressed to WebP (silently skipped). Check: `php -r "var_dump(function_exists('imagewebp'));"`
- [ ] Site root = **`public`**
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` filled in, `APP_URL=https://DOMAIN`, `DB_*`, `SESSION_SECURE_COOKIE=true`, `ADMIN_EMAIL`/`ADMIN_PASSWORD`
- [ ] Indexing: `SEO_INDEXING=auto` (default) opens the index only on `SEO_PRIMARY_HOST=otfk.od.ua`; the test hosting automatically gets `X-Robots-Tag: noindex, nofollow`. After launch on the main domain, verify: `curl -sI https://otfk.od.ua/` without `X-Robots-Tag`, and `/robots.txt` without `Disallow: /`
- [ ] To migrate an existing site: import the current DB dump and run `php artisan migrate --force`; **without seed**. `migrate --seed --force` is only for an empty demo environment, after which the demo content must be replaced
- [ ] `php artisan storage:link` (photos from the admin)
- [ ] `php artisan filament:assets` (admin styles)
- [ ] `public/build` uploaded to the server (by autodeploy or manually after `npm run build`)
- [ ] write permissions: `chmod -R 775 storage bootstrap/cache` (if 500 errors occur)

## Verify after deployment

The SEO smoke check runs automatically in `deploy.yml` if the repository variables are set (Settings → Secrets and variables → Actions → Variables): `SEO_SMOKE_BASE_URL` (for example `https://just-test.shop`) and `SEO_SMOKE_EXPECT` (`closed` for the test hosting, `indexable` for otfk.od.ua — switch it in the same window when the barrier is lifted). Manually: `php artisan otfk:seo-smoke --base=https://otfk.od.ua --expect=indexable --check-redirects --sitemap-sample=10`; before the DNS switch, add `--resolve=otfk.od.ua:443:<IP of the new server>`. Right after the switch: `curl -I http://otfk.od.ua/` and `https://www.otfk.od.ua/` — a single 301 to `https://otfk.od.ua/` without a loop; if there is a loop, enable "redirect to HTTPS" in the hosting panel and remove the HTTPS rule from `public/.htaccess`.

After switching the domain, additionally:

- [ ] Hosting panel "forced HTTPS" setting on the otfk.od.ua vhost: if the panel redirects to https before `.htaccess` does (as on just-test.shop), old links such as `http://otfk.od.ua/news/…/` go through 2 hops (panel → https, then Laravel → new address). For a single 301 — disable it and keep the HTTPS rule in `public/.htaccess` (verify there is no loop).
- [ ] `curl -I https://otfk.od.ua/index.php` and `curl -I https://otfk.od.ua/index.php/spetsialnosti` — a single 301 to `/` and `/spetsialnosti` (the `public/.htaccess` rule).
- [ ] Remove the test GA4 ID `G-TEST000000` (`php storage/app/private/privacy-2026-10-08/set_test_ga_id.php --remove` on a copy with the test DB, or clear the field in "SEO → Розмітка та аналітика" (Markup and analytics)) and only then enter the real ID.

1. `https://DOMAIN/` — the site, tiles, news
2. `https://DOMAIN/admin` — log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` → change the password in the profile, create personal accounts for staff with the "Редактор" (Editor) role (`Налаштування → Користувачі` (Settings → Users))
4. Post-deployment security checklist — section 6 of [`docs/security-audit.md`](docs/security-audit.md): headers on `/admin/login`, probe `storage/app/public/_probe.php` → 403, log `storage/logs/security-*.log`; then `php artisan otfk:sanitize-content` (report) and `php artisan otfk:sanitize-content --apply` (cleaning of old HTML with a backup)
3. Upload the logo, a banner, a couple of news items — check that the photos display (requires `storage:link`)

## Two-factor protection and access recovery

All admin users connect an authenticator app (Google Authenticator, Aegis) on their first login after deployment; each has 10 one-time recovery codes. Three recovery levels, so the admin never gets "locked out":

1. **A user lost their phone** — enters a recovery code, then reconnects the app in "Двофакторний захист" (Two-factor protection) (`/admin/two-factor-setup`).
2. **Both the phone and the codes are lost** — an administrator presses "Скинути 2FA" (Reset 2FA) in "Налаштування → Користувачі" (Settings → Users) (only after verifying the person's identity); the user reconnects the app on the next login.
3. **No administrator can log in** — over SSH on the hosting:

```bash
php artisan otfk:two-factor --status            # who is connected, how many codes are left
php artisan otfk:two-factor admin@DOMAIN --reset # reset the factor of a specific user
```

   If there is no SSH access to the command either — temporarily set `TWO_FACTOR_ENFORCE=false` in `.env` (+ `php artisan config:cache`), log in, reset/reconnect, then set it back to `true`. All events (`2fa.enabled/passed/failed/recovery_used/reset/lockout`) are written to `storage/logs/security-*.log`.

## Cron on the hosting (backups + Laravel schedule)

Already configured in the code:
- **every Sunday at 03:30** — `php artisan otfk:backup` (database dump into `storage/app/backups`);
- **every Sunday at 04:00** — cleanup of old visit statistics.
- **every minute** — `php artisan otfk:mirror-files --limit=30` (the `file_mirrors` queue: the server itself downloads the files of the old otfk.od.ua site into `storage/app/public/mirror/`; with no entries in the queue, it only checks the queue).

For this to work, add **one** cron job in the hosting panel (every minute):

```bash
* * * * * cd /home/LOGIN/YOUR-DOMAIN/www && php artisan schedule:run >> /dev/null 2>&1
```

Replace the `cd` path with your site directory. Manual check:

```bash
php artisan schedule:list
php artisan otfk:backup
```

## Old-address map and 404 log

Old addresses that have changed are transferred via the `legacy_redirects` table: a 301/308 redirect to a new page or file, or 410 for a deliberately deleted item. In bulk — from a CSV (columns `source,target,code,note`; `target` — only a relative path `/...`):

```bash
php artisan otfk:legacy-redirects storage/app/private/redirects.csv
php artisan otfk:legacy-redirects storage/app/private/redirects.csv --apply
```

The first run only shows new/changed records and conflicts (a live address, a missing or external target, duplicates, chains); `--apply` writes the rows without conflicts and saves a copy of the CSV and a report into `storage/app/private/legacy-redirects/`. Repeated runs are idempotent.

The CSV is built by `php artisan otfk:legacy-map --out=storage/app/private/legacy-map/map.csv --sitemap=https://otfk.od.ua/sitemap.xml --urls=gsc-pages.csv --scan-dir=<saved pages of the old site> --verify` (read-only; run it on the hosting where the `storage` files are). The editor works through `map.csv.unmapped.csv` (material / 410 according to the approved list / 404) and `map.csv.conflicts.csv`, extends the CSV, and then does a dry-run and `--apply` with the command above. Point edits — "SEO → Редиректи старих адрес" (SEO → Old address redirects) in the admin; addresses where visitors get a 404 — "SEO → Журнал 404" (SEO → 404 log) (where "Створити редирект" (Create redirect) is also available). Log entries without visits for more than 90 days are removed by `model:prune` (via cron `schedule:run`). Files that physically live in `public/` are served by Apache without Laravel — for them, a redirect will not work.

## Files of the old site and migration to permanent hosting

**Mirroring.** The files of the old site are not uploaded manually: the URL is put into the `file_mirrors` table (`App\Models\FileMirror::enqueue($url)` or an INSERT with `source_hash = SHA2(source_url, 256)` and `status = 'pending'`); the cron `schedule:run` runs `otfk:mirror-files` every minute; the file appears at `FileMirror::publicUrl()` (`/storage/mirror/otfk.od.ua/...`). Only **relative** paths `/storage/...` are inserted into content — so a domain change does not break the links. Allowed hosts — `FILE_MIRROR_HOSTS` (default `otfk.od.ua,www.otfk.od.ua`), size limit — `FILE_MIRROR_MAX_MB` (100). Status: `SELECT status, COUNT(*) FROM file_mirrors GROUP BY status`; errors — the `error` column; retry of failed ones — `php artisan otfk:mirror-files --retry-failed`. Requires cron and outbound HTTPS from the hosting. Without cron, the queue can be processed manually: GitHub → Actions → "Mirror legacy files" → Run workflow (or `gh workflow run mirror-files.yml -f limit=500`) — the same SSH secrets as for the deployment.

**Migration from temporary hosting to permanent hosting** (deletes nothing on the old server):

1. On the old server: `php artisan otfk:backup` (database dump into `storage/app/backups/otfk_*.sql.gz`) and `php artisan otfk:storage-export` (archive `storage/app/backups/storage_*.zip`: the whole `public` disk — admin uploads, imported photos, `mirror/` — with a sha256 manifest).
2. Transfer both files to the new server (scp/rsync or the panel's file manager; do not put them into Git — `storage/` is ignored).
3. On the new server: deploy the code per "Option A", import the dump into the new DB, run `php artisan migrate --force`, `php artisan storage:link`.
4. Restore the files: `php artisan otfk:storage-import /path/to/storage_….zip` — writes the missing files and verifies sha256; existing files with different content are only shown as conflicts (to replace them — `--overwrite`).
5. Fetch the mirror files that are missing or damaged: `php artisan otfk:mirror-files --verify --limit=1000` (from the otfk.od.ua source). If the original is already unavailable but the old hosting still works: `php artisan otfk:mirror-files --verify --from=https://OLD-DOMAIN --limit=1000` — the file is taken from `/storage/...` on the old hosting and accepted only if it matches the recorded sha256. Repeat until nothing is left in the queue.
6. Add the cron `schedule:run` on the new hosting, update `APP_URL`, run `php artisan optimize`. Check `/`, `/en`, `/admin`, and a few pages with files.

**Security during the migration (otherwise the admin will not let anyone in):**

- **`APP_KEY` — the same one as on the old server.** It encrypts the secrets and the 2FA recovery codes in the DB (and the cookies/sessions). A new `key:generate` breaks login for all users; if this has already happened — `TWO_FACTOR_ENFORCE=false`, log in, run `php artisan otfk:two-factor <email> --reset` for each user, then set it back to `true`.
- `.env`: `TWO_FACTOR_ENFORCE=true`, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `APP_URL` with the new domain (template — `.env.production.example`).
- Exact server time (NTP): TOTP codes live for 30 s; a discrepancy of more than a minute = "invalid code" for everyone. Check: `date -u`.
- Document root = `public/`; repeat the probe `storage/app/public/_probe.php` → 403 (section 6 of `docs/security-audit.md`). If the new hosting runs nginx without Apache, `.htaccess` does not work — write the ban on scripts in `/storage/` into the nginx config (`location ~* ^/storage/.*\.php$ { return 403; }`); the application's upload allowlist works independently.
- `storage/logs/security-*.log` and `storage/app/private/sanitize-backup-*.json` are not included in `storage-export` — copy them manually if needed.
- GitHub Actions secrets (`REMOTE_HOST`, `REMOTE_USER`, `REMOTE_KEY`, `REMOTE_PATH`, `REMOTE_PORT`) must be moved to the new server (for `test`; the Plesk branch `master` is deployed via Laravel Toolkit and does not use the SSH secrets) — otherwise autodeploy will keep going to the old server.

## Availability monitoring (no code)

Free options: [UptimeRobot](https://uptimerobot.com) or Better Stack — ping `https://your-domain/` every 5 minutes. Notifications by email/Telegram if the site goes down.

## Background tasks (Telegram)

Automatic posting of news to Telegram is sent
**after** the page is returned — via `dispatch(...)->afterResponse()`. This is
a terminating callback: Laravel executes it in the same process at the
`terminate()` stage, already after the response has been sent to the browser. Therefore:

- **a queue worker is not needed** (there is none on shared hosting) — `QUEUE_CONNECTION`
  is not used for these tasks, and the `jobs` table does not accumulate;
- the visitor/admin does not wait for the Telegram response;
- **do not convert these tasks to `ShouldQueue`** without a running `queue:work` —
  otherwise they go into the queue and will never run without a worker.

If the Telegram post was not sent (the API is unavailable), the news item is still marked
as published (duplicate protection), and the error is written to the log (`Log::warning`).
To retry manually: clear `telegram_posted_at` for the news item and save it again.

## Updating the site later (Option A)

Usually nothing needs to be done — a push to `master` (Plesk) or `test` (test hosting) triggers autodeploy (`deploy.yml`). The manual path is for the case when CI is unavailable (then build the frontend locally and upload `public/build` yourself):

If the database still contains the result of the old migration `2026_08_28_180000_drop_applicant_feedback_testimonials`, a regular `migrate --force` restores the missing tables via `2026_10_04_175000_restore_missing_content_tables` before the homepage block translation (`180000`). The restoration does not run the seeder and does not change existing records. The latest translation migration allows a repeat after a partially executed MySQL DDL: only the missing fields are added. Do not delete records from `migrations` and do not roll back old content migrations to restore the schema. The built-in contact/application forms and the reviews have been removed from the interface; the tables remain as archives; the August drop migration in the merged code deletes nothing. Before the restoration, save the original records and verify the actual tables/fields; after it, check that the cache rebuild step succeeded and that `/`, `/en`, `/admin` respond.

```bash
cd ~/YOUR-DOMAIN/www
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

If the PR contained new migrations or the server still has images without WebP:

```bash
php artisan migrate --force
php artisan images:webp
```
