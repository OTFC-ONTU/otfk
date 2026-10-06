<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Підвал сайту: офіційні посилання з оригінального otfk.od.ua
 * (заведені міграцією 2026_08_28_183000) і налаштування footer_about.
 */
class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_shows_official_partner_links_from_original_site(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('https://ontu.edu.ua', escape: false)
            ->assertSee('https://mon.gov.ua', escape: false)
            ->assertSee('https://nmc-vfpo.com', escape: false)
            ->assertSee('https://organic-platform.org', escape: false)
            ->assertSee('https://ukc.gov.ua', escape: false)
            ->assertSee('НМЦ ВФПО')
            ->assertSee('Органічна платформа знань')
            ->assertSee('Урядова «гаряча лінія» 1545');
    }

    public function test_footer_about_setting_is_seeded(): void
    {
        $this->assertTrue(
            DB::table('settings')->where('key', 'footer_about')->exists(),
            'Налаштування footer_about має існувати після міграцій.'
        );
    }
}
