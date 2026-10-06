<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Редизайн галереї: світла шапка, рекомендований альбом, мозаїчна сітка з лайтбоксом, «Інші альбоми». */
class GalleryPageTest extends TestCase
{
    use RefreshDatabase;

    /** Демо-альбоми з сидера прибираємо — сторінки перевіряємо на власному наборі. */
    protected function setUp(): void
    {
        parent::setUp();

        Photo::query()->delete();
        Gallery::query()->delete();
    }

    private function makeGallery(string $title, string $slug, int $photos = 0, int $sort = 0, bool $archive = false): Gallery
    {
        $gallery = Gallery::create([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Опис альбому ' . $title,
            'published_at' => now()->subDays($sort + 1),
            'sort_order' => $sort,
            'is_published' => true,
            'is_archive' => $archive,
        ]);

        for ($i = 0; $i < $photos; $i++) {
            Photo::create([
                'gallery_id' => $gallery->id,
                'image' => "galleries/{$slug}-{$i}.jpg",
                'caption' => "Підпис {$i}",
                'sort_order' => $i,
            ]);
        }

        return $gallery;
    }

    public function test_index_has_light_header_with_counter_and_featured_album(): void
    {
        $this->makeGallery('Урочистості до річниці', 'urochystosti', 3, 0);
        $this->makeGallery('Студентське життя', 'studentske-zhyttya', 2, 1);

        $this->get('/halereya')
            ->assertOk()
            // Світла шапка розділу замість navy-героя
            ->assertSee('bg-slate-50/80', false)
            ->assertSee('text-brand-950 sm:text-4xl lg:text-[2.75rem]">Фотогалерея</h1>', false)
            // Лічильник з українським відмінюванням і рекомендований альбом
            ->assertSee('2 альбоми')
            ->assertSee('Рекомендований альбом')
            ->assertSee('Переглянути альбом')
            ->assertSee('Усі альбоми')
            ->assertSee('Студентське життя');
    }

    /** Лічильник відмінюється: 1 альбом / 2–4 альбоми / 5+ альбомів. */
    public function test_index_counter_uses_ukrainian_plural_forms(): void
    {
        $this->makeGallery('Єдиний альбом', 'yedynyy', 1, 0);
        $this->get('/halereya')->assertOk()->assertSee('1 альбом');

        for ($i = 2; $i <= 5; $i++) {
            $this->makeGallery("Альбом {$i}", "albom-{$i}", 1, $i);
        }

        $this->get('/halereya')->assertOk()->assertSee('5 альбомів');
    }

    public function test_single_album_has_no_featured_block(): void
    {
        $this->makeGallery('Єдиний альбом', 'yedynyy', 2, 0);

        $this->get('/halereya')
            ->assertOk()
            ->assertSee('Єдиний альбом')
            ->assertDontSee('Рекомендований альбом');
    }

    public function test_index_is_paginated(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->makeGallery("Альбом {$i}", "albom-{$i}", 1, $i);
        }

        $this->get('/halereya')
            ->assertOk()
            ->assertSee('14 альбомів')
            ->assertSee('?page=2', false)
            ->assertDontSee('Альбом 14');

        // Головний альбом — лише на першій сторінці
        $this->get('/halereya?page=2')
            ->assertOk()
            ->assertSee('Альбом 14')
            ->assertDontSee('Рекомендований альбом');
    }

    public function test_index_empty_state(): void
    {
        $this->makeGallery('Чернетка', 'chernetka', 1, 0)->update(['is_published' => false]);

        $this->get('/halereya')
            ->assertOk()
            ->assertSee('Фотоальбоми незабаром буде додано.')
            ->assertDontSee('Чернетка');
    }

    public function test_show_has_light_header_and_lightbox_with_all_photos(): void
    {
        $gallery = $this->makeGallery('День відкритих дверей', 'den-vidkrytyh-dverey', 3, 0);

        $this->get(route('galleries.show', $gallery))
            ->assertOk()
            ->assertSee('bg-slate-50/80', false)
            ->assertSee('3 фото')
            ->assertSee('Почати перегляд')
            // Лайтбокс: усі фото в одному масиві, гортання стрілками
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Наступне фото', false)
            ->assertSee('Попереднє фото', false)
            ->assertSee('den-vidkrytyh-dverey-2.jpg', false)
            ->assertSee('Підпис 2');
    }

    public function test_show_lists_other_albums(): void
    {
        $gallery = $this->makeGallery('Поточний альбом', 'potochnyy', 2, 0);
        $this->makeGallery('Сусідній альбом', 'susidniy', 1, 1);

        $this->get(route('galleries.show', $gallery))
            ->assertOk()
            ->assertSee('Інші альбоми')
            ->assertSee('Сусідній альбом')
            ->assertSee('Повернутися до галереї');

        // Сам альбом до списку сусідів не потрапляє: назва лишається тільки в шапці й крихтах
        $others = $this->get(route('galleries.show', $gallery))->viewData('others');
        $this->assertSame(['Сусідній альбом'], $others->pluck('title')->all());
    }

    public function test_show_without_neighbours_still_has_back_link(): void
    {
        $gallery = $this->makeGallery('Єдиний альбом', 'yedynyy', 1, 0);

        $this->get(route('galleries.show', $gallery))
            ->assertOk()
            ->assertDontSee('Інші альбоми')
            ->assertSee('Повернутися до галереї');
    }

    public function test_show_empty_album_hides_viewer_controls(): void
    {
        $gallery = $this->makeGallery('Порожній альбом', 'porozhniy', 0, 0);

        $this->get(route('galleries.show', $gallery))
            ->assertOk()
            ->assertSee('Фотографій у цьому альбомі ще немає.')
            ->assertDontSee('Почати перегляд');
    }
}
