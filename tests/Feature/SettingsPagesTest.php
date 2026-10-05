<?php

namespace Tests\Feature;

use App\Filament\Pages\AnnouncementSettings;
use App\Filament\Pages\ContactSettings;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\HolidaySettings;
use App\Filament\Pages\TelegramSettings;
use App\Filament\Resources\SettingResource;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Людські» сторінки налаштувань у групі «Налаштування» замість сирого
 * key-value списку: відкриваються, зберігають значення в settings,
 * публікують/знімають англійський переклад перекладних ключів і не
 * чіпають переклад, коли змінено лише оригінал. Групи меню мають
 * сталий порядок, налаштування — внизу.
 */
class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::firstOrFail());
    }

    public function test_all_settings_pages_open(): void
    {
        foreach ([GeneralSettings::class, ContactSettings::class, AnnouncementSettings::class, HolidaySettings::class, TelegramSettings::class] as $page) {
            $this->get($page::getUrl())->assertOk();
        }
        $this->get(SettingResource::getUrl())->assertOk()->assertSee('Розширені налаштування');
    }

    public function test_navigation_groups_have_fixed_order_with_settings_last(): void
    {
        $groups = Filament::getPanel('admin')->getNavigationGroups();
        $this->assertSame('Контент', $groups[0]);
        $this->assertSame('Налаштування', end($groups));

        // Пункти групи «Налаштування» в меню: людські сторінки, далі сирий список і адміністратори
        $this->get('/admin')->assertOk()->assertSeeInOrder([
            'Контент', 'Звернення', 'Налаштування',
            'Основні', 'Контакти та соцмережі', 'Оголошення', 'Святкова тема', 'Telegram',
            'Розширені налаштування', 'Адміністратори',
        ]);
    }

    public function test_contacts_are_saved_and_translation_published(): void
    {
        Livewire::test(ContactSettings::class)
            ->set('data.contact_phone', '(048) 000-00-00')
            ->set('data.contact_address', 'вул. Тестова, 1')
            ->set('data.contact_address_en', '1 Testova St.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('(048) 000-00-00', Setting::get('contact_phone'));
        $address = Setting::where('key', 'contact_address')->first();
        $this->assertSame('1 Testova St.', $address->value_en);
        $this->assertTrue($address->hasPublishedEnglishTranslation());

        $this->get('/en')->assertSee('1 Testova St.');
    }

    public function test_changing_only_original_keeps_translation_state(): void
    {
        Livewire::test(AnnouncementSettings::class)
            ->set('data.announcement_text', 'Оголошення')
            ->set('data.announcement_text_en', 'Notice')
            ->call('save');
        $hash = Setting::where('key', 'announcement_text')->value('translation_source_hash');

        Livewire::test(AnnouncementSettings::class)
            ->assertSet('data.announcement_text_en', 'Notice')
            ->set('data.announcement_text', 'Нове оголошення')
            ->call('save');

        $setting = Setting::where('key', 'announcement_text')->first();
        $this->assertTrue($setting->translation_published);
        $this->assertSame($hash, $setting->translation_source_hash);
        $this->assertSame('Оригінал змінено', $setting->translationStatus());

        // Очищений переклад знімається з публікації
        Livewire::test(AnnouncementSettings::class)->set('data.announcement_text_en', '')->call('save');
        $this->assertFalse(Setting::where('key', 'announcement_text')->first()->translation_published);
    }

    public function test_telegram_toggle_is_stored_as_flag(): void
    {
        Livewire::test(TelegramSettings::class)
            ->set('data.telegram_autopost', true)
            ->set('data.telegram_channel', '@otfk')
            ->call('save');

        $this->assertSame('1', Setting::get('telegram_autopost'));
        $this->assertSame('@otfk', Setting::get('telegram_channel'));

        Livewire::test(TelegramSettings::class)->assertSet('data.telegram_autopost', true);
    }
}
