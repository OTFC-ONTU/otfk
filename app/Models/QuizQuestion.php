<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasSortOrder;
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

    /**
     * Найбільше балів, яке кожна ОПП може набрати: сума її найкращих варіантів у кожному питанні.
     * Від цього рахується відсоток збігу, тож програма, що трапляється рідше, не програє лише через це.
     *
     * @param  iterable<self>  $questions  з завантаженими options.scores
     * @return array<int, int> program_id => бали
     */
    public static function maxPoints(iterable $questions): array
    {
        $max = [];
        foreach ($questions as $question) {
            $best = [];
            foreach ($question->options as $option) {
                foreach ($option->scoreMap() as $programId => $points) {
                    $best[$programId] = max($best[$programId] ?? 0, $points);
                }
            }
            foreach ($best as $programId => $points) {
                $max[$programId] = ($max[$programId] ?? 0) + $points;
            }
        }

        return $max;
    }

    /** Питання та всі варіанти показуються однією мовою; бали ОПП спільні. */
    public function publicPayload(): array
    {
        $english = app()->getLocale() === 'en' && $this->hasPublishedEnglishTranslation()
            && $this->options->isNotEmpty()
            && $this->options->every(fn (QuizOption $option) => $option->hasPublishedEnglishTranslation());

        return [
            'q' => $english ? $this->question_en : $this->question,
            'options' => $this->options->map(fn (QuizOption $option) => [
                'label' => $english ? $option->label_en : $option->label,
                'scores' => $option->scoreMap(),
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
