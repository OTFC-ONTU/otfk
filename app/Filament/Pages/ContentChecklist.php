<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DepartmentResource;
use App\Filament\Resources\DocumentCategoryResource;
use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\PageResource;
use App\Models\Department;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Setting;
use Filament\Pages\Page as FilamentPage;
use Illuminate\Support\Facades\Cache;

/**
 * Живий чек-лист наповнення сайту: показує, що ще порожнє або заглушка,
 * з прямими посиланнями на редагування. Допомагає довести контент до кінця.
 */
class ContentChecklist extends FilamentPage
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Що наповнити';

    protected static ?string $title = 'Що ще наповнити';

    protected static ?string $navigationGroup = 'Структура сайту';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.content-checklist';

    /** Скільки символів «живого» тексту вважаємо мінімумом, щоб сторінка не була заглушкою. */
    private const STUB_MIN_CHARS = 200;

    /**
     * Слаги сторінок-плиток: їх перехоплюють явні маршрути (розклад дзвінків,
     * FAQ, квіз, спеціальності), тіло таких сторінок ніколи не рендериться — наповнювати нічого.
     */
    private const ROUTE_TILE_SLUGS = ['rozklad-dzvinkiv', 'faq', 'kviz', 'spetsialnosti'];

    /** Сторінки без змісту (порожні або дуже короткі / з маркерами «в розробці»). */
    public function stubPages(): array
    {
        $phrases = ['у розробці', 'в розробці', 'буде додано', 'готується', 'готуються', 'незабаром', 'невдовзі', 'матеріали готуються'];

        // Хаби (сторінки з опублікованими дочірніми) рендерять плитки розділу — довге тіло їм не потрібне.
        return Page::query()
            ->whereNotIn('slug', self::ROUTE_TILE_SLUGS)
            ->whereDoesntHave('children', fn ($q) => $q->where('is_published', true))
            ->orderBy('section')->orderBy('title')->get()
            ->filter(function (Page $p) use ($phrases) {
                $text = trim(strip_tags((string) $p->body));
                $hasPhrase = collect($phrases)->contains(fn ($ph) => mb_stripos($text, $ph) !== false);

                return mb_strlen($text) < self::STUB_MIN_CHARS || $hasPhrase;
            })
            ->map(fn (Page $p) => [
                'label' => $p->title,
                'meta' => $p->section ?: '—',
                'url' => PageResource::getUrl('edit', ['record' => $p]),
            ])
            ->values()->all();
    }

    /** Категорії документів без жодного документа. */
    public function emptyDocumentCategories(): array
    {
        return DocumentCategory::query()->withCount('documents')->orderBy('title')->get()
            ->filter(fn (DocumentCategory $c) => $c->documents_count === 0)
            ->map(fn (DocumentCategory $c) => [
                'label' => $c->title,
                'meta' => '0 документів',
                'url' => DocumentCategoryResource::getUrl('edit', ['record' => $c]),
            ])
            ->values()->all();
    }

    /** Відділення/комісії без персоналу або без опису. */
    public function departmentsNeedingWork(): array
    {
        return Department::query()->withCount('staff')->orderBy('title')->get()
            ->filter(fn (Department $d) => $d->staff_count === 0 || mb_strlen(trim(strip_tags((string) $d->description))) < 80)
            ->map(fn (Department $d) => [
                'label' => $d->title,
                'meta' => $d->staff_count === 0 ? 'немає персоналу' : 'короткий опис',
                'url' => DepartmentResource::getUrl('edit', ['record' => $d]),
            ])
            ->values()->all();
    }

    /** Галереї без фотографій. */
    public function emptyGalleries(): array
    {
        return Gallery::query()->withCount('photos')->orderBy('title')->get()
            ->filter(fn (Gallery $g) => $g->photos_count === 0)
            ->map(fn (Gallery $g) => [
                'label' => $g->title,
                'meta' => '0 фото',
                'url' => GalleryResource::getUrl('edit', ['record' => $g]),
            ])
            ->values()->all();
    }

    /** Важливі налаштування, які ще не заповнені. */
    public function missingSettings(): array
    {
        $keys = [
            'contact_phone' => 'Телефон',
            'contact_email' => 'Електронна пошта',
            'contact_address' => 'Адреса',
            'work_hours' => 'Графік роботи',
            'map_embed' => 'Карта на сторінці контактів',
            'social_facebook' => 'Facebook',
            'social_instagram' => 'Instagram',
            'social_youtube' => 'YouTube-канал',
            'site_description' => 'Опис сайту (SEO)',
        ];

        $rows = Setting::query()->whereIn('key', array_keys($keys))->get()->keyBy('key');
        $missing = [];

        foreach ($keys as $key => $label) {
            if (blank(optional($rows->get($key))->value)) {
                $missing[] = [
                    'label' => $label,
                    'meta' => $key,
                    'url' => $key === 'site_description' ? GeneralSettings::getUrl() : ContactSettings::getUrl(),
                ];
            }
        }

        return $missing;
    }

    /** Загальна кількість незакритих пунктів — для бейджа в меню (кеш 60с, бо рахується на кожен рендер). */
    public static function getNavigationBadge(): ?string
    {
        $total = Cache::remember('content_checklist_badge', 60, function () {
            $page = new static;

            return count($page->stubPages())
                + count($page->emptyDocumentCategories())
                + count($page->departmentsNeedingWork())
                + count($page->emptyGalleries())
                + count($page->missingSettings());
        });

        return $total > 0 ? (string) $total : null;
    }
}
