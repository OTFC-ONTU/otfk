<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactSettings;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\TelegramSettings;
use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\SettingResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ролі адмінпанелі: адміністратор керує користувачами, налаштуваннями та меню,
 * редактор працює лише з контентом. Запобіжники не дають залишити панель без
 * адміністратора або видалити власний обліковий запис.
 */
class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_user_is_admin_and_factory_defaults_are_explicit(): void
    {
        $this->assertTrue(User::firstOrFail()->isAdmin());
        $this->assertTrue(User::factory()->create()->isAdmin());
        $this->assertFalse(User::factory()->editor()->create()->isAdmin());

        // create() без ролі (tinker, make:filament-user) — редактор, а не виняток
        $this->assertSame(User::ROLE_EDITOR, User::create(['name' => 'Без ролі', 'email' => 'norole@example.test', 'password' => 'Dovhyi-parol-2026'])->role);
    }

    public function test_editor_cannot_open_users_settings_or_menu(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(SettingResource::getUrl())->assertForbidden();
        $this->get(MenuItemResource::getUrl())->assertForbidden();
        foreach ([GeneralSettings::class, ContactSettings::class, TelegramSettings::class] as $page) {
            $this->get($page::getUrl())->assertForbidden();
        }

        // Контент доступний, у меню немає адміністративних розділів.
        $this->get(NewsResource::getUrl())->assertOk();
        $this->get('/admin')->assertOk()
            ->assertDontSee('Користувачі')
            ->assertDontSee('Розширені налаштування')
            ->assertDontSee('Меню навігації');

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(ContactSettings::class)->assertForbidden();
    }

    public function test_admin_sees_everything(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(UserResource::getUrl())->assertOk();
        $this->get(MenuItemResource::getUrl())->assertOk();
        $this->get(GeneralSettings::getUrl())->assertOk();
        $this->get('/admin')->assertOk()->assertSee('Користувачі')->assertSee('Меню навігації');
    }

    public function test_settings_save_is_rechecked_on_livewire_call_after_role_change(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $component = Livewire::test(ContactSettings::class)->assertOk();

        $admin->update(['role' => User::ROLE_EDITOR]); // сидований адміністратор лишається
        $component->call('save')->assertForbidden();
    }

    public function test_user_form_requires_strong_password_and_saves_role(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Редактор', 'email' => 'editor@example.test', 'role' => User::ROLE_EDITOR,
                'password' => 'short1', 'password_confirmation' => 'short1'])
            ->call('create')
            ->assertHasFormErrors(['password']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Редактор', 'email' => 'editor@example.test', 'role' => User::ROLE_EDITOR,
                'password' => 'Dovhyi-parol-2026', 'password_confirmation' => 'Dovhyi-parol-2026'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(User::ROLE_EDITOR, User::where('email', 'editor@example.test')->firstOrFail()->role);
    }

    public function test_last_admin_cannot_be_demoted_or_deleted_and_nobody_deletes_themselves(): void
    {
        $seeded = User::firstOrFail();
        $last = User::factory()->create();
        $seeded->update(['role' => User::ROLE_EDITOR]); // дозволено: лишається $last

        try {
            $last->update(['role' => User::ROLE_EDITOR]);
            $this->fail('Останнього адміністратора не можна понизити.');
        } catch (ValidationException) {
        }

        try {
            $last->delete();
            $this->fail('Останнього адміністратора не можна видалити.');
        } catch (ValidationException) {
        }

        $this->actingAs($seeded);
        try {
            $seeded->delete();
            $this->fail('Власний обліковий запис не видаляється.');
        } catch (ValidationException) {
        }

        $this->assertDatabaseHas('users', ['id' => $last->id, 'role' => User::ROLE_ADMIN]);
        $this->assertDatabaseHas('users', ['id' => $seeded->id]);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        User::factory()->create(['role' => 'superuser']);
    }
}
