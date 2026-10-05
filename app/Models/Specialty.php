<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Specialty extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['cover_image'];

    protected $fillable = [
        'title_en', 'short_description_en', 'description_en', 'degree_en', 'study_form_en', 'duration_en', 'translation_published',
        'title', 'slug', 'code', 'short_description', 'description',
        'degree', 'study_form', 'duration', 'cover_image', 'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'translation_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->orderBy('sort_order');
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
        static::saving(function (Specialty $s) {
            if (blank($s->slug) && filled($s->title)) {
                $s->slug = Str::slug($s->title);
            }
        });
    }

    protected function translationSourceFields(): array
    {
        return ['title', 'short_description', 'description', 'degree', 'study_form', 'duration'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
