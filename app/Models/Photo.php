<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['image'];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    protected $fillable = ['caption_en', 'translation_published', 'gallery_id', 'image', 'caption', 'sort_order'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->image);
    }

    protected function translationSourceFields(): array
    {
        return ['caption'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    protected function translationPrimaryField(): string
    {
        return 'caption';
    }
}
