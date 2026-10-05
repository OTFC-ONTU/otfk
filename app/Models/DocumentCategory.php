<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentCategory extends Model
{
    use HasEnglishTranslation;

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    protected $fillable = ['title_en', 'translation_published', 'title', 'slug', 'sort_order'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('sort_order')->orderByDesc('published_at');
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
