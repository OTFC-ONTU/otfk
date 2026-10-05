<?php

namespace App\Models;

use App\Support\LocalizedUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class MenuItem extends Model
{
    protected $fillable = [
        'parent_id', 'label', 'label_en', 'link_type', 'page_id', 'url',
        'open_new_tab', 'sort_order', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'open_new_tab' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->visible()->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    /** Кешоване дерево меню для шапки сайту. */
    public static function navigation(): Collection
    {
        return Cache::remember('menu.navigation', 600, fn () => static::roots()->visible()
            ->with([
                'children' => fn ($q) => $q->visible()->orderBy('sort_order'),
                'page',
                'children.page',
            ])
            ->get());
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('menu.navigation'));
        static::deleted(fn () => Cache::forget('menu.navigation'));
    }

    /** Переклад обчислюється після читання спільного кешу меню. */
    public function getLocalizedLabelAttribute(): string
    {
        if (app()->getLocale() !== 'en') {
            return $this->label;
        }

        if (filled($this->label_en)) {
            return $this->label_en;
        }

        $labels = trans('navigation.labels');

        return is_array($labels) ? ($labels[$this->label] ?? $this->label) : $this->label;
    }

    /**
     * Обчислене посилання пункту меню.
     */
    public function getHrefAttribute(): string
    {
        $href = match ($this->link_type) {
            'url' => $this->url ?: '#',
            'route' => $this->url && Route::has($this->url) ? route($this->url) : '#',
            default => $this->page ? url('/'.$this->page->slug) : ($this->url ?: '#'),
        };

        return LocalizedUrl::to($href);
    }
}
