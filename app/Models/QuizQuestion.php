<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasEnglishTranslation;

    protected $fillable = ['question_en', 'translation_published', 'question', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'translation_published' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Питання та всі варіанти показуються однією мовою; бали й прив’язки спільні. */
    public function publicPayload(): array
    {
        $english = app()->getLocale() === 'en' && $this->hasPublishedEnglishTranslation()
            && $this->options->isNotEmpty()
            && $this->options->every(fn (QuizOption $option) => $option->hasPublishedEnglishTranslation());

        return [
            'q' => $english ? $this->question_en : $this->question,
            'options' => $this->options->map(fn (QuizOption $option) => [
                'label' => $english ? $option->label_en : $option->label,
                'sid' => $option->specialty_id,
                'pts' => (int) $option->points,
            ])->values()->all(),
        ];
    }

    protected function translationPrimaryField(): string
    {
        return 'question';
    }

    protected function translationSourceFields(): array
    {
        return ['question'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
