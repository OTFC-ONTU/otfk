<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasEnglishTranslation;

    public const TYPES = [
        'viddilennya' => 'Відділення',
        'tsyklova-komisiya' => 'Циклова комісія',
        'kafedra' => 'Кафедра',
    ];

    protected $fillable = ['title_en', 'description_en', 'translation_published', 'title', 'slug', 'type', 'description', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'translation_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public static function booted(): void
    {
        static::saving(function (Department $d) {
            if (blank($d->slug) && filled($d->title)) {
                $d->slug = Str::slug($d->title);
            }
        });
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
