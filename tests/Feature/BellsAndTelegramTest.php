<?php

namespace Tests\Feature;

use App\Jobs\PostNewsToTelegram;
use App\Models\BellPeriod;
use App\Models\News;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BellsAndTelegramTest extends TestCase
{
    use RefreshDatabase;

    public function test_bells_page_renders_with_periods(): void
    {
        $this->get('/rozklad-dzvinkiv')
            ->assertOk()
            ->assertSee('Розклад дзвінків')
            ->assertSee('1-ша пара')
            ->assertSee('Велика перерва');
    }

    public function test_studentu_section_has_bells_tile(): void
    {
        $this->get('/studentu')
            ->assertOk()
            ->assertSee('Розклад дзвінків');
    }

    public function test_bells_with_repeated_numbers_are_sorted_by_start_and_identified_by_id(): void
    {
        BellPeriod::query()->delete();
        $afternoon = BellPeriod::create(['number' => 1, 'starts' => '13:00', 'ends' => '14:10', 'is_active' => true]);
        $morning = BellPeriod::create(['number' => 1, 'starts' => '08:30', 'ends' => '09:40', 'is_active' => true]);
        BellPeriod::create(['number' => 2, 'starts' => '09:50', 'ends' => '11:00', 'is_active' => true]);
        BellPeriod::create(['number' => 9, 'starts' => '07:00', 'ends' => '08:00', 'is_active' => false]);

        $this->assertSame([$morning->id, $afternoon->id], BellPeriod::active()->where('number', 1)->pluck('id')->values()->all());
        $this->assertSame(['08:30', '09:50', '13:00'], BellPeriod::active()->pluck('starts')->all());

        foreach (['/rozklad-dzvinkiv', '/en/rozklad-dzvinkiv'] as $url) {
            $this->get($url)->assertOk()
                ->assertSeeInOrder(['>08:30</td>', '>09:50</td>', '>13:00</td>'], false)
                ->assertSee('current === '.$morning->id, false)
                ->assertSee('current === '.$afternoon->id, false)
                ->assertDontSee('>07:00</td>', false);
        }
    }

    public function test_overlapping_periods_do_not_create_false_breaks(): void
    {
        BellPeriod::query()->delete();
        BellPeriod::create(['number' => 4, 'starts' => '12:40', 'ends' => '14:30', 'is_active' => true]);
        BellPeriod::create(['number' => 1, 'starts' => '13:00', 'ends' => '14:10', 'is_active' => true]);
        BellPeriod::create(['number' => 2, 'starts' => '14:20', 'ends' => '15:30', 'is_active' => true]);
        BellPeriod::create(['number' => 3, 'starts' => '15:40', 'ends' => '16:50', 'is_active' => true]);

        $response = $this->get('/rozklad-dzvinkiv')->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'colspan="4"'));
        $response->assertSee('Перерва · 10 хв')
            ->assertDontSee('Велика перерва ·');
    }

    private function enableAutopost(): void
    {
        Setting::where('key', 'telegram_autopost')->update(['value' => '1']);
        Setting::where('key', 'telegram_bot_token')->update(['value' => 'test-token']);
        Setting::where('key', 'telegram_channel')->update(['value' => '@test_channel']);
        cache()->forget('settings.map');
    }

    public function test_news_dispatches_telegram_post_once(): void
    {
        Bus::fake();
        $this->enableAutopost();

        $news = News::create([
            'title' => 'Новина для Telegram',
            'body' => '<p>Текст</p>',
            'published_at' => now()->subMinute(),
            'is_published' => true,
        ]);

        // Пост летить після віддачі відповіді — адмін не чекає на Telegram.
        Bus::assertDispatchedAfterResponseTimes(PostNewsToTelegram::class, 1);
        $this->assertNotNull($news->fresh()->telegram_posted_at);

        // Повторне збереження не постить вдруге (атомарна позначка).
        $news->fresh()->update(['title' => 'Оновлена назва']);
        Bus::assertDispatchedAfterResponseTimes(PostNewsToTelegram::class, 1);
    }

    public function test_no_autopost_when_disabled(): void
    {
        Bus::fake();

        News::create([
            'title' => 'Тиха новина',
            'body' => '<p>Текст</p>',
            'published_at' => now()->subMinute(),
            'is_published' => true,
        ]);

        Bus::assertNotDispatchedAfterResponse(PostNewsToTelegram::class);
    }

    public function test_job_sends_request_to_telegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->enableAutopost();

        $news = News::withoutEvents(fn () => News::create([
            'title' => 'Новина для Telegram',
            'slug' => 'novyna-dlya-telegram',
            'body' => '<p>Текст</p>',
            'published_at' => now()->subMinute(),
            'is_published' => true,
        ]));

        (new PostNewsToTelegram($news))->handle();

        Http::assertSentCount(1);
    }
}
