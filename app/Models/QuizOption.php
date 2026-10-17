<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizOption extends Model
{
    use HasEnglishTranslation;

    protected $fillable = ['label_en', 'translation_published', 'quiz_question_id', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    /** Бали ОПП за цей вибір: один варіант може зараховуватися кільком програмам з різною вагою. */
    public function scores(): HasMany
    {
        return $this->hasMany(QuizOptionScore::class);
    }

    /** @return array<int, int> program_id => бали */
    public function scoreMap(): array
    {
        return $this->scores->mapWithKeys(fn (QuizOptionScore $score) => [(int) $score->program_id => (int) $score->points])->all();
    }

    protected function translationPrimaryField(): string
    {
        return 'label';
    }

    protected function translationSourceFields(): array
    {
        return ['label'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
