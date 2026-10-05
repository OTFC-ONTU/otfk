<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasEnglishTranslation;

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at', 'url', 'is_published',
        'title_en', 'description_en', 'location_en', 'translation_published',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'translation_published' => 'boolean',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** Майбутні події (включно з тими, що тривають сьогодні). */
    public function scopeUpcoming($query)
    {
        return $query->where(function ($q) {
            $q->where('starts_at', '>=', now()->startOfDay())
                ->orWhere(fn ($qq) => $qq->whereNotNull('ends_at')->where('ends_at', '>=', now()));
        })->orderBy('starts_at');
    }

    public function scopePast($query)
    {
        return $query->where('starts_at', '<', now()->startOfDay())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '<', now()))
            ->orderByDesc('starts_at');
    }

    /** Початок/кінець в UTC для календарів (час у БД — київський настінний). */
    public function utcStart(): Carbon
    {
        return $this->starts_at->copy()->shiftTimezone('Europe/Kyiv')->utc();
    }

    public function utcEnd(): Carbon
    {
        return ($this->ends_at ?? $this->starts_at->copy()->addHour())
            ->copy()->shiftTimezone('Europe/Kyiv')->utc();
    }

    /** Посилання «Додати в Google Календар». */
    public function getGoogleCalendarUrlAttribute(): string
    {
        $fmt = fn ($c) => $c->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->localized('title'),
            'dates' => $fmt($this->utcStart()).'/'.$fmt($this->utcEnd()),
            'details' => (string) $this->localized('description'),
            'location' => (string) $this->localized('location'),
        ]);
    }

    protected function translationSourceFields(): array
    {
        return ['title', 'description', 'location'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }
}
