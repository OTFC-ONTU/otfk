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
        'color', 'open_new_tab', 'sort_order', 'is_visible',
    ];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean',
            'open_new_tab' => 'boolean',
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

    protected function translationSourceFields(): array
    {
        return ['title', 'description'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
