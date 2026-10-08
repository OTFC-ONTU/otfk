# Політика конфіденційності — чернетка

> **Чернетка, потребує затвердження відповідальною особою коледжу.** Не публікувати до юридичної перевірки. Після затвердження текст вноситься в адмінці як CMS-сторінка зі slug `polityka-konfidentsiynosti` (адреса `/polityka-konfidentsiynosti`, англійська версія — у розділі «Англійська версія» тієї ж сторінки), а посилання на неї додається в підвал і банер cookies.
>
> **Що має перевірити й заповнити коледж перед публікацією:**
> 1. Повна офіційна назва володільця даних (коледж як відокремлений структурний підрозділ ОНТУ чи сам ОНТУ), код ЄДРПОУ, поштова адреса, e-mail і телефон для звернень — у тексті позначені `[…]`. Демонстраційні контакти з тестового сайту не використовувати.
> 2. Відповідальна особа (структурний підрозділ) за організацію обробки персональних даних — ПІБ/посада або назва підрозділу; чи є окремий наказ/положення коледжу або ОНТУ про захист персональних даних, на яке слід послатися.
> 3. Строк зберігання журналів вебсервера в хостинг-провайдера (уточнити в ukraine.com.ua) і країна розміщення серверів.
> 4. Назва ключа localStorage для згоди — уточнити в розробника після реалізації банера (у тексті `otfk-analytics-consent`).
> 5. Юридична перевірка формулювань щодо правових підстав, транскордонної передачі (Google) і прав суб'єкта; дата набрання чинності.
>
> Технічні факти в тексті звірені з кодом сайту станом на 08.10.2026: cookie сесії `odeskii-texnicnii-faxovii-koledz-ontu-session` (ім'я походить від `APP_NAME`; зміниться, якщо на сервері задано `SESSION_COOKIE` або інший `APP_NAME`), строк сесії 120 хвилин, сесії зберігаються в БД разом з IP та User-Agent (стандартна таблиця `sessions` Laravel) і видаляються після закінчення строку; лічильник відвідувань — дата + шлях + кількість, рядки старші 180 днів видаляються; журнал 404 — без IP, 90 днів; журнал безпеки адмінки — IP і User-Agent працівників, 90 днів; шрифти — fonts.bunny.net; відео YouTube — youtube-nocookie.com. При зміні коду — оновити текст.

---

## Політика конфіденційності та використання cookies

*Редакція від [дата затвердження]*

### 1. Хто ми

Ця політика стосується вебсайту https://otfk.od.ua (далі — сайт). Володільцем персональних даних, що обробляються через сайт, є [повна офіційна назва: Одеський технічний фаховий коледж Одеського національного технологічного університету], код ЄДРПОУ [код], адреса: [поштова адреса] (далі — коледж).

Звернення щодо персональних даних: [e-mail для звернень], [телефон], або поштою на адресу коледжу. Відповідальний за організацію обробки персональних даних: [посада / підрозділ].

Ми обробляємо дані відповідно до Закону України «Про захист персональних даних» і дотримуємося загальновизнаних принципів: законність, мінімізація даних, обмеження мети й строку зберігання, прозорість.

### 2. Які дані ми обробляємо

**Сайт не має форм** для звернень, заявок чи відгуків і не просить вводити ім'я, телефон або e-mail. Для зв'язку з коледжем ви самі обираєте телефон чи пошту, вказані на сайті.

| Дані | Навіщо | Скільки зберігаються |
|---|---|---|
| **Технічні дані запиту** (IP-адреса, час, адреса сторінки, тип браузера) у журналах вебсервера хостинг-провайдера | Робота й безпека сайту, захист від атак | [строк хостинг-провайдера] |
| **Сесія** (технічний ідентифікатор у cookie; на сервері — IP-адреса, тип браузера, час останньої активності) | Робота сайту, захист від підробки запитів | 120 хвилин бездіяльності, потім видаляється |
| **Знеособлений лічильник відвідувань**: дата, адреса сторінки, кількість переглядів | Статистика популярності сторінок | 180 днів |
| **Журнал неіснуючих сторінок (404)**: адреса, сторінка-джерело без параметрів, кількість звернень; **без IP-адреси** | Виправлення битих посилань після оновлення сайту | 90 днів після останнього звернення |
| **Google Analytics 4 — лише за вашою згодою** (див. розділ 3) | Знеособлена статистика відвідувань і кліків | 14 місяців |

Працівники коледжу, які входять в адміністративну панель, додатково фіксуються в журналі безпеки (e-mail, IP-адреса, тип браузера, час входу/виходу) — строк зберігання 90 днів. Це потрібно для захисту сайту й стосується лише персоналу.

### 3. Google Analytics

Якщо ви натиснете «Прийняти» у банері cookies, сайт завантажить Google Analytics 4 — сервіс компанії Google Ireland Limited / Google LLC, яка діє як обробник даних за дорученням коледжу. Без вашої згоди код Google Analytics не завантажується і дані Google не передаються. Аналітика також не працює для працівників, які увійшли в адмінпанель.

Google Analytics отримує: переглянуті сторінки, джерело переходу, приблизне місцеположення (країна, місто), тип пристрою й браузера, мову, а також події «клік на телефон», «клік на e-mail» і «завантаження файлу». Google обробляє IP-адресу для визначення приблизного місцеположення, але не зберігає її в Google Analytics. Ми вимкнули Google signals і персоналізацію реклами, не підключали рекламні сервіси і не передаємо в аналітику імена, телефони чи e-mail.

Дані Google Analytics можуть оброблятися на серверах Google за межами України, зокрема в ЄС і США, відповідно до [умов обробки даних Google](https://business.safety.google/adsprocessorterms/) та [політики конфіденційності Google](https://policies.google.com/privacy). Строк зберігання в Google Analytics — 14 місяців.

### 4. Cookies та локальне сховище браузера

| Назва | Тип | Призначення | Строк |
|---|---|---|---|
| `odeskii-texnicnii-faxovii-koledz-ontu-session` | Необхідна | Сесія відвідувача | 120 хвилин |
| `XSRF-TOKEN` | Необхідна | Захист від підробки запитів | 120 хвилин |
| `otfk-analytics-consent` (localStorage) | Необхідна | Запам'ятовує ваш вибір щодо cookies | До очищення даних браузера |
| `ann-closed` (localStorage) | Функціональна | Запам'ятовує закрите оголошення | До очищення даних браузера |
| `_ga` | Аналітична, **лише за згодою** | Google Analytics: розрізнення відвідувачів | До 2 років |
| `_ga_<ID>` | Аналітична, **лише за згодою** | Google Analytics: стан сесії | До 2 років |

Необхідні cookies потрібні для роботи сайту і встановлюються без згоди. Сайт також завантажує шрифти з fonts.bunny.net і показує відео YouTube через youtube-nocookie.com, а окремі сторінки можуть містити вбудовані карти чи документи Google. Під час відкриття таких сторінок ці сервіси отримують вашу IP-адресу як технічну умову доставки вмісту і діють за власними правилами.

### 5. Правові підстави

- Необхідні cookies, журнали вебсервера, лічильник відвідувань і журнал 404 — законний інтерес коледжу в забезпеченні роботи, безпеки й зручності офіційного сайту, а також виконання обов'язку оприлюднювати інформацію про діяльність закладу освіти.
- Google Analytics — ваша згода, яку можна будь-коли відкликати.

### 6. Як змінити або відкликати згоду

У підвалі кожної сторінки є посилання **«Налаштування cookies»**. Там можна прийняти або відхилити аналітику. Після відмови код Google Analytics більше не завантажується; вже встановлені cookies `_ga` можна видалити в налаштуваннях браузера. Відмова не впливає на роботу сайту.

### 7. Ваші права

Відповідно до статті 8 Закону України «Про захист персональних даних» ви маєте право знати про джерела, мету й місцезнаходження ваших даних, отримати до них доступ, вимагати виправлення або видалення, заперечити проти обробки, відкликати згоду та звернутися зі скаргою до Уповноваженого Верховної Ради України з прав людини або до суду.

Для реалізації прав напишіть на [e-mail для звернень]. Зважте, що більшість даних сайту знеособлені, тому ми можемо попросити вказати дату й час відвідування, щоб знайти відповідні записи. Ми відповідаємо у строки, встановлені законодавством.

### 8. Що ми не робимо

Ми не продаємо і не передаємо дані для реклами, не створюємо профілів відвідувачів і не приймаємо автоматизованих рішень щодо вас.

### 9. Діти

Сайт призначений для широкого кола відвідувачів, зокрема вступників до 18 років. Ми свідомо не збираємо дані дітей; аналітика вмикається лише після згоди, яку неповнолітнім рекомендуємо надавати разом із батьками.

### 10. Зміни політики

Ми можемо оновлювати цю політику, наприклад у разі зміни сервісів сайту. Чинна редакція завжди доступна на цій сторінці з датою оновлення.

---

## Privacy and Cookie Policy

*Version of [approval date]*

### 1. Who we are

This policy applies to the website https://otfk.od.ua (the "website"). The controller of personal data processed through the website is [full official name: Odesa Technical Professional College of Odesa National University of Technology], EDRPOU code [code], address: [postal address] (the "college").

Personal data enquiries: [contact e-mail], [phone], or by post to the college address. Person responsible for personal data processing: [position / department].

We process data in accordance with the Law of Ukraine "On Personal Data Protection" and follow generally recognised principles: lawfulness, data minimisation, purpose and storage limitation, and transparency.

### 2. What data we process

**The website has no forms** for enquiries, applications or reviews and does not ask for your name, phone number or e-mail. To contact the college you choose to use the phone or e-mail shown on the website.

| Data | Purpose | Retention |
|---|---|---|
| **Technical request data** (IP address, time, page address, browser type) in the hosting provider's web server logs | Operation and security of the website | [hosting provider period] |
| **Session** (technical identifier in a cookie; on the server: IP address, browser type, time of last activity) | Website operation, protection against forged requests | 120 minutes of inactivity, then deleted |
| **Anonymous visit counter**: date, page address, number of views | Statistics on page popularity | 180 days |
| **Missing pages log (404)**: address, referring page without parameters, number of requests; **no IP address** | Fixing broken links after the website update | 90 days after the last request |
| **Google Analytics 4 — only with your consent** (see section 3) | Aggregated statistics of visits and clicks | 14 months |

College staff who sign in to the administration panel are additionally recorded in a security log (e-mail, IP address, browser type, sign-in/out time), kept for 90 days. This protects the website and applies to staff only.

### 3. Google Analytics

If you click "Accept" in the cookie banner, the website loads Google Analytics 4, a service of Google Ireland Limited / Google LLC acting as a processor on behalf of the college. Without your consent the Google Analytics code is not loaded and no data is sent to Google. Analytics is also disabled for staff signed in to the administration panel.

Google Analytics receives: pages viewed, traffic source, approximate location (country, city), device and browser type, language, and the events "phone click", "e-mail click" and "file download". Google uses the IP address to determine approximate location but does not store it in Google Analytics. We have disabled Google signals and ads personalisation, connected no advertising services, and send no names, phone numbers or e-mail addresses to analytics.

Google Analytics data may be processed on Google servers outside Ukraine, including in the EU and the USA, under [Google's data processing terms](https://business.safety.google/adsprocessorterms/) and the [Google Privacy Policy](https://policies.google.com/privacy). Retention in Google Analytics is 14 months.

### 4. Cookies and browser local storage

| Name | Type | Purpose | Duration |
|---|---|---|---|
| `odeskii-texnicnii-faxovii-koledz-ontu-session` | Necessary | Visitor session | 120 minutes |
| `XSRF-TOKEN` | Necessary | Protection against forged requests | 120 minutes |
| `otfk-analytics-consent` (localStorage) | Necessary | Remembers your cookie choice | Until browser data is cleared |
| `ann-closed` (localStorage) | Functional | Remembers a dismissed announcement | Until browser data is cleared |
| `_ga` | Analytics, **consent only** | Google Analytics: distinguishing visitors | Up to 2 years |
| `_ga_<ID>` | Analytics, **consent only** | Google Analytics: session state | Up to 2 years |

Necessary cookies are required for the website to work and are set without consent. The website also loads fonts from fonts.bunny.net and shows YouTube videos via youtube-nocookie.com, and some pages may contain embedded Google maps or documents. When such pages are opened, these services receive your IP address as a technical condition of delivering content and act under their own policies.

### 5. Legal bases

- Necessary cookies, web server logs, the visit counter and the 404 log: the college's legitimate interest in operating a secure and usable official website and its duty to publish information about the activities of an educational institution.
- Google Analytics: your consent, which you may withdraw at any time.

### 6. Changing or withdrawing consent

Every page has a **"Cookie settings"** link in the footer where you can accept or reject analytics. After rejection the Google Analytics code is no longer loaded; `_ga` cookies already set can be deleted in your browser settings. Rejecting analytics does not affect the website.

### 7. Your rights

Under Article 8 of the Law of Ukraine "On Personal Data Protection", you have the right to know the sources, purpose and location of your data, to access it, to request correction or deletion, to object to processing, to withdraw consent, and to complain to the Ukrainian Parliament Commissioner for Human Rights or to a court.

To exercise your rights, write to [contact e-mail]. As most website data is anonymous, we may ask for the date and time of your visit to locate the relevant records. We respond within the time limits set by law.

### 8. What we do not do

We do not sell data or share it for advertising, do not build visitor profiles and do not make automated decisions about you.

### 9. Children

The website is intended for a wide audience, including applicants under 18. We do not knowingly collect children's data; analytics is enabled only after consent, which we recommend minors give together with their parents.

### 10. Changes

We may update this policy, for example when website services change. The current version, with its update date, is always available on this page.
