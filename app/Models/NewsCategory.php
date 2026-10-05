<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsCategory extends Model
{
    use HasEnglishTranslation;

    protected $fillable = ['title', 'slug', 'sort_order', 'is_heritage', 'title_en', 'translation_published'];

    protected function casts(): array
    {
        return [
            'is_heritage' => 'boolean',
            'translation_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }

    public static function booted(): void
    {
        static::saving(function (NewsCategory $category) {
            if (blank($category->slug) && filled($category->title)) {
                $category->slug = Str::slug($category->title);
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
