<?php

namespace App\Models;

use App\Models\Concerns\HasEnglishTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasEnglishTranslation {
        hasCompleteEnglishTranslation as private hasCompleteValueTranslation;
    }

    public const TRANSLATABLE_KEYS = [
        'brand_short', 'brand_name', 'footer_about', 'site_description',
        'contact_address', 'work_hours', 'announcement_text', 'site_version_label',
    ];

    private const ENGLISH_DEFAULTS = [
        'brand_short' => 'layout.brand_short',
        'brand_name' => 'layout.brand_name',
        'footer_about' => 'layout.about',
        'site_description' => 'layout.description',
    ];

    protected $fillable = ['key', 'value', 'group', 'type', 'value_en', 'translation_published'];

    public $timestamps = true;

    protected function casts(): array
    {
        return ['translation_published' => 'boolean'];
    }

    /**
     * Усі налаштування як масив key => value (кешується на запит).
     */
    public static function map(): array
    {
        return Cache::remember('settings.map', 600, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::map()[$key] ?? $default;
    }

    public static function supportsTranslation(?string $key, ?string $type): bool
    {
        return in_array($key, self::TRANSLATABLE_KEYS, true) && in_array($type, ['text', 'textarea'], true);
    }

    /** Спільний кеш містить оригінали; мова обирається лише під час публічного виводу. */
    public static function publicMap(): array
    {
        $values = static::map();
        if (app()->getLocale() !== 'en') {
            return $values;
        }

        $translations = Cache::remember('settings.translations', 600, fn () => static::query()
            ->whereIn('key', self::TRANSLATABLE_KEYS)->get()->keyBy('key'));
        foreach ($translations as $key => $setting) {
            // Порожній оригінал приховує оголошення/позначку і після перекладу.
            if (blank($values[$key] ?? null) || ! static::supportsTranslation($key, $setting->type)) {
                continue;
            }
            $values[$key] = $setting->hasPublishedEnglishTranslation()
                ? $setting->value_en
                : (isset(self::ENGLISH_DEFAULTS[$key]) ? __(self::ENGLISH_DEFAULTS[$key]) : $setting->value);
        }

        return $values;
    }

    public static function publicGet(string $key, ?string $default = null): ?string
    {
        return static::publicMap()[$key] ?? $default;
    }

    public function hasCompleteEnglishTranslation(): bool
    {
        return static::supportsTranslation($this->key, $this->type) && $this->hasCompleteValueTranslation();
    }

    protected function translationPrimaryField(): string
    {
        return 'value';
    }

    protected function translationSourceFields(): array
    {
        return ['value'];
    }

    protected function translationRequiredFields(): array
    {
        return $this->translationSourceFields();
    }

    public function currentTranslationSourceHash(): string
    {
        return hash('sha256', json_encode($this->only(['key', 'type', 'value']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected static function booted(): void
    {
        $forget = function (): void {
            Cache::forget('settings.map');
            Cache::forget('settings.translations');
        };
        static::saved($forget);
        static::deleted($forget);
    }
}
