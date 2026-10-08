<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    use HasSortOrder;
    use HasEnglishTranslation;

    protected $fillable = [
        'title_en', 'description_en', 'translation_published',
        'specialty_id', 'title', 'file_path', 'external_url', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
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
