<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;

class QuickLink extends Model
{
    use HasSortOrder;
    use HasEnglishTranslation;

    protected $fillable = ['title_en', 'description_en', 'translation_published',
        'location', 'title', 'description', 'url', 'icon',
        'color', 'open_new_tab', 'nofollow', 'sort_order', 'is_visible',
    ];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean',
            'open_new_tab' => 'boolean',
            'nofollow' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeLocation($query, string $location)
    {
        return $query->where('location', $location);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Значення rel для посилання в підвалі: noopener для нової вкладки,
     * nofollow — лише для зовнішніх адрес із прапорцем (внутрішнім
     * сторінкам сайту вагу не обрізаємо).
     */
    public function rel(): ?string
    {
        $rel = [];
        // null — запис ще не перечитаний з БД, де default колонки true.
        if (($this->nofollow ?? true) && $this->isExternal()) {
            $rel[] = 'nofollow';
        }
        if ($this->open_new_tab) {
            $rel[] = 'noopener';
        }

        return $rel ? implode(' ', $rel) : null;
    }

    /** Абсолютна адреса на інший хост (не поточний і не основний домен сайту). */
    public function isExternal(): bool
    {
        $url = (string) $this->url;
        $host = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $bare = fn (?string $value) => preg_replace('/^www\./', '', strtolower((string) $value));
        $own = array_filter([$bare(request()->getHost()), $bare(config('otfk.seo.primary_host'))]);

        return ! in_array($bare($host), $own, true);
    }

    protected function translationSourceFields(): array
    {
        return ['title', 'description'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
