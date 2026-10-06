<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Page;
use App\Models\Program;
use App\Models\QuickLink;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->quickLinks();
        $this->categories();
        $this->documents();          // Фаза 2
        $this->specialties();        // Фаза 2
        $this->departmentsAndStaff(); // Фаза 3
        $this->galleries();           // Фаза 4
        $this->structureAndMenu();
        $this->news();
        $this->videos();
        $this->banners();
    }

    private function placeholder(string $title): string
    {
        return "<p>Розділ «{$title}» наповнюється. Тут буде розміщено детальну та актуальну інформацію.</p>"
            . '<p>Наразі ви можете звернутися до адміністрації коледжу за додатковими відомостями '
            . 'або скористатися формою на сторінці «Контакти».</p>';
    }

    private function settings(): void
    {
        $items = [
            ['site_description', 'Офіційний сайт Одеського технічного фахового коледжу ОНТУ - спеціальності, новини, вступ.', 'general', 'textarea'],
            ['footer_about', 'Одеський технічний фаховий коледж - структурний підрозділ Одеського національного технологічного університету.', 'general', 'textarea'],
            ['logo', '', 'general', 'image'],
            ['favicon', '', 'general', 'image'],
            ['brand_short', 'ОТФК ОНТУ', 'general', 'text'],
            ['brand_name', 'Одеський технічний фаховий коледж', 'general', 'text'],
            ['contact_address', 'м. Одеса, вул. Прикладна, 1', 'contacts', 'text'],
            ['contact_phone', '+38 (048) 000-00-00', 'contacts', 'text'],
            ['contact_email', 'info@otfk.od.ua', 'contacts', 'text'],
            ['work_hours', 'Пн-Пт: 08:00-17:00', 'contacts', 'text'],
            ['social_facebook', 'https://www.facebook.com/', 'social', 'url'],
            ['social_instagram', 'https://www.instagram.com/', 'social', 'url'],
            ['social_youtube', '', 'social', 'url'],
            ['map_embed', 'https://www.google.com/maps?q=Odesa&output=embed', 'contacts', 'url'],
        ];

        foreach ($items as [$key, $value, $group, $type]) {
            Setting::updateOrCreate(['key' => $key], compact('value', 'group', 'type'));
        }
    }

    private function quickLinks(): void
    {
        $links = [
            // location, title, description, url, icon, color, sort
            ['home_tile', 'Абітурієнту', 'Вступ, спеціальності, правила прийому', '/abituriyentu', 'academic-cap', 'brand', 1],
            ['home_tile', 'Студенту', 'Навчання, гуртожиток, життя коледжу', '/studentu', 'user-group', 'gold', 2],
            ['home_tile', 'Про коледж', 'Історія, адміністрація, структура', '/pro-koledzh', 'building-library', 'brand', 3],
            ['home_tile', 'Публічна інформація', 'Документи, звіти, нормативна база', '/dokumenty', 'document-text', 'gold', 4],
            ['footer_partner', 'ОНТУ', null, 'https://ontu.edu.ua', null, 'brand', 1],
            ['footer_partner', 'МОН України', null, 'https://mon.gov.ua', null, 'brand', 2],
        ];

        foreach ($links as [$location, $title, $description, $url, $icon, $color, $sort]) {
            QuickLink::updateOrCreate(
                ['location' => $location, 'title' => $title],
                [
                    'description' => $description,
                    'url' => $url,
                    'icon' => $icon,
                    'color' => $color,
                    'open_new_tab' => $location === 'footer_partner',
                    'sort_order' => $sort,
                    'is_visible' => true,
                ]
            );
        }
    }

    private function categories(): void
    {
        $cats = ['Новини' => 'novyny', 'Події' => 'podiyi', 'Конференції' => 'konferentsiyi', 'Оголошення' => 'oholoshennya'];
        $i = 0;
        foreach ($cats as $title => $slug) {
            NewsCategory::updateOrCreate(['slug' => $slug], ['title' => $title, 'sort_order' => $i++]);
        }
    }

    /** Категорії «Публічної інформації» + демо-документи. */
    private function documents(): void
    {
        $cats = [
            'Нормативна база' => 'normatyvna-baza',
            'Договори' => 'dohovory',
            'Звіти' => 'zvity',
            'План роботи' => 'plan-roboty',
            'Моніторинг якості освіти' => 'monitorynh-yakosti-osvity',
            'Бюджет коледжу' => 'byudzhet-koledzhu',
            'Вакантні посади' => 'vakantni-posady',
        ];

        $i = 0;
        foreach ($cats as $title => $slug) {
            $cat = DocumentCategory::updateOrCreate(['slug' => $slug], ['title' => $title, 'sort_order' => $i++]);
            $cat->documents()->delete();
            for ($d = 1; $d <= 3; $d++) {
                $cat->documents()->create([
                    'title' => "{$title}: документ №{$d}",
                    'description' => 'Демонстраційний запис. Завантажте реальний файл через адмінпанель.',
                    'published_at' => now()->subDays($d * 10),
                    'sort_order' => $d,
                    'is_published' => true,
                ]);
            }
        }
    }

    /** Спеціальності + освітні програми. */
    private function specialties(): void
    {
        $items = [
            ['Інженерія програмного забезпечення', 'inzheneriya-programnoho-zabezpechennya', 'F2'],
            ['Комп’ютерна інженерія', 'kompyuterna-inzheneriya', 'F7'],
            ['Харчові технології', 'kharchovi-tekhnolohiyi', 'G13'],
        ];

        foreach ($items as $i => [$title, $slug, $code]) {
            // Реальні описи та ОПП завозить міграція import_specialties_content —
            // сидер їх не перетирає: заглушки й демо-програма лише для нових записів.
            $sp = Specialty::firstOrNew(['slug' => $slug]);

            if (! $sp->exists) {
                $sp->fill([
                    'title' => $title,
                    'code' => $code,
                    'short_description' => 'Сучасна підготовка фахівців за спеціальністю «' . $title . '».',
                    'description' => $this->placeholder($title),
                    'degree' => 'Фаховий молодший бакалавр',
                    'study_form' => 'Денна, заочна',
                    'duration' => 'на основі 9 класів - 3 р. 10 міс.',
                    'sort_order' => $i,
                    'is_published' => true,
                ]);
            }

            $sp->save();

            if ($sp->programs()->doesntExist()) {
                $sp->programs()->create([
                    'title' => 'Освітньо-професійна програма «' . $title . '»',
                    'description' => 'Демонстраційний запис. Завантажте файл програми через адмінпанель.',
                    'sort_order' => 0,
                ]);
            }
        }
    }

    /** Структурні підрозділи + персонал (адміністрація та викладачі). */
    private function departmentsAndStaff(): void
    {
        $deps = [
            ['viddilennya', 'Відділення інформаційних технологій', 'viddilennya-it'],
            ['viddilennya', 'Відділення економіки та сфери обслуговування', 'viddilennya-ekonomiky'],
            ['tsyklova-komisiya', 'Циклова комісія програмної інженерії', 'ck-prohramnoyi-inzheneriyi'],
            ['tsyklova-komisiya', 'Циклова комісія загальноосвітніх дисциплін', 'ck-zahalnoosvitnih'],
            ['kafedra', 'Кафедра інженерії програмного забезпечення', 'kafedra-ipz'],
            ['kafedra', 'Кафедра загальнотехнічних дисциплін', 'kafedra-zahalnotehnichnyh'],
        ];

        $bySlug = [];
        foreach ($deps as $i => [$type, $title, $slug]) {
            $bySlug[$slug] = Department::updateOrCreate(['slug' => $slug], [
                'title' => $title, 'type' => $type, 'description' => $this->placeholder($title),
                'sort_order' => $i, 'is_published' => true,
            ]);
        }

        Staff::query()->delete();

        $admin = [
            ['Петренко Олександр Іванович', 'Директор коледжу', 'кандидат технічних наук'],
            ['Коваленко Марія Сергіївна', 'Заступник директора з навчальної роботи', null],
            ['Шевченко Андрій Петрович', 'Заступник директора з виховної роботи', null],
            ['Бондаренко Ольга Вікторівна', 'Завідувач навчальної частини', null],
        ];
        foreach ($admin as $i => [$name, $position, $degree]) {
            Staff::create([
                'full_name' => $name, 'position' => $position, 'category' => 'administration',
                'academic_degree' => $degree, 'email' => 'info@otfk.od.ua', 'sort_order' => $i, 'is_published' => true,
            ]);
        }

        $teachers = [
            ['Ткаченко Ірина Олегівна', 'Викладач програмування', 'kafedra-ipz', 'спеціаліст вищої категорії'],
            ['Мельник Сергій Васильович', 'Викладач інформатики', 'kafedra-ipz', null],
            ['Кравчук Наталія Ігорівна', 'Викладач математики', 'kafedra-zahalnotehnichnyh', 'викладач-методист'],
            ['Поліщук Дмитро Олександрович', 'Викладач фізики', 'kafedra-zahalnotehnichnyh', null],
        ];
        foreach ($teachers as $i => [$name, $position, $depSlug, $degree]) {
            Staff::create([
                'full_name' => $name, 'position' => $position, 'category' => 'teacher',
                'department_id' => $bySlug[$depSlug]->id ?? null, 'academic_degree' => $degree,
                'sort_order' => $i, 'is_published' => true,
            ]);
        }
    }

    /** Демонстраційні фотоальбоми (фото додаються через адмінку). */
    private function galleries(): void
    {
        $albums = [
            ['Урочистості до річниці коледжу', 'urochystosti-richnytsya'],
            ['Студентське життя', 'studentske-zhyttya-foto'],
            ['День відкритих дверей', 'den-vidkrytyh-dverey-foto'],
        ];

        foreach ($albums as $i => [$title, $slug]) {
            Gallery::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'description' => 'Демонстраційний альбом. Додайте фотографії через адмінпанель.',
                'published_at' => now()->subDays(($i + 1) * 15),
                'sort_order' => $i,
                'is_published' => true,
            ]);
        }
    }

    private function makePage(string $title, string $slug, ?int $parentId = null, ?string $section = null): Page
    {
        return Page::updateOrCreate(['slug' => $slug], [
            'title' => $title,
            'parent_id' => $parentId,
            'section' => $section,
            'body' => $this->placeholder($title),
            'is_published' => true,
            'sort_order' => 0,
        ]);
    }

    private function structureAndMenu(): void
    {
        // Розділи з дочірніми СТОРІНКАМИ. Значення дочірнього елемента:
        //   'slug'                  → сторінка
        //   ['url', '/path']        → пряме посилання
        $sections = [
            'pro-koledzh' => ['title' => 'Про коледж', 'children' => [
                'Історія' => 'istoriya', 'Коледж сьогодні' => 'koledzh-sohodni',
                'Стратегія розвитку' => 'stratehiya-rozvytku', 'Концепція освітньої діяльності' => 'kontseptsiya-osvitnoyi-diyalnosti',
                'Адміністрація' => ['url', '/administratsiya'], 'Гордість коледжу' => 'hordist-koledzhu',
            ]],
            'struktura' => ['title' => 'Структура', 'children' => [
                'Відділення' => ['url', '/struktura#viddilennya'],
                'Циклові комісії' => ['url', '/struktura#tsyklova-komisiya'],
                'Кафедри' => ['url', '/struktura#kafedra'],
                'Бібліотека' => 'biblioteka', 'Студентське самоврядування' => 'studentske-samovryaduvannya',
                'Взаємодія з роботодавцями' => 'vzayemodiya-z-robotodavtsyamy',
            ]],
            'abituriyentu' => ['title' => 'Абітурієнту', 'children' => [
                // Сторінки-плитки: слаги перехоплюються маршрутами FAQ та квізу
                'Питання та відповіді' => 'faq',
                'Яка спеціальність мені підходить?' => 'kviz',
                'Правила прийому' => 'pravyla-pryyomu', 'Вартість навчання' => 'vartist-navchannya',
                'Наші спеціальності' => ['url', '/spetsialnosti'],
                'Освітньо-професійні програми' => ['url', '/dokumenty/osvitno-profesiyni-prohramy'],
                'Розклад вступних випробувань' => 'rozklad-vstupnykh-vyprobuvan',
                'Результати вступних іспитів' => 'rezultaty-vstupnykh-ispytiv', 'Рейтингові списки' => 'reytynhovi-spysky',
            ]],
            'studentu' => ['title' => 'Студенту', 'children' => [
                'Навчання' => 'navchannya', 'Дипломне проектування' => 'dyplomne-proektuvannya',
                'Навчально-виробнича робота' => 'navchalno-vyrobnycha-robota', 'Виховна робота' => 'vyhovna-robota',
                'Соціально-психологічна служба' => 'sotsialno-psyholohichna-sluzhba', 'Спортивне життя' => 'sportyvne-zhyttya',
                'Гуртожиток' => 'hurtozhytok', 'Академічна доброчесність' => 'akademichna-dobrochesnist',
                // Сторінка-плитка: сам слаг перехоплюється маршрутом розкладу дзвінків
                'Розклад дзвінків' => 'rozklad-dzvinkiv',
            ]],
        ];

        // «Публічна інформація» - посилання на категорії документів (/dokumenty/{slug}).
        $docChildren = DocumentCategory::ordered()->get();

        MenuItem::query()->delete();
        $order = 0;

        // 1. Головна
        MenuItem::create(['label' => 'Головна', 'link_type' => 'route', 'url' => 'home', 'sort_order' => $order++]);

        // 2-5. Розділи зі сторінками
        foreach (['pro-koledzh', 'struktura', 'abituriyentu', 'studentu'] as $slug) {
            $cfg = $sections[$slug];

            if ($slug === 'struktura') {
                // Корінь «Структура» веде на модуль /struktura (без сторінки-розділу).
                $root = MenuItem::create(['label' => $cfg['title'], 'link_type' => 'url', 'url' => '/struktura', 'sort_order' => $order++]);
                $parentPageId = null;
            } else {
                $sectionPage = $this->makePage($cfg['title'], $slug, null, $slug);
                $root = MenuItem::create(['label' => $cfg['title'], 'link_type' => 'page', 'page_id' => $sectionPage->id, 'sort_order' => $order++]);
                $parentPageId = $sectionPage->id;
            }

            $ci = 0;
            foreach ($cfg['children'] as $ctitle => $cval) {
                if (is_array($cval) && $cval[0] === 'url') {
                    MenuItem::create(['label' => $ctitle, 'link_type' => 'url', 'url' => $cval[1], 'parent_id' => $root->id, 'sort_order' => $ci++]);
                } else {
                    $cp = $this->makePage($ctitle, $cval, $parentPageId, $slug);
                    MenuItem::create(['label' => $ctitle, 'link_type' => 'page', 'page_id' => $cp->id, 'parent_id' => $root->id, 'sort_order' => $ci++]);
                }
            }
        }

        // 6. Конференції → новини за відповідною категорією
        MenuItem::create(['label' => 'Конференції', 'link_type' => 'url', 'url' => '/novyny?category=konferentsiyi', 'sort_order' => $order++]);

        // 7. Публічна інформація → /dokumenty + категорії
        $pubRoot = MenuItem::create(['label' => 'Публічна інформація', 'link_type' => 'url', 'url' => '/dokumenty', 'sort_order' => $order++]);
        $ci = 0;
        foreach ($docChildren as $cat) {
            MenuItem::create(['label' => $cat->title, 'link_type' => 'url', 'url' => '/dokumenty/' . $cat->slug, 'parent_id' => $pubRoot->id, 'sort_order' => $ci++]);
        }

        // 8. Дистанційне навчання (сторінка-хаб із посиланнями)
        $dyst = $this->makePage('Дистанційне навчання', 'dystantsiyne-navchannya');
        $dyst->update(['body' => '<p>Платформи та ресурси для дистанційного навчання у коледжі:</p>'
            . '<ul><li><a href="/video">Навчальні відео коледжу</a></li>'
            . '<li><a href="https://zoom.us" target="_blank" rel="noopener">Відеоконференції Zoom</a></li>'
            . '<li><a href="https://www.netacad.com" target="_blank" rel="noopener">Cisco Networking Academy</a></li>'
            . '<li><a href="https://workspace.google.com" target="_blank" rel="noopener">Google Workspace для навчання</a></li></ul>']);
        MenuItem::create(['label' => 'Дистанційне навчання', 'link_type' => 'page', 'page_id' => $dyst->id, 'sort_order' => $order++]);

        // 9. Новини
        MenuItem::create(['label' => 'Новини', 'link_type' => 'route', 'url' => 'news.index', 'sort_order' => $order++]);

        // 9b. Галерея
        MenuItem::create(['label' => 'Галерея', 'link_type' => 'url', 'url' => '/halereya', 'sort_order' => $order++]);

        // 10. Ліцензування та акредитація
        $lits = $this->makePage('Ліцензування та акредитація', 'litsenzuvannya-ta-akredytatsiya');
        MenuItem::create(['label' => 'Ліцензування та акредитація', 'link_type' => 'page', 'page_id' => $lits->id, 'sort_order' => $order++]);

        // 11. Контакти
        MenuItem::create(['label' => 'Контакти', 'link_type' => 'route', 'url' => 'contacts', 'sort_order' => $order++]);
    }

    private function news(): void
    {
        $byCat = NewsCategory::pluck('id', 'slug');

        $items = [
            ['Студенти коледжу здобули перемогу на обласному конкурсі фахової майстерності', 'novyny', 1, true],
            ['Відбулася науково-практична конференція з інженерії програмного забезпечення', 'konferentsiyi', 2, true],
            ['Команда коледжу взяла участь у військово-спортивних змаганнях', 'podiyi', 2, false],
            ['Презентація проєкту наших студентів на міжнародній виставці', 'podiyi', 12, false],
            ['Коледж долучився до програми «Наукова еліта України»', 'novyny', 12, false],
            ['Волонтерська діяльність студентського гуртка коледжу', 'novyny', 16, false],
            ['Оновлено графік консультацій для абітурієнтів 2026 року', 'oholoshennya', 20, false],
            ['День відкритих дверей: запрошуємо майбутніх студентів', 'oholoshennya', 25, true],
            ['Підсумки навчального року: досягнення та плани', 'novyny', 30, false],
        ];

        foreach ($items as $idx => [$title, $catSlug, $daysAgo, $featured]) {
            $slug = Str::slug($title) . '-' . ($idx + 1);
            News::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'category_id' => $byCat[$catSlug] ?? null,
                'excerpt' => 'Стислий анонс новини. Повний текст матеріалу буде додано редактором у адміністративній панелі.',
                'body' => '<p>Це демонстраційний текст новини. Реальний зміст буде наповнено через адмінпанель Filament.</p>'
                    . '<p>Адміністратор може додати фотографії, форматування, посилання та інші матеріали.</p>',
                'published_at' => now()->subDays($daysAgo),
                'is_published' => true,
                'is_featured' => $featured,
                'views' => random_int(15, 480),
            ]);
        }
    }

    private function videos(): void
    {
        Video::query()->delete();

        $items = [
            ['Привітання директора коледжу', 'M7lc1UVf-VE', 5],
            ['Віртуальна екскурсія коледжем', 'aqz-KE-bpKQ', 20],
            ['Наші спеціальності: огляд', 'jfKfPfyJRdk', 40],
            ['Студентське життя коледжу', 'ScMzIvxBSi4', 60],
        ];

        foreach ($items as $i => [$title, $youtube, $daysAgo]) {
            Video::create([
                'title' => $title,
                'youtube_id' => $youtube,
                'published_at' => now()->subDays($daysAgo),
                'sort_order' => $i,
                'is_published' => true,
            ]);
        }
    }

    private function banners(): void
    {
        Banner::query()->delete();

        Banner::create([
            'title' => 'Вступ 2026 - приєднуйся до нас!',
            'subtitle' => 'Сучасні технічні спеціальності, практична підготовка та подальше працевлаштування.',
            'link_url' => '/spetsialnosti',
            'link_label' => 'Наші спеціальності',
            'sort_order' => 0,
            'is_published' => true,
        ]);

        Banner::create([
            'title' => 'Одеський технічний фаховий коледж ОНТУ',
            'subtitle' => 'Якісна фахова передвища освіта з багаторічними традиціями.',
            'link_url' => '/pro-koledzh',
            'link_label' => 'Про коледж',
            'sort_order' => 1,
            'is_published' => true,
        ]);
    }
}
