# Деплой ОТФК на Хостинг Україна (ukraine.com.ua)

Тариф «Кращий»: есть **SSH**, **Composer 2** (уже установлен), выбор **версии PHP** — значит деплой идёт по стандартному пути.

> ✅ Перед прод-деплоем уже сделано в коде: `User::canAccessPanel()` (иначе Filament отдаёт 403 на проде) и шаблон `.env.production.example`.

> 🧭 **Два окружения (с 2026-10-09):** `master` — продакшен-ветка: push в неё выкладывает на Plesk-поддомен `new.otfk.od.ua` через ветку `plesk-build` и Laravel Toolkit (раздел «Plesk: new.otfk.od.ua» ниже); `test` — тестовая: push в неё выкладывает по SSH на тестовый хостинг just-test.shop (секреты репозитория). Задачи сначала сливаются в `test` и проверяются на just-test.shop, выпуск — перенос `test` в `master`. Ветка `prod` (2026-10-08…09) упразднена. Старый сайт на otfk.od.ua в это время работает как был.
>
> 🚀 **Автодеплой (тестовый хостинг):** после первичной настройки по этому документу обновления едут сами — workflow `.github/workflows/deploy.yml` на каждый push в `test` (или вручную через Run workflow из другой ветки, кроме `master`): после успешных тестов и аудита того же workflow собирает фронтенд в CI, по SSH делает `git reset --hard ${{ github.sha }}` + `composer install`, заливает `public/build/` rsync-ом и выполняет `migrate --force` + пересборку кэшей. Нужны секреты репозитория `REMOTE_KEY` (приватный SSH-ключ), `REMOTE_HOST`, `REMOTE_USER`, `REMOTE_PATH` (каталог сайта), опционально `REMOTE_PORT`. Раздел «Обновление сайта потом» ниже — ручной запасной путь.
> Переревью безопасности 06.10.2026: в `deploy.yml` реализован собственный job `tests`, `deploy` зависит от него и устанавливает тот же SHA. Ручной запуск вне `test` требует отдельной проверки получения выбранного SHA. Атомарность проверки и записи `otfk:sanitize-content --apply` реализована условным UPDATE (разделы 16–17 аудита). Приёмка на хостинге остаётся обязательной; локальный тест команды требует изоляции storage, чтобы не удалять рабочие бэкапы.
>
> ⚠️ `public/build/` больше **не** коммитится в git — сборка живёт только в CI/деплое; локально `npm run build` или `npm run dev`. Тема админки импортирует CSS из `vendor/filament`, поэтому перед сборкой нужен `composer install` (в `deploy.yml` — `composer install --no-dev --no-scripts`).

---

## 0. Подготовка локально (один раз)

```bash
# 1) собрать фронтенд (создаёт public/build — на хостинге нет npm, поэтому собираем тут; нужен vendor/ — composer install)
npm run build

# 2) узнать свой APP_KEY (понадобится для .env на сервере)
#    он уже есть в локальном .env, строка APP_KEY=base64:...
```

Версия PHP: **8.3** (в `composer.json` зафиксировано `platform.php = 8.3.0`, CI тестирует на 8.3; `composer.lock` собирается под эту версию).

---

## Вариант А — через Git + SSH (рекомендую: удобно обновлять)

На сервере нет `vendor/` и `public/build` (они в `.gitignore`): `vendor` ставим Composer'ом на сервере, а `public/build` при первом деплое собираем локально (`npm run build`) и заливаем в `~/ВАШ-ДОМЕН/www/public/build` (scp/sftp); дальше его обновляет автодеплой.

### 1. Залить код
```bash
# выбрать в панели версию PHP 8.3 для сайта (Сайти → Налаштування → Версія PHP)
# подключиться по SSH, перейти в каталог сайта:
cd ~/ВАШ-ДОМЕН/www

# клонировать репозиторий (или загрузить файлы файловым менеджером)
git clone https://github.com/ВАШ_РЕПОЗИТОРИЙ.git .
```

### 2. Зависимости + .env
```bash
# Composer уже есть. Если php в PATH не та версия — указать явный путь, напр. /usr/local/php83/bin/php
composer install --no-dev --optimize-autoloader

cp .env.production.example .env
# отредактировать .env: APP_URL, APP_KEY, DB_*
# при переносе существующей БД сохранить APP_KEY исходного сервера (секреты 2FA)
nano .env
```

### 3. База данных
В панели хостинга: **MySQL → создать базу + пользователя**, дать пользователю права на базу. Подставить их в `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST=localhost`).

> ⚠️ В `.env` задайте свои `ADMIN_EMAIL` и `ADMIN_PASSWORD` **до** этой команды: вне окружений `local`/`testing`
> сидер без `ADMIN_PASSWORD` останавливается с ошибкой (дефолтного пароля на сервере нет). Пароль — минимум 12 символов,
> буквы и цифры. Делайте seed **до** `php artisan optimize` (кэш конфига ломает чтение env в сидере).

```bash
# создаст все таблицы И наполнит сайт (меню, страницы, демо-контент) + админа из ADMIN_EMAIL/ADMIN_PASSWORD
php artisan migrate --seed --force
```

### 4. Ассеты, симлинк, кеш
```bash
php artisan filament:assets      # опубликовать CSS/JS админки в public (иначе админка без стилей)
php artisan storage:link         # симлинк public/storage → storage/app/public (для загруженных фото)
php artisan optimize             # кеш конфигов/роутов/вью (быстрее). При проблемах: php artisan optimize:clear
```
`public/build` в git **не** зберігається: при автодеплої його збирає CI і заливає rsync-ом; при першому ручному деплої зберіть локально (`npm run build`) і завантажте каталог самі.

### 5. Корень сайта → public
В панели: **Сайти → Налаштування → Кореневий каталог** → указать `public`
(альтернатива — `.htaccess`-редирект на `public`, но смена корня в панели чище).

---

## Вариант Б — архивом (проще для первого раза, без Git)

1. **Локально:** `composer install --no-dev --optimize-autoloader` + `npm run build`.
2. Заархивировать проект в `.zip` **без** `node_modules`, `.git`, `.env` (но **с** `vendor/` и `public/build/`).
3. В файловом менеджере хостинга: загрузить zip в каталог сайта и распаковать.
4. Создать БД в панели; залить `.env` (из `.env.production.example`).
5. **База:** либо `php artisan migrate --seed --force` по SSH, либо экспортировать локальную базу
   `mysqldump -u root otfk > otfk.sql` и импортировать `otfk.sql` через **phpMyAdmin** в панели.
6. По SSH (один раз): `php artisan filament:assets && php artisan storage:link && php artisan optimize`.
7. Корень сайта → `public` (как в Варианте А, шаг 5).

---

## Plesk: new.otfk.od.ua (ветка `master`)

Новый сайт живёт на поддомене подписки otfk.od.ua рядом со старым. Всё, что зависит от домена, привязано к `SEO_PRIMARY_HOST=otfk.od.ua`, поэтому поддомен автоматически `noindex`, без GA4 и без правил www/HTTPS из `public/.htaccess`; редиректы старых адресов остаются на поддомене. **`SEO_PRIMARY_HOST` на поддомен не менять.**

**Почему не SSH.** SSH подписки — `/bin/bash (chrooted)`, пользователь его не меняет; внутри chroot только PHP 7.2. Поэтому выкладку делает **Laravel Toolkit** Plesk (Git + Composer + artisan + планировщик на PHP 8.3 сайта), а Node на сервере нет — фронтенд собирает CI.

**Как едет код:** push в `master` → `deploy.yml` (job `tests`, затем `deploy-plesk`) собирает фронтенд и публикует ветку **`plesk-build`** = дерево `master` + `public/build` (коммит с родителями «прошлый plesk-build» и «проверенный SHA master», ветка только движется вперёд) → вебхук `PLESK_DEPLOY_WEBHOOK` (секрет репозитория) запускает развёртывание в Plesk. Без секрета — кнопка развёртывания в Plesk вручную. В `plesk-build` руками не коммитить.

**Панель Plesk (поддомен `new.otfk.od.ua`):**
- «Сертифікати SSL/TLS»: Let's Encrypt; в «Хостинг та DNS» — перенаправление HTTP → HTTPS.
- Корень документов: Toolkit ставит приложение в текущий корень и сам переводит корень на его `public` (`artisan` — в родительском каталоге). Для новой установки оставить корень `new.otfk.od.ua`; у нынешней установки корень заранее был `…/public`, поэтому приложение в `new.otfk.od.ua/public`, веб-корень `new.otfk.od.ua/public/public`.
- «PHP»: 8.3, «FPM-застосунок обслуговується Apache» (не nginx — нужен `.htaccess`), `upload_max_filesize` 25M, `post_max_size` 32M (админка принимает файлы до 20 МБ).
- «Налаштування Apache і nginx»: выключить «Обслуговувати статичні файли напряму через nginx» — иначе файлы `/storage/` уходят мимо `storage/app/public/.htaccess` (CSP `sandbox` для HTML/SVG).
- «Бази даних»: отдельная база и пользователь; старую БД сайта не трогать.
- Квота подписки — 10 ГБ на старый и новый сайт вместе (учитывать `storage/mirror` и бэкапы).

**Первичная установка (выполнена 2026-10-08):**
1. Laravel Toolkit («Почніть роботу» → Laravel) → из Git `https://github.com/gotthejuicee/otfk.git`. Мастер ветку не спрашивает и берёт `master` — после установки ветку переключить на **`plesk-build`** в «Git» домена и развернуть заново. Toolkit кладёт приложение в **текущий корень документов** и переносит корень на его `public`: при корне `new.otfk.od.ua/public` приложение оказалось в **`new.otfk.od.ua/public`**, а веб-корень — **`new.otfk.od.ua/public/public`** (так и оставлено; `.env`/`composer.json` снаружи — 403).
2. `.env` (Toolkit → «Змінні середовища»): Toolkit создаёт локальный шаблон (`APP_ENV=local`, `APP_DEBUG=true`, **SQLite**) — заменить целиком по `.env.production.example`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://new.otfk.od.ua`, `DB_CONNECTION=mysql` + `DB_*`, `SESSION_SECURE_COOKIE=true`; `SEO_PRIMARY_HOST=otfk.od.ua` не менять. При переносе БД — **`APP_KEY` исходного сервера** (иначе не расшифруются секреты 2FA). После любой правки `.env` — «Виконавець»: `optimize:clear`, `config:cache` (развёртывание кеширует конфиг; признак старого конфига — `<title>Laravel` и cookie `laravel-session`).
3. Данные: дамп БД тестового хостинга («Бази даних» → «Імпортувати дамп», `.zip`), **не** `db:seed`; проверка — `db:show` в «Виконавець».
4. Файлы `storage/app/public` (3,1 ГБ) заливались по FTP (`lftp mirror -R`, FTPS). **FTP пользователя подписки открывается в `httpdocs` старого сайта** — относительный путь `new.otfk.od.ua/...` создаёт папку внутри старого сайта. Правильное место — `/new.otfk.od.ua/public/storage/app/public/` (от корня подписки); переносить внутри сервера через «Файли» → «Перемістити». Не класть файлы в веб-корень `public/public/storage` — там должна быть **ссылка** `storage:link` (иначе нет защитного `.htaccess` и новые загрузки не видны).
5. Сценарий развёртывания Toolkit («Розгортання»): этапы режим обслуживания + composer + «Запуск сценарію розгортання»; **package.json выключен** (Node 21 в Toolkit не подходит Vite 7, сборка уже в ветке). Сценарий запускается в chroot, где `php` — 7.2, поэтому **полный путь**:
   ```
   /opt/plesk/php/8.3/bin/php artisan migrate --force
   /opt/plesk/php/8.3/bin/php artisan optimize:clear
   /opt/plesk/php/8.3/bin/php artisan config:cache
   /opt/plesk/php/8.3/bin/php artisan route:cache
   /opt/plesk/php/8.3/bin/php artisan view:cache
   ```
   Очередь (queue worker) не включать — в проекте `afterResponse()`.
6. «Виконавець»: `storage:link`.
7. Планировщик: «Заплановані завдання» на панели Toolkit включить (`schedule:run`). `otfk:backup` требует `mysqldump`; если его нет в окружении задачи — полагаться на «Резервна копія та відновлення» Plesk.
8. Автоматическое развёртывание: «Розгортання» → режим **«Автоматичний»**; URL вебхука (`https://hosting9.tenet.ua:8443/modules/git/public/web-hook.php?uuid=…`) — в секрет GitHub `PLESK_DEPLOY_WEBHOOK`. В ручном режиме вебхук только подтягивает код, не разворачивая.

`mirror-files.yml` работает только по SSH тестового хостинга; на Plesk очередь `file_mirrors` обрабатывает планировщик (или команда `otfk:mirror-files` во вкладке Artisan).

**Проверка:** `curl -sI https://new.otfk.od.ua/` — есть `X-Robots-Tag: noindex, nofollow`; `/`, `/en`, `/admin` (вход с 2FA) открываются; проба `storage/app/public/_probe.php` → 403 (раздел 6 `docs/security-audit.md`); вручную `php artisan otfk:seo-smoke --base=https://new.otfk.od.ua --expect=closed` (в CI для Plesk не запускается — развёртывание асинхронное).

## Чек-лист «не забыть»

- [ ] PHP **8.3** выбран для сайта и CLI (версия CI и platform.php)
- [ ] PHP-расширение **GD с поддержкой WebP** (для авто-оптимизации картинок). Если нет — сайт работает, но изображения не сжимаются в WebP (молча пропускается). Проверка: `php -r "var_dump(function_exists('imagewebp'));"`
- [ ] Корень сайта = **`public`**
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` заполнен, `APP_URL=https://домен`, `DB_*`, `SESSION_SECURE_COOKIE=true`, `ADMIN_EMAIL`/`ADMIN_PASSWORD`
- [ ] Индексация: `SEO_INDEXING=auto` (по умолчанию) открывает индекс только на `SEO_PRIMARY_HOST=otfk.od.ua`; тестовый хостинг получает `X-Robots-Tag: noindex, nofollow` автоматически. После запуска на основном домене проверить: `curl -sI https://otfk.od.ua/` без `X-Robots-Tag`, `/robots.txt` без `Disallow: /`
- [ ] Для переноса существующего сайта: импорт актуального дампа БД и `php artisan migrate --force`; **без seed**. `migrate --seed --force` — только для пустого демо-окружения, затем требуется замена демо-контента
- [ ] `php artisan storage:link` (фото из админки)
- [ ] `php artisan filament:assets` (стили админки)
- [ ] `public/build` залитий на сервер (автодеплоєм або вручну після `npm run build`)
- [ ] права на запись: `chmod -R 775 storage bootstrap/cache` (если будут ошибки 500)

## Проверить после деплоя

SEO smoke-проверка выполняется в `deploy.yml` автоматически, если заданы переменные репозитория (Settings → Secrets and variables → Actions → Variables): `SEO_SMOKE_BASE_URL` (например `https://just-test.shop`) и `SEO_SMOKE_EXPECT` (`closed` для тестового хостинга, `indexable` для otfk.od.ua — переключить в том же окне, когда снимается барьер). Вручную: `php artisan otfk:seo-smoke --base=https://otfk.od.ua --expect=indexable --check-redirects --sitemap-sample=10`; до переключения DNS — добавить `--resolve=otfk.od.ua:443:<IP нового сервера>`. Сразу после переключения: `curl -I http://otfk.od.ua/` и `https://www.otfk.od.ua/` — один 301 на `https://otfk.od.ua/` без цикла; при цикле включить «редирект на HTTPS» в панели хостинга и убрать HTTPS-правило из `public/.htaccess`.

После переключения домена дополнительно:

- [ ] Настройка панели хостинга «принудительный HTTPS» на vhost otfk.od.ua: если панель редиректит на https раньше `.htaccess` (так на just-test.shop), старые ссылки `http://otfk.od.ua/news/…/` проходят 2 перехода (панель → https, затем Laravel → новая адрес). Для одного 301 — выключить её и оставить HTTPS-правило `public/.htaccess` (проверить отсутствие цикла).
- [ ] `curl -I https://otfk.od.ua/index.php` и `curl -I https://otfk.od.ua/index.php/spetsialnosti` — один 301 на `/` и `/spetsialnosti` (правило `public/.htaccess`).
- [ ] Убрать тестовый GA4 ID `G-TEST000000` (`php storage/app/private/privacy-2026-10-08/set_test_ga_id.php --remove` на копии с тестовой БД или очистить поле в «SEO → Розмітка та аналітика») и только потом вписать настоящий ID.

1. `https://домен/` — сайт, плитки, новости
2. `https://домен/admin` — вход под `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env` → сменить пароль в профиле, создать личные учётки сотрудников с ролью «Редактор» (`Налаштування → Користувачі`)
4. Чек-лист безопасности после деплоя — раздел 6 [`docs/security-audit.md`](docs/security-audit.md): заголовки на `/admin/login`, проба `storage/app/public/_probe.php` → 403, журнал `storage/logs/security-*.log`; затем `php artisan otfk:sanitize-content` (отчёт) и `php artisan otfk:sanitize-content --apply` (очистка старого HTML с бэкапом)
3. Загрузить логотип, баннер, пару новостей — проверить, что фото отображаются (нужен `storage:link`)

## Двофакторний захист і відновлення доступу

Усі користувачі адмінки підключають застосунок-автентифікатор (Google Authenticator, Aegis) при першому вході після деплою; у кожного є 10 одноразових кодів відновлення. Три рівні відновлення, щоб адмінка ніколи не «замкнулася»:

1. **Користувач втратив телефон** — вводить код відновлення, потім у «Двофакторний захист» (`/admin/two-factor-setup`) перепідключає застосунок.
2. **Втрачено і телефон, і коди** — адміністратор у «Налаштування → Користувачі» натискає «Скинути 2FA» (тільки після перевірки особи); користувач підключає застосунок заново при вході.
3. **Жоден адміністратор не може увійти** — по SSH на хостингу:

```bash
php artisan otfk:two-factor --status            # хто підключений, скільки кодів лишилось
php artisan otfk:two-factor admin@домен --reset # скинути фактор конкретного користувача
```

   Якщо й SSH-доступу до команди немає — тимчасово `TWO_FACTOR_ENFORCE=false` у `.env` (+ `php artisan config:cache`), увійти, скинути/перепідключити, повернути `true`. Усі події (`2fa.enabled/passed/failed/recovery_used/reset/lockout`) — у `storage/logs/security-*.log`.

## Cron на хостингу (бекапи + розклад Laravel)

У коді вже налаштовано:
- **щонеділі о 03:30** — `php artisan otfk:backup` (дамп БД у `storage/app/backups`);
- **щонеділі о 04:00** — очищення старої статистики відвідувань.
- **щохвилини** — `php artisan otfk:mirror-files --limit=30` (черга `file_mirrors`: сервер сам завантажує файли старого сайту otfk.od.ua у `storage/app/public/mirror/`; без записів у черзі лише перевіряє її).

Щоб це працювало, у панелі хостинга додайте **один** cron (щохвилини):

```bash
* * * * * cd /home/ЛОГІН/ВАШ-ДОМЕН/www && php artisan schedule:run >> /dev/null 2>&1
```

Шлях `cd` замініть на свій каталог сайту. Перевірка вручну:

```bash
php artisan schedule:list
php artisan otfk:backup
```

## Карта старих адрес і журнал 404

Старі адреси, що змінилися, переносяться таблицею `legacy_redirects`: редирект 301/308 на нову сторінку чи файл або 410 для свідомо видаленого. Масово — з CSV (колонки `source,target,code,note`; `target` — лише відносний шлях `/...`):

```bash
php artisan otfk:legacy-redirects storage/app/private/redirects.csv
php artisan otfk:legacy-redirects storage/app/private/redirects.csv --apply
```

Перший запуск лише показує нові/змінені записи й конфлікти (жива адреса, відсутнє чи зовнішнє призначення, дублі, ланцюжки); `--apply` записує без конфліктних рядків і зберігає копію CSV та звіт у `storage/app/private/legacy-redirects/`. Повторний запуск ідемпотентний.

CSV збирає `php artisan otfk:legacy-map --out=storage/app/private/legacy-map/map.csv --sitemap=https://otfk.od.ua/sitemap.xml --urls=gsc-pages.csv --scan-dir=<збережені сторінки старого сайту> --verify` (лише читання; запускати на хостингу, де лежать файли `storage`). Редактор розбирає `map.csv.unmapped.csv` (матеріал / 410 за затвердженим списком / 404) і `map.csv.conflicts.csv`, доповнює CSV, далі — dry-run і `--apply` командою вище. Точкові правки — «SEO → Редиректи старих адрес» в адмінці; адреси, за якими відвідувачі отримують 404, — «SEO → Журнал 404» (там же «Створити редирект»). Записи журналу без звернень понад 90 днів видаляє `model:prune` (через cron `schedule:run`). Файли, що фізично лежать у `public/`, Apache віддає без Laravel — для них редирект не спрацює.

## Файли старого сайту і переїзд на постійний хостинг

**Дзеркалювання.** Файли старого сайту не заливаються вручну: URL ставиться в таблицю `file_mirrors` (`App\Models\FileMirror::enqueue($url)` або INSERT з `source_hash = SHA2(source_url, 256)` і `status = 'pending'`), cron `schedule:run` щохвилини запускає `otfk:mirror-files`, файл з'являється за `FileMirror::publicUrl()` (`/storage/mirror/otfk.od.ua/...`). У контент вставляються лише **відносні** шляхи `/storage/...` — тоді зміна домену посилань не ламає. Дозволені хости — `FILE_MIRROR_HOSTS` (типово `otfk.od.ua,www.otfk.od.ua`), ліміт — `FILE_MIRROR_MAX_MB` (100). Стан: `SELECT status, COUNT(*) FROM file_mirrors GROUP BY status`; помилки — колонка `error`; повтор невдалих — `php artisan otfk:mirror-files --retry-failed`. Потрібні cron і вихідний HTTPS з хостингу. Без cron чергу можна обробити вручну: GitHub → Actions → «Mirror legacy files» → Run workflow (або `gh workflow run mirror-files.yml -f limit=500`) — ті самі SSH-секрети, що й у деплою.

**Переїзд з тимчасового хостингу на постійний** (нічого не видаляє на старому сервері):

1. На старому сервері: `php artisan otfk:backup` (дамп БД у `storage/app/backups/otfk_*.sql.gz`) і `php artisan otfk:storage-export` (архів `storage/app/backups/storage_*.zip`: увесь диск `public` — завантаження адмінки, імпортовані фото, `mirror/` — з маніфестом sha256).
2. Перенести обидва файли на новий сервер (scp/rsync або файловий менеджер панелі; у Git їх не класти — `storage/` ігнорується).
3. На новому сервері: розгорнути код за «Вариант А», імпортувати дамп у нову БД, `php artisan migrate --force`, `php artisan storage:link`.
4. Відновити файли: `php artisan otfk:storage-import /шлях/storage_….zip` — пише відсутні файли й перевіряє sha256; наявні файли з іншим вмістом лише показує як конфлікти (замінити — `--overwrite`).
5. Добрати дзеркальні файли, яких немає або які пошкоджені: `php artisan otfk:mirror-files --verify --limit=1000` (із джерела otfk.od.ua). Якщо оригінал уже недоступний, а старий хостинг ще працює: `php artisan otfk:mirror-files --verify --from=https://СТАРИЙ-ДОМЕН --limit=1000` — файл береться з `/storage/...` старого хостингу і приймається лише за збігу записаного sha256. Повторювати, доки в черзі нічого не лишиться.
6. Додати cron `schedule:run` на новому хостингу, оновити `APP_URL`, `php artisan optimize`. Перевірити `/`, `/en`, `/admin` і кілька сторінок з файлами.

**Безпека при переїзді (інакше адмінка не впустить нікого):**

- **`APP_KEY` — той самий, що на старому сервері.** Ним зашифровані секрети й коди відновлення 2FA в БД (а також cookie/сесії). Новий `key:generate` зламає вхід усім користувачам; якщо це вже сталося — `TWO_FACTOR_ENFORCE=false`, увійти, `php artisan otfk:two-factor <пошта> --reset` кожному, повернути `true`.
- `.env`: `TWO_FACTOR_ENFORCE=true`, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `APP_URL` з новим доменом (шаблон — `.env.production.example`).
- Точний час сервера (NTP): TOTP-коди живуть 30 с, розбіжність понад хвилину = «невірний код» у всіх. Перевірка: `date -u`.
- Document root = `public/`; повторити пробу `storage/app/public/_probe.php` → 403 (розділ 6 `docs/security-audit.md`). Якщо новий хостинг на nginx без Apache, `.htaccess` не діє — заборону скриптів у `/storage/` прописати в конфігу nginx (`location ~* ^/storage/.*\.php$ { return 403; }`); білий список завантажень у застосунку працює незалежно.
- `storage/logs/security-*.log` і `storage/app/private/sanitize-backup-*.json` у `storage-export` не входять — за потреби скопіювати вручну.
- Секрети GitHub Actions (`REMOTE_HOST`, `REMOTE_USER`, `REMOTE_KEY`, `REMOTE_PATH`, `REMOTE_PORT`) перевести на новий сервер (для `test`; Plesk-гілка `master` розгортається через Laravel Toolkit і SSH-секретів не використовує) — інакше автодеплой і далі йтиме на старий.

## Моніторинг доступності (без коду)

Безкоштовно: [UptimeRobot](https://uptimerobot.com) або Better Stack — пінг `https://ваш-домен/` кожні 5 хв. Сповіщення на email/Telegram, якщо сайт лежить.

## Фоновые задачи (Telegram)

Автопостинг новостей в Telegram отправляется
**после** отдачи страницы — через `dispatch(...)->afterResponse()`. Это
терминирующий колбэк: Laravel выполняет его в том же процессе на этапе
`terminate()`, уже после ответа браузеру. Поэтому:

- **queue-воркер не нужен** (на шаред-хостинге его и нет) — `QUEUE_CONNECTION`
  для этих задач не задействуется, таблица `jobs` не накапливается;
- посетитель/админ не ждёт ответа Telegram;
- **не переводите эти задачи в `ShouldQueue`** без запущенного `queue:work` —
  тогда они улетят в очередь и без воркера не выполнятся никогда.

Если Telegram-пост не ушёл (API недоступен) — новость всё равно помечается
как опубликованная (защита от дублей), а ошибка пишется в лог (`Log::warning`).
Повторить вручную: очистить `telegram_posted_at` у новости и пересохранить её.

## Обновление сайта потом (Вариант А)

Обычно ничего делать не надо — push в `master` (Plesk) или `test` (тестовый хостинг) запускает автодеплой (`deploy.yml`). Ручной путь на случай, если CI недоступен (фронтенд тогда собрать локально и залить `public/build` самому):

Если в БД остался результат старой миграции `2026_08_28_180000_drop_applicant_feedback_testimonials`, обычный `migrate --force` восстановит отсутствующие таблицы через `2026_10_04_175000_restore_missing_content_tables` перед переводом блоков главной (`180000`). Восстановление не запускает сидер и не меняет существующие записи. Последняя миграция перевода допускает повтор после частичного выполнения MySQL DDL: добавляются только отсутствующие поля. Не удаляйте записи из `migrations` и не откатывайте старые контент-миграции для восстановления схемы. Встроенные формы контактов/заявки и отзывы сняты с интерфейса, таблицы остаются архивными; августовская drop-миграция в объединённом коде ничего не удаляет. Перед восстановлением сохраните исходные записи и проверьте фактические таблицы/поля; после него проверьте успешность шага пересборки кешей и ответы `/`, `/en`, `/admin`.

```bash
cd ~/ВАШ-ДОМЕН/www
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
```

Якщо в PR були нові міграції або зображення на сервері ще без WebP:

```bash
php artisan migrate --force
php artisan images:webp
```
