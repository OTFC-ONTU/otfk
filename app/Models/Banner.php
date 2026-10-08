<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasSortOrder;
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['image'];

    protected $fillable = ['title_en', 'subtitle_en', 'image_alt_en', 'link_label_en', 'translation_published',
        'title', 'subtitle', 'image', 'image_alt', 'link_url', 'link_label',
        'starts_at', 'ends_at', 'sort_order', 'is_published',
    ];

    public function imageAlt(): string
    {
        return filled($this->localized('image_alt'))
            ? $this->localized('image_alt')
            : (filled($this->localized('title')) ? $this->localized('title') : __('public.banner'));
    }

    protected function casts(): array
    {
        return ['translation_published' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        $today = now()->startOfDay();

        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today));
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    protected function translationSourceFields(): array
    {
        return ['title', 'subtitle', 'image_alt', 'link_label'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    public function hasCompleteEnglishTranslation(): bool
    {
        return collect($this->translationSourceFields())->contains(fn ($field) => filled($this->getAttribute($field.'_en')))
            && collect($this->translationSourceFields())->every(fn ($field) => blank($this->getAttribute($field)) || filled($this->getAttribute($field.'_en')));
    }

    public function scopeWithPublishedEnglishTranslation(Builder $query): Builder
    {
        $query->where('translation_published', true)->where(function (Builder $english) {
            foreach ($this->translationSourceFields() as $field) {
                $english->orWhereRaw("COALESCE(TRIM({$field}_en), '') <> ''");
            }
        });
        foreach ($this->translationSourceFields() as $field) {
            $query->where(fn (Builder $text) => $text->whereRaw("COALESCE(TRIM({$field}), '') = ''")
                ->orWhereRaw("COALESCE(TRIM({$field}_en), '') <> ''"));
        }

        return $query;
    }

    /** Новий запис — першим у списку, як раніше при сортуванні за датою (HasSortOrder). */
    public static function sortNewRecordsFirst(): bool
    {
        return true;
    }
}
