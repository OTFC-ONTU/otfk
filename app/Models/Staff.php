<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['photo'];

    protected $table = 'staff';

    public const CATEGORIES = [
        'administration' => 'Адміністрація',
        'teacher' => 'Викладач',
    ];

    protected $fillable = ['full_name_en', 'position_en', 'academic_degree_en', 'bio_en', 'translation_published',
        'full_name', 'position', 'category', 'department_id', 'photo',
        'email', 'phone', 'bio', 'academic_degree', 'sort_order', 'is_published', 'profile_page_id', 'qualification_page_id',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'translation_published' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Сторінка «Результати професійної та наукової діяльності» викладача. */
    public function profilePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'profile_page_id');
    }

    /** Сторінка «Відомості про підвищення кваліфікації» викладача. */
    public function qualificationPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'qualification_page_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('full_name');
    }

    public function scopeAdministration($query)
    {
        return $query->where('category', 'administration');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->localized('full_name') ?? ''));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    protected function translationPrimaryField(): string
    {
        return 'full_name';
    }

    protected function translationSourceFields(): array
    {
        return ['full_name', 'position', 'academic_degree', 'bio'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
