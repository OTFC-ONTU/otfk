<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizOption extends Model
{
    use HasEnglishTranslation;

    protected $fillable = ['label_en', 'translation_published', 'quiz_question_id', 'label', 'specialty_id', 'points', 'sort_order'];

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
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
