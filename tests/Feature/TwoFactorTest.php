<?php

namespace Tests\Feature;

use App\Filament\Auth\TwoFactorChallenge;
use App\Filament\Auth\TwoFactorSetup;
use App\Filament\Pages\ContactSettings;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Support\TwoFactor;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Двофакторний захист адмінки (TOTP): обов'язкове підключення, сторінка коду
 * для прямих URL і Livewire-викликів, невірний/повторний код, одноразові коди
 * відновлення, скид адміністратором і консоллю, аварійний вимикач. Усі входи
 * тут — через actingAsWithoutTwoFactor(): без фікстурної позначки сесії.
 */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function code(string $secret = UserFactory::TEST_TOTP_SECRET, int $shift = 0): string
    {
        $engine = new Google2FA;

        return $engine->oathTotp($secret, $engine->getTimestamp() + $shift);
    }

    public function test_user_without_factor_is_sent_to_setup_and_cannot_use_panel_or_livewire(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasTwoFactor());
        $this->actingAsWithoutTwoFactor($user);

        $this->get('/admin')->assertRedirect(TwoFactorSetup::getUrl());
        $this->get(ListNews::getUrl())->assertRedirect(TwoFactorSetup::getUrl());
        $this->get(TwoFactorSetup::getUrl())->assertOk()->assertSee('Підключіть застосунок');
        $this->get(TwoFactorChallenge::getUrl())->assertRedirect(TwoFactorSetup::getUrl());

        // Livewire-виклик компонента панелі без фактора — 403, навіть із валідним snapshot
        // (snapshot отримано в сесії з пройденим фактором, потім позначку прибрано).
        session([TwoFactor::SESSION_KEY => $user->id]);
        $user->forceFill(['two_factor_secret' => UserFactory::TEST_TOTP_SECRET, 'two_factor_confirmed_at' => now()])->saveQuietly();
        $html = $this->get(ContactSettings::getUrl())->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        session()->forget(TwoFactor::SESSION_KEY);

        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'save', 'params' => []]]]],
        ])->assertForbidden();
    }

    public function test_enrollment_requires_valid_code_and_issues_one_time_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAsWithoutTwoFactor($user);

        $component = Livewire::test(TwoFactorSetup::class)->assertOk();
        $secret = $component->get('pendingSecret');
        $this->assertNotEmpty($secret);

        $component->fillForm(['code' => '000000'])->call('confirm')->assertHasErrors(['data.code']);
        $this->assertFalse($user->fresh()->hasTwoFactor(), 'невірний код не вмикає фактор');

        $component->fillForm(['code' => $this->code($secret)])->call('confirm')->assertHasNoErrors();
        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertCount(10, $component->get('recoveryCodes'));
        $this->assertSame($component->get('recoveryCodes'), $user->two_factor_recovery_codes);
        $this->assertSame($secret, $user->two_factor_secret);

        // Після підключення сесія вже пройшла фактор — панель відкрита.
        $this->get('/admin')->assertOk();
    }

    public function test_enrolled_user_must_pass_challenge_each_session(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAsWithoutTwoFactor($user);

        $this->get('/admin')->assertRedirect(TwoFactorChallenge::getUrl());
        $this->get(TwoFactorSetup::getUrl())->assertRedirect(TwoFactorChallenge::getUrl());
        $this->get(TwoFactorChallenge::getUrl())->assertOk()->assertSee('Другий крок входу');

        // Сторінка коду сама викликається через Livewire і має бути дозволена без фактора.
        $html = $this->get(TwoFactorChallenge::getUrl())->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'verify', 'params' => []]]]],
        ])->assertOk(); // Livewire відповідає 200 з помилкою валідації, а не 403

        $challenge = Livewire::test(TwoFactorChallenge::class)->assertOk();
        $challenge->fillForm(['code' => '123456'])->call('verify')->assertHasErrors(['data.code']);
        $this->assertNull(session(TwoFactor::SESSION_KEY));

        $code = $this->code();
        $challenge->fillForm(['code' => $code])->call('verify')->assertHasNoErrors();
        $this->assertSame($user->id, session(TwoFactor::SESSION_KEY));
        $this->get('/admin')->assertOk();
        $this->get(ListUsers::getUrl())->assertOk();

        // Після втрати позначки Livewire-виклик таблиці користувачів — 403.
        $html = $this->get(ListUsers::getUrl())->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        session()->forget(TwoFactor::SESSION_KEY);
        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
        ])->assertForbidden();

        // Той самий код удруге (нова сесія) — відхиляється.
        Livewire::test(TwoFactorChallenge::class)->fillForm(['code' => $code])->call('verify')->assertHasErrors(['data.code']);
    }

    public function test_recovery_code_works_once_and_is_journaled(): void
    {
        $log = storage_path('logs/security-2fa-test.log');
        @unlink($log);
        config(['logging.channels.security' => ['driver' => 'single', 'path' => $log, 'level' => 'info']]);

        $user = User::factory()->withTwoFactor()->create();
        $this->actingAsWithoutTwoFactor($user);

        Livewire::test(TwoFactorChallenge::class)->fillForm(['code' => 'aaaaa-bbbbb'])->call('verify')->assertHasNoErrors();
        $this->assertSame(['CCCCC-DDDDD'], $user->fresh()->two_factor_recovery_codes);

        session()->forget(TwoFactor::SESSION_KEY);
        Livewire::test(TwoFactorChallenge::class)->fillForm(['code' => 'AAAAA-BBBBB'])->call('verify')->assertHasErrors(['data.code']);

        $journal = file_get_contents($log);
        $this->assertStringContainsString('2fa.recovery_used', $journal);
        $this->assertStringContainsString('2fa.failed', $journal);
        @unlink($log);
    }

    public function test_admin_can_reset_factor_for_others_but_not_self_and_console_can_reset_anyone(): void
    {
        $admin = User::factory()->withTwoFactor()->create();
        $editor = User::factory()->editor()->withTwoFactor()->create();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetTwoFactor', $admin)
            ->assertTableActionVisible('resetTwoFactor', $editor)
            ->callTableAction('resetTwoFactor', $editor);

        $editor->refresh();
        $this->assertFalse($editor->hasTwoFactor());
        $this->assertNull($editor->two_factor_recovery_codes);

        $this->artisan('otfk:two-factor', ['email' => $admin->email, '--reset' => true])->assertSuccessful();
        $this->assertFalse($admin->fresh()->hasTwoFactor());
        $this->artisan('otfk:two-factor', ['--status' => true])->expectsOutputToContain('Примусовість')->assertSuccessful();
    }

    public function test_editor_cannot_reset_factors_and_kill_switch_allows_login_without_code(): void
    {
        $editor = User::factory()->editor()->withTwoFactor()->create();
        $this->actingAs($editor);
        $this->get(TwoFactorSetup::getUrl())->assertOk(); // власна сторінка доступна
        Livewire::test(ListUsers::class)->assertForbidden();

        config(['otfk.two_factor.enforce' => false]);
        $other = User::factory()->withTwoFactor()->create();
        $this->actingAsWithoutTwoFactor($other);
        $this->get('/admin')->assertOk();
    }

    public function test_regenerating_recovery_codes_and_reenrolling_require_current_code(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user);

        $setup = Livewire::test(TwoFactorSetup::class)->assertOk();
        $this->assertNull($setup->get('pendingSecret'));

        $setup->fillForm(['code' => '000000'])->call('regenerateRecoveryCodes')->assertHasErrors(['data.code']);
        $this->assertSame(['AAAAA-BBBBB', 'CCCCC-DDDDD'], $user->fresh()->two_factor_recovery_codes);

        $setup->fillForm(['code' => $this->code()])->call('regenerateRecoveryCodes')->assertHasNoErrors();
        $this->assertCount(10, $user->fresh()->two_factor_recovery_codes);

        // Той самий код після regenerate не приймається; наступний крок (у вікні ±1) — приймається.
        $setup->fillForm(['code' => $this->code()])->call('startReenroll')->assertHasErrors(['data.code']);
        $setup->fillForm(['code' => $this->code(shift: 1)])->call('startReenroll')->assertHasNoErrors();
        $this->assertNotEmpty($setup->get('pendingSecret'));
        $this->assertSame(UserFactory::TEST_TOTP_SECRET, $user->fresh()->two_factor_secret, 'старий секрет діє до підтвердження нового');
    }

    public function test_last_admin_cannot_be_locked_out_by_itself(): void
    {
        // Єдиний адміністратор не має кнопки скидання власного фактора — лишаються коди відновлення, консоль і вимикач.
        $seeded = User::firstOrFail();
        $this->assertTrue($seeded->isAdmin());
        $this->actingAs($seeded);
        Livewire::test(ListUsers::class)->assertTableActionHidden('resetTwoFactor', $seeded);
    }
}
