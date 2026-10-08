<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasSortOrder;
    use HasEnglishTranslation;

    protected $fillable = ['question', 'answer', 'sort_order', 'is_active', 'question_en', 'answer_en', 'translation_published'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'translation_published' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    protected function translationPrimaryField(): string
    {
        return 'question';
    }

    protected function translationSourceFields(): array
    {
        return ['question', 'answer'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
