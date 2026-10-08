<?php

namespace App\Models;

use App\Models\Concerns\FlushesSitemap;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentCategory extends Model
{
    use FlushesSitemap;
    use HasEnglishTranslation;

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    protected $fillable = ['title_en', 'translation_published', 'title', 'slug', 'sort_order', 'page_id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('sort_order')->orderByDesc('published_at');
    }

    /** CMS-сторінка з повним вмістом розділу (як на оригіналі). */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** Опублікована сторінка розділу з непорожнім текстом або null — тоді розділ показує список документів. */
    public function sectionPage(): ?Page
    {
        $page = $this->page;

        return $page && $page->is_published && filled($page->publicBody()) ? $page : null;
    }

    /**
     * `/en/dokumenty/{slug}` показує й текст прив'язаної CMS-сторінки: індексується
     * лише тоді, коли перекладено (повністю й актуально) і назву, і сторінку розділу.
     */
    public function hasIndexableEnglishTranslation(): bool
    {
        $section = $this->sectionPage();

        return $this->hasPublishedEnglishTranslation() && ! $this->translationIsStale()
            && ($section === null || $section->hasIndexableEnglishTranslation());
    }

    /** Для sitemap: крім полів перекладу потрібна прив'язка до сторінки розділу. */
    public function translationColumns(): array
    {
        $source = $this->translationSourceFields();

        return array_merge($source, array_map(fn ($field) => $field.'_en', $source), ['translation_published', 'translation_source_hash', 'page_id']);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public static function booted(): void
    {
        static::saving(function (DocumentCategory $c) {
            if (blank($c->slug) && filled($c->title)) {
                $c->slug = Str::slug($c->title);
            }
        });
    }

    protected function translationSourceFields(): array
    {
        return ['title'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
