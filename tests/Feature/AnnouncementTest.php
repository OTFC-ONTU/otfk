<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_banner_shows_when_set(): void
    {
        $this->get('/')->assertDontSee('id="announcement"', escape: false);

        Setting::where('key', 'announcement_text')->update(['value' => 'Завтра коледж працює дистанційно!']);
        cache()->forget('settings.map');

        $this->get('/')->assertSee('Завтра коледж працює дистанційно!');
    }

    public function test_abituriyentu_pages_disable_drop_cap(): void
    {
        $this->get('/abituriyentu')
            ->assertOk()
            ->assertSee('prose-site--no-dropcap', escape: false);
    }
}
