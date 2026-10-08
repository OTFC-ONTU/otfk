<?php

namespace App\Models;

use App\Models\Concerns\KeepsPublicUrls;
use App\Models\Concerns\HasSortOrder;
use App\Casts\SafeHtml;
use App\Models\Concerns\FlushesSitemap;
use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use App\Support\LocalizedHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Staff extends Model
{
    use KeepsPublicUrls;
    use HasSortOrder;
    use FlushesSitemap;
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['photo'];

    protected $table = 'staff';

    public const CATEGORIES = [
        'administration' => 'Адміністрація',
        'teacher' => 'Викладач',
    ];

    protected $fillable = ['full_name_en', 'position_en', 'academic_degree_en', 'bio_en', 'translation_published',
        'full_name', 'slug', 'position', 'category', 'department_id', 'photo',
        'email', 'phone', 'bio', 'academic_degree', 'sort_order', 'is_published', 'profile_page_id', 'qualification_page_id',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'translation_published' => 'boolean', 'bio' => SafeHtml::class, 'bio_en' => SafeHtml::class];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Сторінка «Результати професійної та наукової діяльності» викладача. */
    public function profilePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'profile_page_id');
    }

    /** Сторінка «Відомості про підвищення кваліфікації» викладача. */
    public function qualificationPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'qualification_page_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('full_name');
    }

    public function scopeAdministration($query)
    {
        return $query->where('category', 'administration');
    }

    /** Унікальний слаг із ПІБ (за потреби з числовим суфіксом). */
    public static function uniqueSlug(string $fullName, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($fullName, 90, '')) ?: 'pratsivnyk';
        $slug = $base;

        for ($i = 2; static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Факти з біо у форматі «<strong>Мітка:</strong> значення» — для таблиці на
     * персональній сторінці. Повертає [['label' => …, 'value' => …], …].
     */
    public function bioFacts(): array
    {
        [$html] = $this->splitBio();

        $text = preg_replace('/\s+/u', ' ', strip_tags($html, '<strong>'));
        preg_match_all('/<strong>\s*([^<]+?)\s*:?\s*<\/strong>(.*?)(?=<strong>|$)/su', (string) $text, $m, PREG_SET_ORDER);

        $facts = [];

        foreach ($m as $pair) {
            $value = trim(html_entity_decode(strip_tags($pair[2])), " \t\n\r\0\x0B.,;-");

            if (filled($value)) {
                $facts[] = ['label' => rtrim(trim($pair[1]), ':'), 'value' => $value];
            }
        }

        return $facts;
    }

    /**
     * Посилання з біо (персональні сторінки «професійна діяльність» /
     * «підвищення кваліфікації»): [['url' => …, 'label' => …], …].
     * Мітка — фраза перед посиланням, а не саме слово «посилання».
     */
    public function bioLinks(): array
    {
        return $this->splitBio()[1];
    }

    /**
     * Ділить біо на [html без речень-посилань, список посилань]. Речення-обгортка
     * («Результати … викладача - <a>посилання</a>.») належить посиланню, решта — фактам.
     *
     * @return array{0: string, 1: array<int, array{url: string, label: string}>}
     */
    private function splitBio(): array
    {
        $bio = (string) LocalizedHtml::links($this->localized('bio'));

        if (blank($bio)) {
            return ['', []];
        }

        preg_match_all('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>\s*\.?/su', $bio, $anchors, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $links = [];
        $clean = '';
        $cursor = 0;

        foreach ($anchors as $anchor) {
            [$whole, $wholeOffset] = $anchor[0];
            $before = substr($bio, $cursor, $wholeOffset - $cursor);

            // Початок речення-обгортки: остання межа (кінець тега, «.»/«!»/«?» або 2+ пробіли).
            $introStart = preg_match_all('/(?:[.!?>]|\s{2,})/u', $before, $bounds, PREG_OFFSET_CAPTURE)
                ? $bounds[0][count($bounds[0]) - 1][1] + strlen($bounds[0][count($bounds[0]) - 1][0])
                : 0;

            $intro = Str::squish(html_entity_decode(strip_tags(substr($before, $introStart))));
            $label = trim($intro, " \t\n\r\0\x0B.,;:-–—") ?: Str::squish(strip_tags($anchor[2][0]));

            $links[] = ['url' => $anchor[1][0], 'label' => Str::limit($label, 120)];

            $clean .= substr($before, 0, $introStart);
            $cursor = $wholeOffset + strlen($whole);
        }

        return [$clean.substr($bio, $cursor), $links];
    }

    /**
     * Роль в адміністрації — для групування сторінки /administratsiya.
     * Виводиться з посади (інших даних у БД немає): «заступник» перевіряємо
     * першим, бо в посаді заступника теж є слово «директор».
     */
    public function getAdministrationRoleAttribute(): string
    {
        $position = mb_strtolower((string) $this->position);

        return match (true) {
            str_contains($position, 'заступник') => 'deputy',
            str_contains($position, 'директор') => 'head',
            default => 'unit',
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->localized('full_name') ?? ''));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    protected function translationPrimaryField(): string
    {
        return 'full_name';
    }

    protected function translationSourceFields(): array
    {
        return ['full_name', 'position', 'academic_degree', 'bio'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    protected static function booted(): void
    {
        static::saving(function (Staff $person) {
            if (blank($person->slug) && filled($person->full_name)) {
                $person->slug = static::uniqueSlug($person->full_name, $person->id);
            }
        });
    }

    /** Маршрут публічної сторінки — для перенаправлень при зміні адреси чи видаленні (KeepsPublicUrls). */
    public static function publicRouteName(): string
    {
        return 'staff.show';
    }

    /** Видалений працівник веде на сторінку свого підрозділу або на структуру. */
    public function publicFallbackPath(): string
    {
        $department = $this->department_id ? Department::query()->published()->find($this->department_id) : null;

        return $department ? $department->publicPath() : route('structure.index', [], false);
    }
}
