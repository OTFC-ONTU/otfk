<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasEnglishTranslation;

    protected $fillable = [
        'document_category_id', 'title', 'file_path', 'external_url',
        'description', 'published_at', 'sort_order', 'is_published',
        'title_en', 'description_en', 'translation_published',
    ];

    protected function casts(): array
    {
        return [
            'translation_published' => 'boolean',
            'published_at' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        return $this->file_path ? asset('storage/'.$this->file_path) : null;
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
