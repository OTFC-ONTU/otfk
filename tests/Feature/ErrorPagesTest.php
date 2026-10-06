<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Редизайн сторінок помилок: 404/403 — у макеті сайту зі світлою шапкою-карткою,
 * 500/503 — автономні (без layout і зібраних ассетів), але в тих самих кольорах.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_uses_light_header_card_and_live_search(): void
    {
        $this->get('/takoyi-storinky-tochno-nemaye')
            ->assertNotFound()
            ->assertSee('border-b border-slate-200/70 bg-slate-50/80', false)
            ->assertSee('Помилка 404')
            ->assertSee('Сторінку не знайдено')
            // Контракт живого пошуку зберігаємо
            ->assertSee('Що ви шукали?')
            ->assertSee('Розклад дзвінків');
    }

    public function test_404_offers_sections_from_menu(): void
    {
        MenuItem::create([
            'label' => 'Абітурієнту',
            'link_type' => 'url',
            'url' => '/abituriyentu',
            'sort_order' => 0,
            'is_visible' => true,
        ]);
        cache()->forget('menu.navigation');

        $this->get('/takoyi-storinky-tochno-nemaye')
            ->assertNotFound()
            ->assertSee('Популярні розділи')
            ->assertSee('Абітурієнту');
    }

    public function test_403_matches_404_style(): void
    {
        $this->view('errors.403', ['exception' => null])
            ->assertSee('Помилка 403')
            ->assertSee('Доступ заборонено')
            ->assertSee('accent-rule', false);
    }

    /** 500/503 мають рендеритись без layout — інакше зламаний застосунок не покаже нічого. */
    public function test_standalone_error_pages_do_not_depend_on_layout(): void
    {
        foreach (['errors.500' => '500', 'errors.503' => '503'] as $view => $code) {
            $html = (string) view($view, ['exception' => null])->render();

            $this->assertStringContainsString($code, $html);
            $this->assertStringContainsString('<!DOCTYPE html>', $html);
            // Ні зібраних ассетів, ні шапки сайту
            $this->assertStringNotContainsString('build/assets', $html);
            $this->assertStringNotContainsString('container-site', $html);
            // Фірмові кольори лишаються
            $this->assertStringContainsString('#16223f', $html);
            $this->assertStringContainsString('#d98e1e', $html);
        }
    }
}
