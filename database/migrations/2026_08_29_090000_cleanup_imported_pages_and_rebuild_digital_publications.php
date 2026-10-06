<?php

use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Чистка артефактів імпорту старого сайту (otfk:import-pages) + відбудова
 * розділу «Цифрові видання у галузях» 1:1 за https://otfk.od.ua/:
 *
 * 1. Дублі повторних прогонів імпорту: «Information about college»
 *    (information-about-college-2), «Академічна доброчесність»
 *    (akademicna-dobrocesnist), «Адміністрація» (сторінка administraciia —
 *    дубль модуля персоналу /administratsiya).
 * 2. Сторінки-каталоги видань, створені з першої лінк-картки старого сайту
 *    («Мінфін — все про фінанси…» тощо), отримують правильні назви/слаги
 *    (IT, Енергетика, Легка промисловість, Технологія, Економіка) та тіла
 *    зі старого сайту; бібліографічні підсторінки (link_ed/list) — теж.
 *
 * Тіла сторінок — у database/content/digital-publications/*.html (зняті зі
 * старого сайту). Маркери <!--imported-from:…--> зберігаються, щоб повторний
 * імпорт оновлював ці самі сторінки, а не плодив копії. Ідемпотентно.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Жовтневе наповнення вже актуалізовано; історичні фікстури його не перезаписують.
        if (\Illuminate\Support\Facades\Schema::hasColumn('pages', 'translation_published')) {
            return;
        }

        $this->mergeInformationAboutCollege();
        $this->mergeAcademicIntegrity();
        $this->dropAdministrationDuplicate();
        $this->rebuildDigitalPublications();

        Cache::forget('menu.navigation');
        Cache::forget('content_checklist_badge');
    }

    public function down(): void
    {
        // Контент не відкочуємо: міграція виправляє дані, а не схему.
    }

    /** Дубль «Information about college»: тіло з «-2» переїжджає в канонічний слаг. */
    private function mergeInformationAboutCollege(): void
    {
        $thin = Page::query()->where('slug', 'information-about-college')->first();
        $full = Page::query()->where('slug', 'information-about-college-2')->first();

        if (! $full) {
            return;
        }

        if (! $thin) {
            $full->update(['slug' => 'information-about-college']);

            return;
        }

        // Переносимо лише якщо канонічна сторінка — тонка копія без маркера імпорту.
        if (! str_contains((string) $thin->body, 'imported-from:')) {
            $thin->update([
                'body' => $full->body,
                'meta_description' => $full->meta_description ?: $thin->meta_description,
                'section' => $thin->section ?: 'pro-koledzh',
            ]);
        }

        $this->retargetAndDelete($full, $thin);
    }

    /** Дубль «Академічна доброчесність»: імпортоване тіло — у сторінку з меню. */
    private function mergeAcademicIntegrity(): void
    {
        $keeper = Page::query()->where('slug', 'akademichna-dobrochesnist')->first();
        $dupe = Page::query()->where('slug', 'akademicna-dobrocesnist')->first();

        if (! $dupe) {
            return;
        }

        if (! $keeper) {
            $dupe->update(['slug' => 'akademichna-dobrochesnist']);

            return;
        }

        if (! str_contains((string) $keeper->body, 'imported-from:')
            && str_contains((string) $dupe->body, 'imported-from:')) {
            $keeper->update([
                'body' => $dupe->body,
                'meta_description' => $dupe->meta_description ?: $keeper->meta_description,
            ]);
        }

        $this->retargetAndDelete($dupe, $keeper);
    }

    /** Сторінка «Адміністрація» — дубль модуля персоналу: меню веде на /administratsiya. */
    private function dropAdministrationDuplicate(): void
    {
        $dup = Page::query()->where('slug', 'administraciia')->first();

        if (! $dup) {
            return;
        }

        MenuItem::query()->where('page_id', $dup->id)->get()->each(
            fn (MenuItem $item) => $item->update(['link_type' => 'url', 'url' => '/administratsiya', 'page_id' => null])
        );
        Page::query()->where('parent_id', $dup->id)->update(['parent_id' => $dup->parent_id]);
        $dup->delete();
    }

    /**
     * Розділ «Цифрові видання у галузях»: 5 сторінок-каталогів + 3 бібліографії,
     * всі — дочірні хаба tsyfrovi-vydannya-u-haluzyah, як на старому сайті.
     */
    private function rebuildDigitalPublications(): void
    {
        $hub = Page::query()->where('slug', 'tsyfrovi-vydannya-u-haluzyah')->first();

        if (! $hub) {
            return; // свіжа БД без імпортованого контенту — нічого відбудовувати
        }

        $hub->update([
            'body' => $this->contentFile('tsyfrovi-vydannya-u-haluzyah')
                . "\n" . $this->marker('/student/digital_publications_in_industries'),
        ]);

        $pages = [
            // oldPath (маркер) => [slug, title, excerpt, contentFile, sort]
            '/student/digital_publications_in_industries/it_public' => ['vydannya-it', 'IT', 'Видання та видавництва в сфері IT.', 'vydannya-it', 1],
            '/student/digital_publications_in_industries/energy_public' => ['vydannya-enerhetyka', 'Енергетика', 'Видання та видавництва в сфері енергетики.', 'vydannya-enerhetyka', 2],
            '/student/digital_publications_in_industries/light_industry_public' => ['vydannya-lehka-promyslovist', 'Легка промисловість', 'Видання та видавництва в сфері легкої промисловості.', 'vydannya-lehka-promyslovist', 3],
            '/student/digital_publications_in_industries/technology_public' => ['vydannya-tekhnolohiya', 'Технологія', 'Видання та видавництва для технологів.', 'vydannya-tekhnolohiya', 4],
            '/student/digital_publications_in_industries/economy_public' => ['vydannya-ekonomika', 'Економіка', 'Видання та видавництва в сфері економіки.', 'vydannya-ekonomika', 5],
            '/student/digital_publications_in_industries/it_public/link_ed' => ['spysok-posylan-it', 'Список посилань: IT', 'Статті, підручники та журнали за дисциплінами.', 'spysok-posylan-it', 6],
            '/student/digital_publications_in_industries/economy_public/link_ed' => ['spysok-posylan-ekonomika', 'Список посилань: Економіка', 'Статті, підручники та журнали за дисциплінами.', 'spysok-posylan-ekonomika', 7],
            '/student/digital_publications_in_industries/light_industry_public/list' => ['perelik-literatury-lehka-promyslovist', 'Перелік корисної літератури з легкої промисловості', 'Підручники та посібники за дисциплінами.', 'perelik-literatury-lehka-promyslovist', 8],
        ];

        foreach ($pages as $oldPath => [$slug, $title, $excerpt, $file, $sort]) {
            $page = Page::query()->where('body', 'like', '%' . $this->marker($oldPath) . '%')->first()
                ?? Page::query()->where('slug', $slug)->first();

            $attributes = [
                'title' => $title,
                'slug' => $slug,
                'parent_id' => $hub->id,
                'section' => 'studentu',
                'excerpt' => $excerpt,
                'body' => $this->contentFile($file) . "\n" . $this->marker($oldPath),
                'is_published' => true,
                'sort_order' => $sort,
            ];

            $page ? $page->update($attributes) : Page::query()->create($attributes);
        }
    }

    /** Переприв'язати меню та дочірні сторінки з дубля на канонічну сторінку й видалити дубль. */
    private function retargetAndDelete(Page $dupe, Page $keeper): void
    {
        MenuItem::query()->where('page_id', $dupe->id)->update(['page_id' => $keeper->id]);
        Page::query()->where('parent_id', $dupe->id)->update(['parent_id' => $keeper->id]);
        $dupe->delete();
    }

    private function marker(string $oldPath): string
    {
        return '<!--imported-from:https://otfk.od.ua' . $oldPath . '-->';
    }

    private function contentFile(string $name): string
    {
        return trim((string) file_get_contents(
            database_path('content/digital-publications/' . $name . '.html')
        ));
    }
};
