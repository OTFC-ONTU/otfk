<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use App\Models\Concerns\OptimizesUploadedImages;
use App\Observers\NewsObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[ObservedBy(NewsObserver::class)]
class News extends Model
{
    use HasEnglishTranslation;
    use OptimizesUploadedImages;

    /** @var list<string> */
    protected static array $optimizedImages = ['cover_image'];

    protected $table = 'news';

    protected $fillable = [
        'title_en', 'excerpt_en', 'body_en', 'translation_published',
        'category_id', 'title', 'slug', 'excerpt', 'body', 'cover_image',
        'published_at', 'is_published', 'is_featured', 'is_heritage', 'views', 'likes',
        'telegram_posted_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
            'translation_published' => 'boolean',
            'is_featured' => 'boolean',
            'is_heritage' => 'boolean',
            'telegram_posted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    /** Heritage з прапорця новини або з категорії (наприклад «Архів», «Історія»). */
    public function usesHeritagePresentation(): bool
    {
        if ($this->is_heritage) {
            return true;
        }

        return (bool) $this->category?->is_heritage;
    }

    /** Приблизний час читання статті у хвилинах (≈150 слів/хв). */
    public function getReadingMinutesAttribute(): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags((string) $this->body)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return max(1, (int) ceil(count($words) / 150));
    }

    public function likeRecords()
    {
        return $this->hasMany(NewsLike::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public static function booted(): void
    {
        static::saving(function (News $news) {
            if (blank($news->slug) && filled($news->title)) {
                $news->slug = Str::slug($news->title);
            }
        });

        // Автопостинг у Telegram живе в NewsObserver (винесено з циклу збереження).
    }
}
