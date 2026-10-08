<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;

class StatItem extends Model
{
    use HasSortOrder;
    use HasEnglishTranslation;

    protected $fillable = ['label_en', 'translation_published', 'label', 'value', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    protected function translationSourceFields(): array
    {
        return ['label'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    protected function translationPrimaryField(): string
    {
        return 'label';
    }
}
