# Деплой ОТФК на Хостинг Україна (ukraine.com.ua)

Тариф «Кращий»: есть **SSH**, **Composer 2** (уже установлен), выбор **версии PHP** — значит деплой идёт по стандартному пути.

> ✅ Перед прод-деплоем уже сделано в коде: `User::canAccessPanel()` (иначе Filament отдаёт 403 на проде) и шаблон `.env.production.example`.

> 🚀 **Автодеплой:** после первичной настройки по этому документу обновления едут сами — workflow `.github/workflows/deploy.yml` на каждый push в `master` (или вручную через Run workflow): после успешных тестов и аудита того же workflow собирает фронтенд в CI, по SSH делает `git reset --hard ${{ github.sha }}` + `composer install`, заливает `public/build/` rsync-ом и выполняет `migrate --force` + пересборку кэшей. Нужны секреты репозитория `REMOTE_KEY` (приватный SSH-ключ), `REMOTE_HOST`, `REMOTE_USER`, `REMOTE_PATH` (каталог сайта), опционально `REMOTE_PORT`. Раздел «Обновление сайта потом» ниже — ручной запасной путь.
> Переревью безопасности 06.10.2026: в `deploy.yml` реализован собственный job `tests`, `deploy` зависит от него и устанавливает тот же SHA. Ручной запуск вне master требует отдельной проверки получения выбранного SHA. Атомарность проверки и записи `otfk:sanitize-content --apply` реализована условным UPDATE (разделы 16–17 аудита). Приёмка на хостинге остаётся обязательной; локальный тест команды требует изоляции storage, чтобы не удалять рабочие бэкапы.
>
> ⚠️ `public/build/` больше **не** коммитится в git — сборка живёт только в CI/деплое; локально `npm run build` или `npm run dev`.

---

## 0. Подготовка локально (один раз)

```bash
# 1) собрать фронтенд (создаёт public/build — на хостинге нет npm, поэтому собираем тут)
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
# выбрать в панели версию PHP 8.2/8.3 для сайта (Сайти → Налаштування → Версія PHP)
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
# отредактировать .env: APP_URL, APP_KEY (скопировать из локального), DB_*
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

## Чек-лист «не забыть»

- [ ] PHP **8.2+** выбран для сайта
- [ ] PHP-расширение **GD с поддержкой WebP** (для авто-оптимизации картинок). Если нет — сайт работает, но изображения не сжимаются в WebP (молча пропускается). Проверка: `php -r "var_dump(function_exists('imagewebp'));"`
- [ ] Корень сайта = **`public`**
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` заполнен, `APP_URL=https://домен`, `DB_*`, `SESSION_SECURE_COOKIE=true`, `ADMIN_EMAIL`/`ADMIN_PASSWORD`
- [ ] `php artisan migrate --seed --force` (или импорт `otfk.sql`)
- [ ] `php artisan storage:link` (фото из админки)
- [ ] `php artisan filament:assets` (стили админки)
- [ ] `public/build` залитий на сервер (автодеплоєм або вручну після `npm run build`)
- [ ] права на запись: `chmod -R 775 storage bootstrap/cache` (если будут ошибки 500)

## Проверить после деплоя

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
- Секрети GitHub Actions (`REMOTE_HOST`, `REMOTE_USER`, `REMOTE_KEY`, `REMOTE_PATH`, `REMOTE_PORT`) перевести на новий сервер, інакше автодеплой і далі йтиме на старий.

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

Обычно ничего делать не надо — push в `master` запускает автодеплой (`deploy.yml`). Ручной путь на случай, если CI недоступен (фронтенд тогда собрать локально и залить `public/build` самому):

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
