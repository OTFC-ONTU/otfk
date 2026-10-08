<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_banners_page_renders_overlay_control(): void
    {
        $admin = \App\Models\User::firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/banners')
            ->assertOk()
            ->assertSee('Затемнення фото')
            ->assertSee('Сила затемнення');
    }

    public function test_banner_overlay_strength_follows_setting(): void
    {
        Banner::query()->delete();

        Banner::create([
            'title' => 'Тестовий банер',
            'image' => 'banners/test.jpg',
            'is_published' => true,
        ]);

        Setting::updateOrCreate(
            ['key' => 'banner_overlay_opacity'],
            ['value' => '0', 'group' => 'appearance', 'type' => 'number'],
        );
        cache()->forget('settings.map');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('linear-gradient(to right, rgba(22, 34, 63', escape: false);

        Setting::updateOrCreate(
            ['key' => 'banner_overlay_opacity'],
            ['value' => '50', 'group' => 'appearance', 'type' => 'number'],
        );
        cache()->forget('settings.map');

        $this->get('/')
            ->assertOk()
            ->assertSee('rgba(22, 34, 63, 0.48)', escape: false);
    }

    public function test_many_banners_use_slide_counter_on_phones_so_arrows_fit(): void
    {
        Banner::query()->delete();

        foreach (range(1, 3) as $i) {
            Banner::create(['title' => "Банер {$i}", 'image' => "banners/{$i}.jpg", 'is_published' => true]);
        }

        // До 7 слайдів точки вміщуються поруч зі стрілками навіть на 375px
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="'.__('public.next_slide').'"', escape: false)
            ->assertDontSee('x-text="index + 1"', escape: false)
            ->assertDontSee('max-sm:hidden', escape: false);

        foreach (range(4, 12) as $i) {
            Banner::create(['title' => "Банер {$i}", 'image' => "banners/{$i}.jpg", 'is_published' => true]);
        }

        // 12 точок ширші за телефон і виштовхують стрілки — на телефоні лічильник замість точок
        $this->get('/')
            ->assertOk()
            ->assertSee('<span x-text="index + 1">1</span> / 12', escape: false)
            ->assertSee('class="flex max-sm:hidden" role="tablist"', escape: false)
            ->assertSee('aria-label="'.__('public.previous_slide').'"', escape: false)
            ->assertSee('aria-label="'.__('public.next_slide').'"', escape: false);
    }

    public function test_photo_only_slide_has_no_floating_buttons_on_phones(): void
    {
        Banner::query()->delete();

        Banner::create(['title' => 'Вступ', 'subtitle' => 'Підпис', 'image' => 'banners/1.jpg', 'link_url' => '/pro-koledzh', 'link_label' => 'Про коледж', 'is_published' => true]);
        Banner::create(['image' => 'banners/2.jpg', 'is_published' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        // Слайд із текстом: дві кнопки поруч у сітці, затемнення на телефоні — знизу вгору
        $this->assertStringContainsString('grid mt-7 grid-cols-2', $html);
        $this->assertStringContainsString('linear-gradient(to top, rgba(22, 34, 63, 0.71)', $html);
        // Фото-слайд без тексту й посилання: блок кнопок на телефоні прихований, фото лише з легкою тінню знизу
        $this->assertStringContainsString('hidden mt-7 grid-cols-2', $html);
        $this->assertStringContainsString('linear-gradient(to top, rgba(22, 34, 63, 0.75) 0%, rgba(22, 34, 63, 0.41)', $html);
        // Десктопне затемнення зліва направо лишається
        $this->assertStringContainsString('linear-gradient(to right, rgba(22, 34, 63', $html);
    }
}
