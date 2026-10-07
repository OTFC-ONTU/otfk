<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Публічна частина не рекламує адмінпанель: жодного посилання на /admin у шапці чи підвалі.
 * Вхід для персоналу — лише за прямою адресою /admin (посібник адміністратора).
 */
class AdminExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_do_not_link_to_admin_panel(): void
    {
        foreach (['/', '/en', '/novyny', '/kontakty'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '~href="[^"]*/admin(?:[/"?#])~',
                $html,
                "Сторінка {$path} містить посилання на адмінпанель",
            );
            $this->assertStringNotContainsString('Адмінпанель', $html);
            $this->assertStringNotContainsString('Admin panel', $html);
        }
    }

    public function test_admin_requires_authentication_and_has_no_self_service_routes(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/register')->assertNotFound();
        $this->get('/admin/forgot-password')->assertNotFound();
    }
}
