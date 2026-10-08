<?php

namespace App\Models;

use App\Models\Concerns\KeepsPublicUrls;
use App\Models\Concerns\HasSortOrder;
use App\Casts\SafeHtml;
use App\Models\Concerns\FlushesSitemap;
use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Specialty extends Model
{
    use KeepsPublicUrls;
    use HasSortOrder;
    use FlushesSitemap;
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
        return ['is_published' => 'boolean', 'translation_published' => 'boolean', 'description' => SafeHtml::class, 'description_en' => SafeHtml::class];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class)->orderBy('sort_order');
    }

    /**
     * Декоративна іконка напряму підготовки — обкладинок у спеціальностей немає,
     * тож картки тримаються на коді та іконці. Спершу точний код (нові літерні
     * та старі числові), далі галузь знань (літера або перші дві цифри),
     * інакше — загальна «академічна шапочка».
     */
    public function getIconNameAttribute(): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->code));

        return match ($code) {
            'F2', '121' => 'code-bracket',
            'F7', '123' => 'cpu-chip',
            'G13', '181' => 'beaker',
            '071' => 'calculator',
            'D2' => 'banknotes',
            'D3' => 'briefcase',
            'D5' => 'megaphone',
            'D7' => 'scale',
            'G4' => 'wrench-screwdriver',
            'G15' => 'scissors',
            default => match (true) {
                str_starts_with($code, 'F'), str_starts_with($code, '12') => 'computer-desktop',
                str_starts_with($code, 'G'), str_starts_with($code, '18') => 'wrench-screwdriver',
                str_starts_with($code, 'D'), str_starts_with($code, '07') => 'chart-bar',
                default => 'academic-cap',
            },
        };
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

    /** Маршрут публічної сторінки — для перенаправлень при зміні адреси чи видаленні (KeepsPublicUrls). */
    public static function publicRouteName(): string
    {
        return 'specialties.show';
    }

    public function publicFallbackPath(): string
    {
        return route('specialties.index', [], false);
    }
}
