<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gallery extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['cover_image'];

    protected $table = 'galleries';

    protected $fillable = [
        'title', 'slug', 'description', 'cover_image', 'published_at', 'sort_order', 'is_published', 'is_archive',
        'title_en', 'description_en', 'translation_published',
    ];

    protected function casts(): array
    {
        return [
            'translation_published' => 'boolean',
            'published_at' => 'date',
            'is_published' => 'boolean',
            'is_archive' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('published_at');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_image) {
            return asset('storage/'.$this->cover_image);
        }

        $first = $this->photos->first() ?? $this->photos()->first();

        return $first ? asset('storage/'.$first->image) : null;
    }

    protected function translationSourceFields(): array
    {
        return ['title', 'description'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    /** Альбом і всі непорожні підписи показуються однією мовою. */
    public function hasEnglishAlbum(): bool
    {
        return app()->getLocale() === 'en' && $this->hasPublishedEnglishTranslation()
            && $this->photos->every(fn (Photo $photo) => blank($photo->caption) || $photo->hasPublishedEnglishTranslation());
    }

    public function albumLocalized(string $field): ?string
    {
        if (! in_array($field, $this->translationSourceFields(), true)) {
            throw new \InvalidArgumentException('Unsupported translation field: '.$field);
        }

        return $this->getAttribute($this->hasEnglishAlbum() ? $field.'_en' : $field);
    }

    public function publicCaption(Photo $photo): ?string
    {
        return $this->hasEnglishAlbum() && filled($photo->caption) ? $photo->caption_en : $photo->caption;
    }
}
