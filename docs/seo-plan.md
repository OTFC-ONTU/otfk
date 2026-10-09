# SEO optimization plan for the college website

Date: October 7, 2026. Goal — preserve search visibility while the old website is replaced, and increase the number of visits from prospective students to specialties, admission conditions and the admissions office contacts. The main priority is the Ukrainian version; the English version opens after editorial acceptance.

The main address is preserved: the old and the new site run on `https://otfk.od.ua/`. This is an application replacement on the same domain. Verifying that old and new paths match is mandatory: keeping the domain does not guarantee keeping the addresses of pages, documents and images.

This plan is based on the current Laravel code and the project documentation. Search Console data, actual indexing, the content of the target database and the speed of the live site require separate measurement. The timelines below are labour estimates, not a promise of ranking growth. Implementation progress is in the section «Implementation status».

## What already works

- The shared layout outputs title, description, canonical, Open Graph, Twitter card and EducationalOrganization. CMS pages support separate SEO fields and their English variants.
- There are robots.txt, sitemap.xml, RSS, breadcrumbs with JSON-LD, NewsArticle, Event, Course and FAQPage.
- Public materials are rendered on the server; language URLs are separated via `/en`.
- English responses receive `X-Robots-Tag: noindex, follow`; administrative ones — `noindex, nofollow`.

## Verified gaps

| Problem | Where | Meaning for the plan |
|---|---|---|
| Canonical is built via `url()->current()` without the query | `resources/views/components/layouts/app.blade.php` | The second page of news points its canonical to the first; a query-parameter policy is needed |
| No hreflang in the shared layout | same layout | Once the English version is opened, the language variants must be linked |
| The sitemap has no detail pages for Department and no English URLs | `app/Http/Controllers/SitemapController.php` | Add published departments; add EN only after it is admitted to indexing |
| The sitemap contains RSS, and its lastmod equals today's date | same controller | Keep RSS as a separate discovery channel; clean the XML sitemap down to canonical pages |
| The sitemap loads six model sets in full without a cache | same controller | Select only the needed fields, limit memory use, define cache reset on publication |
| Views and likes change updated_at, which the sitemap uses for lastmod | `NewsController:53,135`, Eloquent `Builder::increment()` | Confirmed bug: fix the counter updates while keeping the content change date |
| The English version can serve a whole Ukrainian item | `HasEnglishTranslation` | Do not open fallback pages as if they were ready English translations |

## Implementation status

Branch `feature/seo-stage-1` (PR #25 merged and deployed), fixes after review — `fix/seo-followups`, 8 October 2026; the contract — `tests/Feature/SeoIndexingTest.php`.

| Item | Status |
|---|---|
| Closing the test domain (stage 1, item 4) | Done: `SEO_INDEXING=auto` + `SEO_PRIMARY_HOST=otfk.od.ua`; outside the primary domain `X-Robots-Tag: noindex, nofollow` and meta robots; robots.txt does not forbid crawling |
| URL policy and canonical (items 5–6) | Done: canonical keeps only `page` (>1), `category`, `year`; UTM/fbclid and other parameters are dropped; search and the archive `?year=` — `noindex, follow`; an unknown category and pages beyond the pagination of news/galleries/videos — 404. Decision on `category`: indexed; on `year`: not indexed — reconsider if needed |
| Sitemap (item 7) | Done: without RSS and `/en`; published departments added; CMS slugs under special routes are skipped; the query selects only the needed fields; 1-hour cache with reset on saving/deleting materials |
| lastmod and counters | Done: views and likes do not change `updated_at`, do not fire events and do not trigger Telegram |
| Future news | Done: a direct URL before the publication date returns 404 for guests, as in the lists |
| Useful 404 | Done: permanent links to admissions, specialties and search in both locales; 410 — together with redirects |
| Indexing barrier: Feature tests | Done for the home page and landing pages on `https://otfk.od.ua` |
| `legacy_redirects`, 410, 404 log, Filament resources | Done (test `LegacyRedirectsTest`): tables `legacy_redirects`/`not_found_logs`, lookup only on 404, 410 with useful links, CSV import `otfk:legacy-redirects` (dry-run → `--apply`, archive of the CSV and report), «SEO» sections in the admin for admin only, «Створити редирект» (Create redirect) from the log, cleanup after 90 days. The map itself is not yet assembled; the significant parameters of the old CMS are a preliminary list in `config/otfk.php`, to be clarified after the crawl |
| URL map collection tool | Done (test `LegacyMapTest`): `otfk:legacy-map` builds the CSV for import from imported-from markers, file_mirrors, photos and PDFs; uncovered addresses and conflicts go into separate CSVs. The map itself has not been assembled from the old site's data or Search Console |
| HTTP smoke check in `deploy.yml`, www/HTTPS normalization | Done (test `SeoSmokeTest`): `otfk:seo-smoke` (indexable/closed, robots, sitemap, canonical/og:url, 404, `--check-redirects`, `--resolve`), a step in `deploy.yml` driven by the variables `SEO_SMOKE_BASE_URL`/`SEO_SMOKE_EXPECT` (set 2026-10-08: `https://just-test.shop`, `closed`; the check after the deployment of PR #25 passed); www/HTTPS rules only for otfk.od.ua; `/index.php` and `/index.php/<path>` → `/` and `/<path>` in a single 301 for any host (via `THE_REQUEST`). On just-test.shop the hosting control panel enforces HTTPS before `.htaccess`, so old `http://` links there take 2 hops — on the primary vhost check the panel setting when switching (DEPLOY.md). Verify on the target hosting that there is no loop and check www in DNS/certificate |
| Stage 2 (technical part) | Done (test `SeoMetadataTest`): unique title/description via `MetaText`, separator « — » and brand_short without repetition, «… в Одесі» (“… in Odesa”) for specialties, one H1 (home banners → H2), «Наступний крок» (Next step) block. Not done: semantics from Search Console, editorial verification of facts, programme ↔ department ↔ news links, texts of `/abituriyentu`, differentiation of identical page titles |
| Stage 3 (technical part) | Done (test `StructuredDataTest`): JSON-LD via `StructuredData` (PostalAddress, alternateName/sameAs from «SEO → Розмітка організації» (SEO → Organization markup), NewsArticle with absolute URLs, publisher and Kyiv dates, Event/Course/Breadcrumb), priority and sizes of top images, lazy media in the text. Not done: before/after measurements, responsive images/WebP, real alternateName/sameAs values, validator checks |
| Stage 4 (minimal) | Done (test `EnglishIndexingTest`, user decision 2026-10-08): `/en` is opened automatically without flags or UI — sections always, items with a complete, non-stale translation; hreflang uk/en/x-default, pairs in the sitemap, smoke check of `/en` and hreflang reciprocity. Not done: proofreading of the machine translation |
| Analytics | Done in code (test `AnalyticsConsentTest`): GA4 with a consent banner, inert without an ID, gtag only on the primary domain after «Прийняти» (Accept); guide `docs/analytics-search-console-guide.md`, policy draft `docs/privacy-policy-draft.md`. On the test DB on 2026-10-08 an **unpublished** page `polityka-konfidentsiynosti` (privacy policy) was created (id 286, 8 placeholder college data entries, needs legal proofreading) and a test ID `G-TEST000000` used only to show the banner (remove before the real ID is set: `php storage/app/private/privacy-2026-10-08/set_test_ga_id.php --remove`). Not done: the GA4 property and real ID, approval and publication of the policy, DebugView check on production |
| Old site crawl | Done 2026-10-08 (git-ignored `storage/app/private/legacy-crawl-2026-10-08/`): a homemade PHP site with trailing-slash paths and `/x/index.php` duplicates, no query parameters; its 2020 sitemap is useless. Preliminary map on the test DB: 1796 matched, 2689 without a pair (of these ~2020 photos — they will be matched on hosting with `--verify`). Outcome: `query_keys` cleared, `/x/index.php` = `/x`, old-site directories excluded from the slash removal in `.htaccess` (one hop), 397 section files (~653 MB) put into `file_mirrors` of the test DB. The map was imported on the test hosting on 2026-10-08: 4238 `legacy_redirects` records (520 news, 269 pages, 1410 mirror files, 2020 news photos, 19 manual: `/news` → `/novyny`, `/search.php` → `/poshuk`, departments/cycle commissions → `/struktura/...`, `public_information` → `/dokumenty/...`, `/conference` → `/studentski-konferenciyi-otfk-ontu`); all 3430 file destinations were verified by HTTP request. 44 addresses remain 404: 5 unpublished news items (#14, #53, #156, #180, #515) and 39 static files of old mini-sites — the list is in the git-ignored `storage/app/private/legacy-crawl-2026-10-08/unresolved.csv`. Remaining: transfer of the map to production when the domain is switched |

## Stage 1 Preparation for indexing and migration

Priority P0, before launch. Estimate: 1–2 calendar weeks to collect the URL map, implement redirects, the 404 log, indexing barriers and verification; if the sources are incomplete, more time is needed. In the test DB of the new application as of 2026-10-04 — 513 news items and 213 pages (726 items). This is the volume of the new site, while the redirect map is built from the old one: the volume of the old site (including items not included in the import, duplicates and archive sections), documents, images and the parameters of the old CMS must be determined by an export. Development working days for the mechanisms should be estimated separately from the calendar time needed to collect the URL map. The developer is responsible for the mechanisms and automated checks, the editor for the conformity of the materials, the site owner for access to the search consoles and the launch date.

1. Keep the main address `https://otfk.od.ua/` and set the corresponding APP_URL on the target environment. Check absolute links, HTTP → HTTPS, www → without www, and trailing slashes. Canonical, sitemap and JSON-LD must point to the primary domain, not to the test hosting.
2. Keep the existing Google Search Console property for otfk.od.ua and the method of ownership verification when the application is replaced; if the property is not connected yet — connect it. Take baseline figures of the old site: clicks, impressions, queries, popular pages and external links. Export URLs also from the old sitemap, a site crawl and any available logs. The Search Console Change of Address tool is not used for replacing a site on the same domain. [Purpose of the Google tool](https://support.google.com/webmasters/answer/9370220?hl=en).
   Connect Bing Webmaster Tools, either by importing the verified property and sitemap from Search Console or by confirming ownership separately. Connecting with access already available takes 15–30 minutes; the appearance of reports depends on the service's data processing. [Bing instructions](https://blogs.bing.com/webmaster/2019/9/Import-sites-from-Search-Console-to-Bing-Webmaster-Tools/).
3. Compile a table `old path → path in the new application → action`, including news, documents and images, as well as the significant query parameters of old URLs. Preserved addresses return 200 without an additional redirect. Only for changed paths use permanent server-side 301/308 redirects to the corresponding item within otfk.od.ua; do not redirect all old pages to the home page. If an item is deliberately deleted permanently and there is no suitable replacement — 410 Gone for an explicitly approved list of deleted URLs. For unknown, erroneous and random addresses — 404 Not Found. An archived or repealed document that must be kept for history is not deleted automatically: it stays available with a visible status and a link to the current edition. A hosting migration with unchanged URLs does not by itself require redirects. Redirects for changed addresses are kept for at least one year. [Google's recommendations for site moves](https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes).
4. Close the test domain to indexing at the environment level. Check that these restrictions do not reach the primary Ukrainian site; robots.txt does not replace noindex. For pages with noindex keep the ability to be crawled, so the robot can see the directive.
5. Define the URL policy: important content pages are indexed; search `/poshuk`, previews and service responses are not. For `category` and `year` decide whether they have independent search value; exclude unneeded combinations from indexing and from the sitemap. UTM and other tracking parameters are excluded from the canonical.
6. Fix the canonicals of pagination: `/novyny?page=2` must reference itself with `page=2`; do not merge different sets of items with the first page. Check the pagination of the other catalogues, empty results and non-existent pages.
7. Rebuild the sitemap: only unique canonical URLs with status 200 and permitted indexing; add published departments; check CMS slugs that overlap the special routes. Remove RSS from the page list; a reliable lastmod reflects a substantive edit of the material. Do not spend time on priority/changefreq: Google ignores them. [Sitemap rules](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

### File and image migration

This is a separate P0 block: old file addresses have external links and may appear in search on their own. Prepare the map before the site is switched, and verify that the new files are already transferred, available without login and identical to the originals.

- News photos: for confirmed imported images, the scheme `301 /uploads/<name> → /storage/news/imported/<name>`. The import uses the basename: for nested source directories, check for collisions of identical names and that the image matches, then add exact map entries. Do not redirect all extensions from uploads to the photo directory.
- Documents and other mirrors: the source is `file_mirrors` records with status done, `source_url` and `path`; the destination is `FileMirror::publicUrl()`. The typical scheme for the otfk.od.ua source: `301 /<source path> → /storage/mirror/otfk.od.ua/<source path>`. For example, a mirrored PDF from `/uploads/...` must lead to the mirror, not to news/imported. A recorded path value takes priority over the template.
- Use templates to generate a verified map or only for confirmed groups of files. Do not create an unconditional wildcard `/uploads/* → /storage/...`: the destinations differ, and a missing file would redirect to a 404. Exact exceptions take priority.
- Automatically check every map destination: status 200, MIME type, availability; for mirrors compare sha256 with the registry. Manually check all current admission rules, orders and schedules, files with external links or search impressions, and at least 20 photos from different years. Check Cyrillic, spaces, letter case, URL encoding and the PDF fragment `#page=` in a browser. Keep a report of conflicts and missing files.

Block readiness: every known old file URL leads in one hop to the same content; there are no collisions, no PDFs replaced by photos, no chains and no unavailable destinations.

### Storage of redirects and 404 handling

Proposed implementation: a DB table `legacy_redirects`, and a lookup of a record only at the moment when the application is already producing a 404 response (NotFound exception handling). The catch-all `/{page:slug}` for an unknown slug itself returns 404 via model binding, so old addresses reach this point. This way a redirect cannot override a live page and adds no work to ordinary requests. On import, additionally check that the source path does not match a live URL that answers 200. The single source of the map is a CSV assembled from the content export, imported-from markers, file_mirrors, Search Console and the old-site crawl. The import is idempotent: first a dry run with conflicts and destination checks, then apply; the original map is kept for audit.

The table stores the source path, the normalized significant query parameters, the action (`redirect` or `gone`) and an active flag. For `redirect` the target relative URL and the code 301/308 are mandatory; for `gone` — a 410 response without `Location` and without a destination. A deliberate deletion is confirmed by an editor; an accidental 404 is not automatically turned into a 410. Match first the exact path+query, then a permitted record by path only. Old CMS parameters that identify a material must not be lost; the handling of other parameters is set explicitly, and tracking parameters are discarded. Account for possible old language sections such as `/ru/` and others — establish whether they exist by crawling, and record the correspondence of materials in the map.

Cache the map with a reset on change; block external destinations, loops, duplicates and chains. Make a Filament resource for admin only, with search, filters and destination verification; add role tests. Keep the general normalization and the storage protection in `.htaccess`. Note that Apache bypasses Laravel for existing static files: if an old file remains on disk, it either keeps a working address, or a separate verified server rule is needed for the required redirect. All file destinations remain static and are not served through PHP.

For skipped addresses add an application 404 log in the DB and a Filament page for admin only: the path, cleaned significant parameters, a safe referrer, first/last access, the number of hits and the triage status. Do not store cookies, tokens or full arbitrary query strings; aggregate records, limit the recording of bot noise, and delete old records on a schedule, e.g. after 90 days. Show an action to create an exact redirect with a manual choice of the matching material. An error when writing the log must not break the visitor's response.

Check on hosting that missing old `/uploads/...` files pass through the front controller and reach the log. For errors that Apache itself returns before Laravel, provide an available export of the hosting access/error logs or a report from an external crawl: the application log does not cover them automatically. Assign an administrator who reviews the log daily during the first week, then weekly; process first the requests with external referrals and repeated old addresses.

### Useful 404 page

On the 404 page keep the search and add prominent permanent links to admissions (`/abituriyentu`), specialties (`/spetsialnosti`) and a separate link to search (`/poshuk`). These actions must work independently of the CMS menu composition, on phones and without JavaScript; use LocalizedUrl and dictionaries for both locales. The template already contains search and other links — it needs to be supplemented with explicit directions for prospective students. Show a clear explanation that the address may have changed, and keep a genuine HTTP 404: no automatic redirect to the home page and no 200 response. For 410 also provide useful links, while keeping the explanation of final deletion and the 410 status.

Readiness: a visitor arriving from an old link can open admissions, specialties or search in one action; Feature tests check the links and the 404/410 codes; a browser check confirms usability and operation without JS.

### Fixing lastmod

Fix both counter increments — views and likes — so that updated_at does not change; keep atomicity and the existing restrictions on repeated actions. In the installed Laravel, `Builder::increment()` calls `addUpdatedAtColumn()`, so the current lastmod is updated by visitor actions. `incrementQuietly()` is not suitable: it only disables events but still updates the timestamp. Suitable is a query that bypasses the Eloquent builder, e.g. `News::whereKey($news->id)->toBase()->increment('views')`, or an increment with timestamps temporarily disabled. Do not claim that Google has already lost trust in the site: this has not been measured.

Add a regression test: after a view and a like the counter values change, while updated_at and lastmod remain the same; after a substantive editorial change the date changes. Account for service updates and translation changes; if updated_at cannot be reliably separated from them, provide a separate date of substantive edit. Fixing the counters must not send the news item to Telegram.

### Automatic indexing barrier

Write Feature tests for the target APP_URL `https://otfk.od.ua`: the home page and published Ukrainian landing pages contain no noindex either in HTTP headers or in HTML; robots does not block the root for Googlebot/Bingbot. The intentional noindex of the admin, of search and of the not-yet-ready `/en` remains allowed.

Before traffic is switched, run an HTTP smoke check of the new application with the correct Host/SNI on the target server, not of the still-running old site. If there is a noindex, `Disallow: /`, a wrong domain in the canonical/sitemap, or unavailability of key URLs, the switch is blocked. After a regular deployment and cache clearing, run the same check of real public responses in deploy.yml, with retries for brief network failures. A wrong response fails the workflow and requires restoring a working version; a check after the release does not by itself prevent a release that has already been executed. Prepare and verify the recovery procedure in advance. The target-domain condition is taken from an explicit environment parameter; for the test domain, closed indexing is expected.

Readiness: all URLs of the migration table are checked automatically; key pages and files open without login; the sitemap contains no redirects, 404s, drafts, noindex or the test domain; the 404 log is available to the administrator; robots and HTTP headers are verified on the target hosting; the indexing barrier passes.

## Stage 2 Pages for prospective students and metadata

Priority P1, before the launch of the main landing pages. Estimate: 3–5 working days together with the editor; agreeing the facts is a separate task.

Collect semantics from Search Console and query research. The initial groups below are hypotheses; their frequency is not verified: «технічний коледж Одеса» (technical college Odesa), «коледж після 9 класу Одеса» (college after grade 9 Odesa), «вступ після 11 класу» (admission after grade 11), the name of each specialty + «Одеса» (Odesa), admission conditions, budget/contract, dormitory. Do not create dozens of nearly identical pages for different phrasings.

Split the queries by language: analyze Ukrainian and Russian queries separately, without assuming any percentage share. Take into account former official names, old abbreviations and spelling variants of the brand; confirm them with historical documents. Do not create a Russian-language version within this plan. On the Ukrainian history page, make clear the link between the former names and the current college.

| Page | What it must answer for the visitor's query |
|---|---|
| Home | Official name, location, college profile, links to admission and specialties |
| `/abituriyentu` and related CMS pages | Current rules, dates, documents, admission after grade 9/11, admissions committee contacts |
| `/spetsialnosti/{slug}` | Current name and code, qualification, duration and study form, admission requirements, programme, career prospects |
| Cost and dormitory, if the information is confirmed | Conditions, current date, official source and a contact for clarification |
| `/kontakty` | Verified address, phone, working hours and location of the college |
| News and documents | Clear title, date, available attachments; archive information is clearly separated from current rules |

For priority pages prepare unique title and description; remove the common description where a specific one is needed. Example title: «[Назва спеціальності] в Одесі — ОТФК ОНТУ» (“[Specialty name] in Odesa — OTFK ONTU”). Add the year only to materials that the editor will update annually. Check for a single main H1, the sequence of subheadings, and the absence of a repeated brand in the title.

Link the path «home → applicant → specialty → conditions → contacts» with plain HTML links via LocalizedUrl. Add substantive links between programmes, departments and related news. For significant PDFs keep an HTML context with a title and description; check the availability of file mirrors. Alt text describes the image, without a keyword list.

The editor checks the real content of the target DB: a seeder with demo data does not prove that the data exists on the live site. Correcting old news must not send them to Telegram again.

Readiness: all priority pages have approved information, unique metadata, working attachments and a clear next step. An editor is assigned as responsible for deadlines, cost and the annual update.

## Stage 3 Speed and structured data

Priority P1. Estimate: 2–4 days after measurements.

- Measure the mobile home page, a specialty page, a long CMS page, a news item with a photo, and a catalogue. Record the baseline results and repeat them after changes under the same conditions.
- First optimize the identified LCP source: banner sizes, responsive images, WebP and the priority of the main image; load images below the fold lazily. Set dimensions for images and iframes for layout stability. Based on measurements, reduce fonts and excess JS; verify caching of static resources and PHP/MySQL response time.
- Core Web Vitals targets: LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 at the 75th percentile of real visits. Lab Lighthouse is useful for diagnostics but does not confirm field INP. With insufficient traffic, record the absence of field data. [Google metrics](https://developers.google.com/search/docs/appearance/core-web-vitals).
- Review the existing EducationalOrganization, NewsArticle, BreadcrumbList and Event: real contacts, absolute URLs, images, author/publisher, correct dates, and conformity with the visible text. Keep the application's current time model; when fixing JSON-LD, verify Kyiv time with end-to-end tests.
- Add `alternateName` to EducationalOrganization with confirmed former names/abbreviations, and `sameAs` with official social media profiles, the page of the college itself on the ONTU website, and its record in ЄДЕБО (the Unified State Electronic Database on Education). Do not substitute the university's home page for the college's identity page. [Schema.org properties](https://schema.org/EducationalOrganization).
- Validate JSON-LD with the Schema Markup Validator, and supported types with the Rich Results Test. Keep FAQ as a useful substantive section: the 2023 restriction was later superseded by the discontinuation of FAQ rich results display on **7 May 2026**, confirmed by Google's entry of 8 May and by the removal of the documentation on 15 June 2026. FAQPage can be kept as a semantic description without expecting an extended snippet. [Google primary source](https://developers.google.com/search/updates#may-2026).
- Google announced the discontinuation of **Course Info** in June 2025; the entry of **9 September 2025** confirms the removal of the documentation and the end of this type's display in search results. Keep the existing Course as a semantic description of the programme, without expecting Course Info rich results. Do not conflate the discontinuation of a specific search feature with the removal of the Course type from Schema.org. [Google Search changes](https://developers.google.com/search/updates).

Readiness: no errors in the required fields of supported markup; measured speed problems are fixed or have a concrete next step. Production work fits shared hosting without Node and without a queue worker.

## Stage 4 English version and local visibility

Priority P2. Estimate: 1–3 days of technical work after proofreading; the duration of the translation depends on the editor.

Proofread first the home page, specialties, information about the college and contacts. Introduce a verifiable admission criterion for English indexing of a page: a complete published translation, editorial acceptance, current information and metadata. For collections, check the language of the cards and editable blocks, not only the section heading.

For admitted pairs add mutual absolute hreflang `uk`, `en` and `x-default`, including a link to the page itself; use the shared LocalizedUrl mechanism. `x-default` points to the Ukrainian version of the same material (for the home page — to `https://otfk.od.ua/`), not to the home page for all pages. The same set of language links is output on both variants. A complete English translation gets its own canonical. Incomplete variants with Ukrainian fallback keep noindex and are not included in the English sitemap/hreflang. Do not remove the existing global noindex without replacing it with an agreed admission policy. [Google rules for localized versions](https://developers.google.com/search/docs/specialty/international/localized-versions).

Clarify the official profile of the college in Google Maps/Business Profile, if it is available and meets the service's requirements: a unified name, address, phone and site. Check the links to the college from the ONTU website, official educational catalogues and real partners; change requests are prepared by the college's responsible person.

Readiness: hreflang is mutual and leads to indexable translations with status 200; the English and Ukrainian versions keep their own canonicals; public contacts match the official sources.

## Analytics and visibility in AI-driven search

Priority P1, basic analytics before launch. Estimate: 1–2 working days for GA4 setup, events, consent management and verification; agreeing the personal data processing policy is a separate task. The proposed tool is GA4 with advertising integrations disabled at the first stage. Create a resource/stream under the college's control, or keep the existing one and its history; assign an owner of access; document the identifiers without secrets.

Track `click_phone`, `click_email` and `file_download` with the document type or a safe page path. For file clicks and the automatic GA4 file_download, choose one mechanism so that an event is not counted twice. Verify in debugging: one click → one event; mark the key events and exclude internal visits and the test domain. A link click indicates intent, not a confirmed call or a completed download; actual downloads require server-side data. Do not pass personal data or arbitrary query parameters to analytics.

Before GA4 is enabled, agree with the college's responsible person on the privacy policy and the consent requirements for the applicable jurisdictions. For this plan adopt a conservative scheme: until consent is given, the analytics tag is not loaded and no analytics requests are sent; accept/decline buttons, and the later change of choice, are available in both locales. Refusal does not impede the site. Consent Mode by itself is not a banner and does not replace the consent decision. If GA4 is rejected, separately choose an alternative and recalculate the implementation; the absence of cookies alone does not resolve all data-processing questions. [Google consent modes](https://developers.google.com/tag-platform/security/guides/consent).

For AI Overviews and chat search, add clear factual answers in HTML: current specialty codes, qualification, study durations, admission after grade 9/11, campaign dates and official contacts. State the date of the update and the source of the rules; do not hide important information only in PDFs or images. Agreed markup, the identity of the organization and crawl accessibility are part of regular SEO work. Google does not require special markup for participation in AI Overviews/AI Mode and does not guarantee their display. [Google documentation](https://developers.google.com/search/docs/appearance/ai-features).

Use Bing Webmaster Tools to monitor crawling and visibility in Bing; connecting does not guarantee citation by any AI service. Separately track the recognizable referral visits from AI services in analytics, understanding that some sources do not pass the referrer. Do not include special promises of "appearing in AI answers" or the implementation of llms.txt among the mandatory work.

## Launch window

The planned window for replacing the application on otfk.od.ua is **October–December 2026**, with the target launch no later than 31 December, after P0 is complete and editorial acceptance is done. October allows the URL map to be prepared and leaves time for observation in the winter; this is an organizational choice that takes into account the June–August admission campaign specified by the user, and not a guarantee of index stabilization by a particular date.

If readiness is delayed, the reserve window is January–February 2027. **From March through August, do not carry out the planned site replacement, mass path changes or hosting migration**; updating information and necessary fixes are allowed. If the mandatory criteria are not met by the end of February, postpone the transfer to autumn. The person responsible for the launch confirms the readiness of the map, the files, the noindex check and the recovery plan. Recovery must restore working addresses and data, without overwriting new content with an outdated dump.

## Results control and order of execution

Before launch, complete P0 and prepare the Ukrainian pages for admissions, specialties and contacts. Then fix the measured speed and markup problems. The English launch is run separately, after editorial acceptance.

Immediately after the launch and a successful HTTP check, request re-crawling of the priority URLs via URL Inspection → Request indexing in Google Search Console: the home page, admissions, the specialty catalogue, key specialties and contacts. The responsible owner or a user with full access records the date and the list of submitted addresses; for a large number of materials, submit the current sitemap to Google and Bing. Requesting a single URL is not a request for the whole section: submit important child pages separately, within the quota. Resubmitting the same URL does not speed up crawling; the request does not guarantee indexing. [Google's instructions on re-crawling](https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl).

During the first week after launch, check daily the 404s in the new administrative log, 5xx errors via the available logs/external monitoring, redirects, the sitemap and key pages; after that, weekly. Submit the sitemap to Search Console and Bing Webmaster Tools, and check a selective set of URLs with the URL inspection tools. Before launch, confirm that the designated administrator can receive these reports on shared hosting.

After 4, 8 and 12 weeks, compare clicks, impressions, CTR and visits to the admission and specialty pages with the baseline period and with the same period last year, if the data is available. Brand (including former names) and non-brand queries, and Ukrainian and Russian queries, are counted separately. Based on the analytics settings above, record clicks on the committee's phone/email and on documents; website application forms have been removed, so form submissions must not be used as the current conversion. Analytics with consent sees only part of the visitors; its figures should not match Search Console.

Primary result criteria: no critical technical blockers, important pages are available for indexing, and the canonicals selected by Google match the plan. Target traffic growth percentages are to be set after obtaining the baseline data, taking into account admission seasonality; indexing and rankings cannot be guaranteed.

During implementation, add Feature tests for pagination canonicals, the noindex policy, the completeness/validity of the sitemap, lastmod stability after views/likes, language pairs and the unavailability of drafts. For redirects, verify the old CMS query, path encoding, file collisions, loops, the prohibition of external destinations and admin rights; for the 404 log — aggregation and cleanup. The HTTP barrier is verified on the real hosting, while events and consent are verified by browser scenarios of acceptance, refusal and change of choice. Verify future news: currently `News::published()` excludes them from the lists, while `NewsController::show()` checks only the `is_published` flag; agree the availability of the direct URL with the publication date. After changing the behaviour, run the profile tests, then the general `composer test`; run the frontend build when its sources are changed. New tables, admin pages and workflow steps are only proposed in the plan so far: at implementation, update ARCHITECTURE, DEPLOY and both instruction files together with the HTML twins. Commit/push/deploy — only on a separate request.
