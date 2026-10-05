<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['cover_image'];

    protected $fillable = [
        'title_en', 'excerpt_en', 'body_en', 'translation_published',
        'meta_title_en', 'meta_description_en',
        'parent_id', 'title', 'slug', 'excerpt', 'body', 'cover_image',
        'section', 'is_published', 'is_heritage', 'sort_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'translation_published' => 'boolean',
            'is_heritage' => 'boolean',
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

    public static function booted(): void
    {
        static::saving(function (Page $page) {
            if (blank($page->slug) && filled($page->title)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }
}
