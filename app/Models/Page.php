<?php

namespace App\Models;

use App\Casts\SafeHtml;
use App\Models\Concerns\FlushesSitemap;
use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use App\Support\AccreditationContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Page extends Model
{
    use FlushesSitemap;
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['cover_image'];

    protected $fillable = [
        'title_en', 'excerpt_en', 'body_en', 'translation_published',
        'meta_title_en', 'meta_description_en',
        'parent_id', 'title', 'slug', 'excerpt', 'body', 'cover_image',
        'section', 'is_published', 'is_heritage', 'is_featured', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'translation_published' => 'boolean',
            'is_heritage' => 'boolean',
            'is_featured' => 'boolean',
            'body' => SafeHtml::class,
            'body_en' => SafeHtml::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    /** Ключові сторінки розділу — виносяться на хабі окремими картками. */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Заголовки h2-h4 з тіла сторінки — для блоку «Навігація по сторінці».
     *
     * @return list<array{id: string, text: string, level: int}>
     */
    public function headings(): array
    {
        $headings = [];

        preg_match_all('/<h([2-4])\\b[^>]*>(.*?)<\\/h\\1>/is', (string) $this->localized('body'), $matches, PREG_SET_ORDER);

        foreach ($matches as $i => $match) {
            $text = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($text === '') {
                continue;
            }

            $headings[] = [
                'id' => $this->headingId($text, $i),
                'text' => $text,
                'level' => (int) $match[1],
            ];
        }

        return $headings;
    }

    /** Адаптація старого імпорту лише при публічному виводі; редактор і БД бачать оригінал. */
    public function publicBody(): string
    {
        $body = (string) $this->localized('body');

        return $this->slug === 'litsenzuvannya-ta-akredytatsiya'
            ? AccreditationContent::restore($body) : $body;
    }

    /** Те саме тіло сторінки, але із проставленими id на заголовках — щоб якорі працювали. */
    public function bodyWithAnchors(): string
    {
        $i = 0;

        return (string) preg_replace_callback(
            '/<h([2-4])\\b([^>]*)>(.*?)<\\/h\\1>/is',
            function (array $m) use (&$i) {
                $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($text === '') {
                    return $m[0];
                }

                // id з імпортованого HTML не чіпаємо — інакше зламаються наявні посилання
                if (str_contains($m[2], 'id=')) {
                    $i++;

                    return $m[0];
                }

                return '<h'.$m[1].$m[2].' id="'.$this->headingId($text, $i++).'">'.$m[3].'</h'.$m[1].'>';
            },
            $this->publicBody()
        );
    }

    /** Стійкий якір: транслітерований заголовок, індекс — щоб однакові назви не збігалися. */
    private function headingId(string $text, int $index): string
    {
        $slug = Str::slug(Str::limit($text, 60, ''));

        return 'rozdil-'.($index + 1).($slug !== '' ? '-'.$slug : '');
    }

    public static function booted(): void
    {
        static::saving(function (Page $page) {
            if (blank($page->slug) && filled($page->title)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}
