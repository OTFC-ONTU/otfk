<?php

namespace App\Models\Concerns;

use App\Support\PublicUrlRedirects;

/**
 * Опублікований матеріал не залишає по собі 404: зміна slug створює 301 зі старої адреси на нову,
 * видалення — 301 на батьківський розділ або список (PublicUrlRedirects, карта legacy_redirects,
 * обидві мови). Чернетки й ще не опубліковані матеріали адрес не мали — для них нічого не робиться.
 *
 * Модель задає маршрут публічної сторінки (publicRouteName()) і за потреби — адресу, куди вести
 * після видалення (publicFallbackPath()) та умову «був опублікований» (wasPublic()).
 */
trait KeepsPublicUrls
{
    abstract public static function publicRouteName(): string;

    public static function bootKeepsPublicUrls(): void
    {
        static::updated(function (self $model): void {
            $old = (string) $model->getOriginal('slug');
            if ($model->wasChanged('slug') && $old !== '' && filled($model->slug) && $model->wasPublic()) {
                PublicUrlRedirects::moved($model->publicPath($old), $model->publicPath($model->slug), 'Автоматично: змінено адресу ('.class_basename($model).' #'.$model->getKey().')');
            }
        });

        static::deleted(function (self $model): void {
            $slug = (string) $model->getOriginal('slug');
            if ($slug !== '' && $model->wasPublic()) {
                PublicUrlRedirects::removed($model->publicPath($slug), $model->publicFallbackPath(), 'Автоматично: видалено «'.mb_substr((string) ($model->title ?? $model->full_name ?? ''), 0, 120).'» ('.class_basename($model).' #'.$model->getKey().')');
            }
        });
    }

    /** Відносна адреса публічної сторінки матеріалу (без мови). */
    public function publicPath(?string $slug = null): string
    {
        return route(static::publicRouteName(), $slug ?? $this->slug, false);
    }

    /** Куди вести адресу видаленого матеріалу. */
    public function publicFallbackPath(): string
    {
        return '/';
    }

    /** Матеріал був доступний на сайті до цієї зміни (за початковими значеннями). */
    public function wasPublic(): bool
    {
        return (bool) $this->getOriginal('is_published');
    }
}
