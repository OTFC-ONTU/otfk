<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['photo'];

    protected $fillable = ['name_en', 'role_en', 'quote_en', 'translation_published', 'name', 'role', 'quote', 'photo', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Ініціали для аватара-заглушки (коли фото не завантажене). */
    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim((string) $this->localized('name'))))
            ->filter()
            ->take(2)
            ->map(fn ($w) => mb_substr($w, 0, 1))
            ->implode('');
    }

    protected function translationSourceFields(): array
    {
        return ['name', 'role', 'quote'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    protected function translationPrimaryField(): string
    {
        return 'name';
    }
}
