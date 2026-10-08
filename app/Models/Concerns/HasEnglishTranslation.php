<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait HasEnglishTranslation
{
    public function scopeWithPublishedEnglishTranslation(Builder $query): Builder
    {
        $primary = $this->translationPrimaryField();
        $query->where('translation_published', true)->whereRaw("COALESCE(TRIM({$primary}_en), '') <> ''");
        foreach ($this->translationRequiredFields() as $field) {
            if ($field !== $primary) {
                $query->where(fn (Builder $text) => $text
                    ->whereRaw("COALESCE(TRIM({$field}), '') = ''")
                    ->orWhereRaw("COALESCE(TRIM({$field}_en), '') <> ''"));
            }
        }

        return $query;
    }

    /** Пошук відповідає мові матеріалу, який відвідувач побачить після переходу. */
    public function scopeSearchPublic(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';
        $primary = $this->translationPrimaryField();
        if (app()->getLocale() !== 'en') {
            return $query->where($primary, 'like', $like);
        }

        return $query->where(fn (Builder $matches) => $matches
            ->where(fn (Builder $english) => $english->withPublishedEnglishTranslation()
                ->where(function (Builder $fields) use ($like) {
                    foreach ($this->translationSourceFields() as $field) {
                        if (! str_starts_with($field, 'meta_')) {
                            $fields->orWhere($field.'_en', 'like', $like);
                        }
                    }
                }))
            ->orWhere(fn (Builder $original) => $original
                ->whereNot(fn (Builder $translation) => $translation->withPublishedEnglishTranslation())
                ->where($primary, 'like', $like)));
    }

    public static function bootHasEnglishTranslation(): void
    {
        static::saving(function (self $model): void {
            $fields = array_map(fn ($field) => $field.'_en', $model->translationSourceFields());
            if ($model->translation_published && ($model->isDirty($fields) || $model->isDirty('translation_published'))
                && ! $model->hasCompleteEnglishTranslation()) {
                throw ValidationException::withMessages([
                    'translation_published' => 'Для публікації заповніть англійський заголовок і переклад обов’язкових полів оригіналу.',
                ]);
            }

            // Зміна лише оригіналу не підтверджує актуальність перекладу.
            if ($model->isDirty($fields) || ($model->translation_published && $model->isDirty('translation_published'))) {
                $model->translation_source_hash = $model->hasCompleteEnglishTranslation()
                    ? $model->currentTranslationSourceHash()
                    : null;
            }
        });
    }

    protected function translationSourceFields(): array
    {
        return $this->getTable() === 'pages'
            ? ['title', 'excerpt', 'body', 'meta_title', 'meta_description']
            : ['title', 'excerpt', 'body'];
    }

    protected function translationPrimaryField(): string
    {
        return 'title';
    }

    public function currentTranslationSourceHash(): string
    {
        $source = [];
        foreach ($this->translationSourceFields() as $field) {
            $source[$field] = $this->getAttribute($field);
        }

        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function hasCompleteEnglishTranslation(): bool
    {
        foreach ($this->translationRequiredFields() as $field) {
            if (($field === $this->translationPrimaryField() || filled($this->getAttribute($field))) && blank($this->getAttribute($field.'_en'))) {
                return false;
            }
        }

        return true;
    }

    protected function translationRequiredFields(): array
    {
        return ['title', 'body'];
    }

    public function hasPublishedEnglishTranslation(): bool
    {
        return $this->translation_published && $this->hasCompleteEnglishTranslation();
    }

    public function translationIsStale(): bool
    {
        return filled($this->translation_source_hash)
            && $this->translation_source_hash !== $this->currentTranslationSourceHash();
    }

    /**
     * Чи індексується англійська версія сторінки матеріалу (App\Support\Seo):
     * повний опублікований переклад, оригінал після нього не змінювався.
     */
    public function hasIndexableEnglishTranslation(): bool
    {
        return $this->hasPublishedEnglishTranslation() && ! $this->translationIsStale();
    }

    /** Колонки, потрібні для перевірки перекладу без завантаження всього запису (sitemap). */
    public function translationColumns(): array
    {
        $source = $this->translationSourceFields();

        return array_merge($source, array_map(fn ($field) => $field.'_en', $source), ['translation_published', 'translation_source_hash']);
    }

    /** Переклад обирається для всього матеріалу, без змішування полів двох мов. */
    public function localized(string $field): ?string
    {
        if (! in_array($field, $this->translationSourceFields(), true)) {
            throw new \InvalidArgumentException('Unsupported translation field: '.$field);
        }

        return $this->getAttribute(app()->getLocale() === 'en' && $this->hasPublishedEnglishTranslation()
            ? $field.'_en'
            : $field);
    }

    public function translationStatus(): string
    {
        if (collect($this->translationSourceFields())->every(fn ($field) => blank($this->getAttribute($field.'_en')))) {
            return 'Відсутній';
        }

        if ($this->translationIsStale()) {
            return 'Оригінал змінено';
        }

        return $this->hasPublishedEnglishTranslation() ? 'Опубліковано' : 'Чернетка';
    }
}
