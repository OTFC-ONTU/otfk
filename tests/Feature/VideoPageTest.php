<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Редизайн сторінки /video: світла шапка, головне відео, вбудований плеєр, блок соцмереж. */
class VideoPageTest extends TestCase
{
    use RefreshDatabase;

    /** Демо-відео з сидера прибираємо — сторінку перевіряємо на власному наборі. */
    protected function setUp(): void
    {
        parent::setUp();

        Video::query()->delete();
    }

    private function makeVideo(string $title, string $youtubeId, int $sort = 0): Video
    {
        return Video::create([
            'title' => $title,
            'youtube_id' => $youtubeId,
            'published_at' => now()->subDays($sort + 1),
            'sort_order' => $sort,
            'is_published' => true,
        ]);
    }

    public function test_page_has_light_header_and_featured_video(): void
    {
        $this->makeVideo('Привітання директора', 'AAAAAAAAAA1', 0);
        $this->makeVideo('Віртуальна екскурсія', 'BBBBBBBBBB2', 1);

        $res = $this->get('/video');

        $res->assertOk()
            // Світла шапка розділу замість navy-героя
            ->assertSee('bg-slate-50/80', false)
            ->assertSee('text-brand-950 sm:text-4xl lg:text-[2.75rem]">Відеоматеріали</h1>', false)
            // Лічильник відео і головне відео
            ->assertSee('2 відео')
            ->assertSee('Дивіться зараз')
            ->assertSee('Усі відео')
            ->assertSee('Привітання директора')
            ->assertSee('Віртуальна екскурсія');
    }

    public function test_videos_play_in_embedded_lightbox_without_tracking_cookies(): void
    {
        $this->makeVideo('Студентське життя', 'CCCCCCCCCC3');

        $res = $this->get('/video');

        $res->assertOk()
            // Плеєр вбудований у лайтбокс, домен без трекінгових cookie
            // (адреса приходить через @js, тож слеші в HTML екрановані)
            ->assertSee('youtube-nocookie.com', false)
            ->assertSee('CCCCCCCCCC3', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Відтворити відео', false)
            // Пряме посилання на YouTube лишається доступним
            ->assertSee('https://www.youtube.com/watch?v=CCCCCCCCCC3', false);
    }

    public function test_single_video_has_no_featured_block(): void
    {
        $this->makeVideo('Єдине відео', 'DDDDDDDDDD4');

        $this->get('/video')
            ->assertOk()
            ->assertSee('Єдине відео')
            ->assertDontSee('Дивіться зараз');
    }

    public function test_youtube_channel_cta_appears_only_when_setting_is_filled(): void
    {
        $this->makeVideo('Відео', 'EEEEEEEEEE5');

        Setting::updateOrCreate(['key' => 'social_youtube'], ['value' => '', 'group' => 'social', 'type' => 'url']);
        Cache::forget('settings.map');

        $this->get('/video')->assertOk()->assertDontSee('Перейти на YouTube-канал');

        Setting::where('key', 'social_youtube')->update(['value' => 'https://youtube.com/@otfk']);
        Cache::forget('settings.map');

        $this->get('/video')
            ->assertOk()
            ->assertSee('Перейти на YouTube-канал')
            ->assertSee('https://youtube.com/@otfk', false);
    }

    public function test_empty_state_when_no_published_videos(): void
    {
        $this->makeVideo('Чернетка', 'FFFFFFFFFF6')->update(['is_published' => false]);

        $this->get('/video')
            ->assertOk()
            ->assertSee('Відео поки немає.')
            ->assertDontSee('Чернетка');
    }
}
