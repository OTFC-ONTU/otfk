<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\QuickLinkResource\Pages\EditQuickLink;
use App\Filament\Resources\SettingResource\Pages\EditSetting;
use App\Filament\Resources\StatItemResource\Pages\EditStatItem;
use App\Filament\Resources\TestimonialResource\Pages\EditTestimonial;
use App\Models\Banner;
use App\Models\QuickLink;
use App\Models\Setting;
use App\Models\StatItem;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HomeBlocksTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function banner(): Banner
    {
        return Banner::create([
            'title' => 'Український банер', 'subtitle' => 'Український підзаголовок',
            'image_alt' => 'Український alt', 'link_label' => 'Українська кнопка',
            'title_en' => 'English banner', 'subtitle_en' => 'English subtitle',
            'image_alt_en' => 'English banner alt', 'link_label_en' => 'English action',
            'image' => 'banners/missing.jpg', 'link_url' => '/abituriyentu',
            'is_published' => true, 'translation_published' => true,
        ]);
    }

    private function setting(string $key, string $value = 'Українське значення', string $english = 'English setting'): Setting
    {
        return Setting::updateOrCreate(['key' => $key], [
            'type' => 'textarea', 'value' => $value, 'value_en' => $english, 'translation_published' => true,
        ]);
    }

    public function test_banner_translates_all_text_alt_and_localized_action_preserving_images_and_dates(): void
    {
        $banner = $this->banner();
        $this->get('/en')->assertOk()->assertSee('English banner')->assertSee('English subtitle')->assertSee('English action')
            ->assertSee('alt="English banner alt"', false)->assertSee('/storage/banners/missing.jpg', false)
            ->assertSee('href="'.url('/en/abituriyentu').'"', false)->assertDontSee('Український підзаголовок');
        $this->get('/')->assertSee('Українська кнопка')->assertDontSee('English action');
        foreach (['title', 'subtitle', 'image_alt', 'link_label'] as $field) {
            Banner::whereKey($banner->id)->update([$field.'_en' => null]);
            $this->get('/en')->assertSee('Український банер')->assertSee('Українська кнопка')->assertDontSee('English subtitle');
            Banner::whereKey($banner->id)->update([$field.'_en' => $banner->getAttribute($field.'_en')]);
        }
        $banner->update(['ends_at' => now()->subDay()]);
        $this->get('/en')->assertDontSee('English banner alt');
        $this->assertSame('/abituriyentu', $banner->fresh()->link_url);
    }

    public function test_banner_without_title_can_translate_populated_fields_and_alt_uses_localized_fallback(): void
    {
        $banner = Banner::create(['subtitle' => 'Лише підзаголовок', 'subtitle_en' => 'Subtitle only', 'image' => 'banners/only.jpg', 'is_published' => true, 'translation_published' => true]);
        $this->get('/en')->assertSee('Subtitle only')->assertSee('alt="Banner"', false);
        $this->assertTrue(Banner::withPublishedEnglishTranslation()->whereKey($banner->id)->exists());
        $banner->update(['title_en' => 'English alt fallback']);
        $this->get('/en')->assertSee('alt="English alt fallback"', false);
        $banner->update(['title' => 'Новий заголовок', 'title_en' => 'English alt fallback']);
        $banner->update(['image_alt' => 'Новий alt']);
        $this->get('/en')->assertSee('Лише підзаголовок')->assertDontSee('Subtitle only');
        $this->assertFalse(Banner::withPublishedEnglishTranslation()->whereKey($banner->id)->exists());
    }

    public function test_tiles_partners_testimonials_and_statistics_translate_as_whole_records(): void
    {
        $tile = QuickLink::create(['title' => 'Українська плитка', 'description' => 'Український опис плитки', 'title_en' => 'English tile', 'description_en' => 'English tile description', 'url' => '/faq', 'location' => 'home_tile', 'is_visible' => true, 'translation_published' => true]);
        $partner = QuickLink::create(['title' => 'Український партнер', 'title_en' => 'English partner', 'url' => 'https://example.test/partner', 'location' => 'footer_partner', 'is_visible' => true, 'translation_published' => true]);
        $testimonial = Testimonial::create(['name' => 'Українське Ім’я', 'role' => 'Українська роль', 'quote' => 'Український відгук', 'name_en' => 'English Name', 'role_en' => 'English role', 'quote_en' => 'English testimonial', 'is_active' => true, 'translation_published' => true]);
        $stat = StatItem::create(['label' => 'Українська статистика', 'label_en' => 'English statistics', 'value' => '1234+', 'is_active' => true, 'translation_published' => true]);
        $this->get('/en')->assertOk()->assertSee('English tile description')->assertSee('English partner')->assertSee('English testimonial')
            ->assertSee('English role')->assertSee('EN')->assertSee('English statistics')->assertSee('1 234+')
            ->assertSee('href="'.url('/en/faq').'"', false)->assertSee('https://example.test/partner');
        $this->get('/')->assertSee('Український відгук')->assertSee('Українська плитка')->assertDontSee('English testimonial');
        app()->setLocale('en');
        $this->assertSame('EN', $testimonial->initials);
        $testimonial->update(['photo' => 'testimonials/missing.jpg']);
        $this->get('/en')->assertSee('alt="English Name"', false);
        QuickLink::whereKey($tile->id)->update(['description_en' => null]);
        Testimonial::whereKey($testimonial->id)->update(['quote_en' => null]);
        $this->get('/en')->assertSee('Український опис плитки')->assertSee('Українська роль')->assertSee('Український відгук')->assertDontSee('English tile')->assertDontSee('English role');
        $tile->refresh()->update(['is_visible' => false]);
        $partner->update(['is_visible' => false]);
        $testimonial->refresh()->update(['is_active' => false]);
        $stat->update(['is_active' => false]);
        $this->get('/en')->assertDontSee('Український опис плитки')->assertDontSee('English partner')->assertDontSee('Український відгук')->assertDontSee('English statistics');
        $this->assertSame('1234+', $stat->fresh()->value);
    }

    public function test_all_blocks_validate_complete_publication_and_track_source_changes_without_shared_fields(): void
    {
        $models = [
            [$this->banner(), 'subtitle', 'English subtitle'],
            [QuickLink::create(['title' => 'Плитка', 'title_en' => 'Tile', 'url' => '/faq', 'translation_published' => true]), 'title', 'Tile'],
            [Testimonial::create(['name' => 'Ім’я', 'quote' => 'Відгук', 'name_en' => 'Name', 'quote_en' => 'Quote', 'translation_published' => true]), 'quote', 'Quote'],
            [StatItem::create(['label' => 'Підпис', 'label_en' => 'Label', 'value' => '100+', 'translation_published' => true]), 'label', 'Label'],
        ];
        foreach ($models as [$model, $field, $english]) {
            $hash = $model->translation_source_hash;
            $model->update(['sort_order' => 42]);
            $this->assertFalse($model->translationIsStale());
            $model->update([$field => 'Змінений текст']);
            $this->assertSame($hash, $model->translation_source_hash);
            $this->assertTrue($model->translationIsStale());
            $model->update([$field.'_en' => $english.' updated']);
            $this->assertFalse($model->translationIsStale());
            try {
                $model->update([$field.'_en' => ' ']);
                $this->fail('Incomplete translation published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
        }
    }

    public function test_public_settings_translate_after_cache_without_changing_service_values_and_invalidate_on_save_delete(): void
    {
        $setting = $this->setting('contact_address', 'Українська адреса', 'English address');
        $this->setting('work_hours', 'Українські години', 'English hours');
        app()->setLocale('en');
        $this->assertSame('English address', Setting::publicGet('contact_address'));
        $this->assertSame('Українська адреса', Setting::get('contact_address'));
        $this->assertSame('Українська адреса', Setting::map()['contact_address']);
        $this->assertTrue(Cache::has('settings.translations'));
        app()->setLocale('uk');
        $this->assertSame('Українська адреса', Setting::publicGet('contact_address'));
        $this->get('/en/kontakty')->assertOk()->assertSee('English address')->assertSee('English hours')->assertDontSee('Українська адреса');
        $setting->update(['value_en' => 'Updated address']);
        $this->assertFalse(Cache::has('settings.map'));
        $this->assertFalse(Cache::has('settings.translations'));
        $this->get('/en/kontakty')->assertSee('Updated address');
        $setting->update(['translation_published' => false]);
        $this->get('/en/kontakty')->assertSee('Українська адреса')->assertDontSee('Updated address');
        $setting->delete();
        $this->assertNull(Setting::publicGet('contact_address'));
    }

    public function test_settings_publish_announcement_brand_metadata_rss_and_jsonld_and_preserve_hidden_originals(): void
    {
        $announcement = $this->setting('announcement_text', 'Українське оголошення', 'English announcement');
        $this->setting('brand_name', 'Український бренд', 'English College');
        $this->setting('brand_short', 'УКР', 'ENG');
        Setting::where('key', 'logo')->firstOrFail()->update(['value' => 'settings/logo.png']);
        $this->setting('footer_about', 'Український підвал', 'English footer');
        $this->setting('site_description', 'Український опис сайту', 'English site description');
        $badge = $this->setting('site_version_label', 'Українська позначка', 'English badge');
        $address = $this->setting('contact_address', 'Українська адреса', 'Address </script><script>alert(1)</script>');
        $this->get('/en/faq')->assertOk()->assertSee('English announcement')->assertSee('English College')->assertSee('English footer')
            ->assertSee('English badge')->assertSee('alt="ENG"', false)
            ->assertDontSee('</script><script>alert(1)</script>', false);
        $this->get('/en')->assertSee('content="English site description"', false);
        $this->get('/en/novyny/feed.xml')->assertOk()->assertSee('English College')->assertSee('English site description')->assertSee('<language>en</language>', false);
        $this->get('/en/podiyi')->assertOk()->assertDontSee('</script><script>alert(1)</script>', false);
        $hash = $announcement->translation_source_hash;
        $announcement->update(['value' => '']);
        $badge->update(['value' => '']);
        $this->assertSame($hash, $announcement->translation_source_hash);
        $this->assertTrue($announcement->translationIsStale());
        $this->get('/en/faq')->assertDontSee('English announcement')->assertDontSee('English badge');
        $address->update(['value_en' => 'New address']);
        $this->get('/en/kontakty')->assertSee('New address');
    }

    public function test_standard_brand_defaults_stay_english_and_technical_settings_cannot_publish_translations(): void
    {
        $this->get('/en/faq')->assertSee(__('layout.brand_name', [], 'en'))->assertDontSee('Одеський технічний фаховий коледж - структурний');
        Setting::where('key', 'site_version_label')->firstOrFail()->delete();
        $this->get('/en/faq')->assertSee('Alpha version')->assertDontSee('Альфа-версія');
        $this->get('/faq')->assertSee('Альфа-версія');
        foreach (['contact_email', 'contact_phone', 'telegram_bot_token', 'announcement_url', 'site_version_color', 'logo'] as $key) {
            $setting = Setting::firstOrCreate(['key' => $key], ['value' => 'original', 'type' => 'text']);
            try {
                $setting->update(['value_en' => 'English technical value', 'translation_published' => true]);
                $this->fail('Technical setting translation published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
            $this->assertSame($setting->fresh()->value, Setting::publicGet($key));
        }
        $setting = $this->setting('work_hours');
        $hash = $setting->translation_source_hash;
        $setting->update(['type' => 'url']);
        $this->assertSame($hash, $setting->translation_source_hash);
        $this->assertTrue($setting->translationIsStale());
        $this->assertFalse($setting->hasPublishedEnglishTranslation());
        $this->assertSame('Українське значення', Setting::publicGet('work_hours'));
    }

    public function test_filament_saves_all_blocks_settings_and_banner_without_title(): void
    {
        $this->actingAs(User::firstOrFail());
        $banner = Banner::create(['subtitle' => 'Без заголовка', 'is_published' => true]);
        Livewire::test(EditBanner::class, ['record' => $banner->id])->fillForm(['subtitle_en' => 'Subtitle without title', 'translation_published' => true])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($banner->fresh()->hasPublishedEnglishTranslation());
        $tile = QuickLink::create(['title' => 'Плитка', 'url' => '/faq', 'location' => 'home_tile']);
        Livewire::test(EditQuickLink::class, ['record' => $tile->id])->fillForm(['title_en' => 'Admin tile', 'translation_published' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin tile', $tile->fresh()->title_en);
        $testimonial = Testimonial::create(['name' => 'Ім’я', 'quote' => 'Відгук']);
        Livewire::test(EditTestimonial::class, ['record' => $testimonial->id])->fillForm(['name_en' => 'Admin Name', 'quote_en' => 'Admin quote', 'translation_published' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin quote', $testimonial->fresh()->quote_en);
        $stat = StatItem::create(['label' => 'Підпис', 'value' => '100+']);
        Livewire::test(EditStatItem::class, ['record' => $stat->id])->fillForm(['label_en' => 'Admin label', 'translation_published' => true])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin label', $stat->fresh()->label_en);
        $setting = $this->setting('announcement_text');
        Livewire::test(EditSetting::class, ['record' => $setting->id])->fillForm(['value_en' => ''])->call('save')->assertHasFormErrors(['value_en' => 'required']);
        $long = str_repeat('Long English content. ', 30);
        Livewire::test(EditSetting::class, ['record' => $setting->id])->fillForm(['value_en' => $long])->call('save')->assertHasNoFormErrors();
        $this->assertSame($long, $setting->fresh()->value_en);
    }
}
