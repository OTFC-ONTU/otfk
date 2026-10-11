<?php

namespace Tests\Feature;

use App\Filament\Pages\AutoTranslateSettings;
use App\Filament\Resources\SettingResource;
use App\Models\News;
use App\Models\Page;
use App\Models\Setting;
use App\Models\TranslationJob;
use App\Models\TranslationModel;
use App\Models\User;
use App\Services\AutoTranslation\AutoTranslator;
use App\Services\AutoTranslation\ClaudeClient;
use App\Services\AutoTranslation\Pricing;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * Автопереклад через Claude API: черга після збереження, запис перекладу з хешем і публікацією,
 * захист від застарілого результату, помилки API, ліміт, зашифрований ключ, кеш моделей на тиждень,
 * дашборд токенів/вартості. HTTP підмінено Guzzle MockHandler — запити проходять реальний SDK.
 */
class AutoTranslateTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-ant-api03-test-key-0123456789abcdef';

    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    private MockHandler $mock;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auto_translate.process_after_response' => false]);

        $this->mock = new MockHandler;
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        $this->app->instance(ClaudeClient::HTTP_BINDING, new GuzzleClient(['handler' => $stack, 'http_errors' => false]));
    }

    private function enable(array $settings = []): void
    {
        AutoTranslator::storeApiKey(self::KEY);
        foreach (['auto_translate_enabled' => '1', 'auto_translate_model' => 'claude-opus-5-5', ...$settings] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'auto_translate', 'type' => 'text']);
        }
    }

    private function messageResponse(array $fields, array $usage = ['input_tokens' => 1000, 'output_tokens' => 500], string $stop = 'end_turn', string $model = 'claude-opus-5-5'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => $model,
            'content' => [['type' => 'text', 'text' => json_encode($fields)]],
            'stop_reason' => $stop,
            'stop_sequence' => null,
            'usage' => $usage,
        ]));
    }

    private function modelsResponse(): Response
    {
        $model = fn (string $id, string $name, bool $structured = true) => [
            'type' => 'model', 'id' => $id, 'display_name' => $name, 'created_at' => '2026-09-01T00:00:00Z',
            'max_input_tokens' => 1000000, 'max_tokens' => 128000,
            'capabilities' => [
                'structured_outputs' => ['supported' => $structured],
                'effort' => ['supported' => true, 'low' => ['supported' => true], 'medium' => ['supported' => true], 'high' => ['supported' => true], 'max' => ['supported' => true]],
            ],
        ];

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'data' => [$model('claude-opus-5-5', 'Claude Opus 5.5'), $model('claude-haiku-5-5', 'Claude Haiku 5.5'), $model('claude-legacy-x', 'Legacy', false)],
            'has_more' => false, 'first_id' => 'claude-opus-5-5', 'last_id' => 'claude-legacy-x',
        ]));
    }

    private function news(array $attributes = []): News
    {
        return News::create(array_merge([
            'title' => 'День відкритих дверей',
            'slug' => 'den-vidkrytykh-dverei-'.uniqid(),
            'excerpt' => 'Запрошуємо вступників',
            'body' => '<p>Запрошуємо на <a href="/abituriyentu">день відкритих дверей</a>.</p>',
            'is_published' => false,
        ], $attributes));
    }

    public function test_disabled_auto_translation_queues_nothing(): void
    {
        $this->news();
        AutoTranslator::storeApiKey(self::KEY);
        $this->news();

        $this->assertSame(0, TranslationJob::count());
    }

    public function test_saved_material_is_translated_published_and_billed(): void
    {
        $this->enable();
        $news = $this->news();
        $updatedAt = $news->fresh()->updated_at;

        $job = TranslationJob::sole();
        $this->assertSame(TranslationJob::PENDING, $job->status);
        $this->assertStringContainsString('День відкритих дверей', $job->label);

        $this->mock->append($this->messageResponse([
            'title' => 'Open Day',
            'excerpt' => 'We invite applicants',
            'body' => '<p>Join us for the <a href="/abituriyentu">open day</a>.</p>',
        ]));
        $result = app(AutoTranslator::class)->processQueue(5);
        $this->assertSame(1, $result['done']);

        $news->refresh();
        $this->assertSame('Open Day', $news->title_en);
        $this->assertSame('We invite applicants', $news->excerpt_en);
        $this->assertStringContainsString('<a href="/abituriyentu">open day</a>', $news->body_en);
        $this->assertTrue($news->hasIndexableEnglishTranslation());
        $this->assertEquals($updatedAt, $news->updated_at, 'Переклад не змінює updated_at (lastmod) оригіналу');

        $job->refresh();
        $this->assertSame(TranslationJob::DONE, $job->status);
        $this->assertSame(1000, $job->input_tokens);
        $this->assertSame(500, $job->output_tokens);
        $this->assertEqualsWithDelta(0.014, $job->cost_usd, 0.000001);

        // Запит: модель, структурована відповідь, низький effort, серверний запасний варіант, ключ у заголовку
        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(self::KEY, $request->getHeaderLine('x-api-key'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('low', $body['output_config']['effort']);
        $this->assertSame(['title', 'excerpt', 'body'], $body['output_config']['format']['schema']['required']);
        $this->assertStringContainsString('Never change URLs', is_string($body['system']) ? $body['system'] : json_encode($body['system']));
        $this->assertStringContainsString('- body: HTML', $body['messages'][0]['content']);

        // Перекладений запис більше не ставиться в чергу
        $news->update(['views' => 5]);
        $this->assertSame(0, TranslationJob::open()->count());
    }

    public function test_translation_runs_after_response_with_plain_messages_api(): void
    {
        config(['auto_translate.process_after_response' => true]);
        $this->enable(['auto_translate_model' => 'claude-haiku-4-5']);
        $news = $this->news(['excerpt' => null, 'body' => null]);

        $this->mock->append($this->messageResponse(['title' => 'Open Day'], model: 'claude-haiku-4-5'));
        $this->app->terminate();

        $this->assertSame('Open Day', $news->fresh()->title_en);
        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertStringNotContainsString('server-side-fallback', $request->getHeaderLine('anthropic-beta'));
        $this->assertArrayNotHasKey('fallbacks', $body);
        $this->assertArrayNotHasKey('effort', $body['output_config'], 'Haiku 4.5 не підтримує effort');
        $this->assertEqualsWithDelta(0.0035, TranslationJob::sole()->cost_usd, 0.000001);
    }

    public function test_changed_original_is_retranslated_unless_overwrite_is_off(): void
    {
        $this->enable();
        $news = $this->news(['title_en' => 'Open Day', 'excerpt_en' => 'Invite', 'body_en' => '<p>Open day.</p>', 'translation_published' => true]);
        $this->assertSame(0, TranslationJob::count(), 'Актуальний ручний переклад не перекладається');

        $news->update(['title' => 'День відкритих дверей 2027']);
        $this->assertSame(1, TranslationJob::open()->count());

        TranslationJob::query()->delete();
        Setting::updateOrCreate(['key' => 'auto_translate_overwrite'], ['value' => '0', 'group' => 'auto_translate', 'type' => 'text']);
        $news->update(['title' => 'День відкритих дверей 2028']);
        $this->assertSame(0, TranslationJob::count(), 'Без дозволу оновлювати наявний переклад не перезаписується');

        // Порожнє англійське поле заповнюється і без дозволу на перезапис — лише воно
        $news->update(['excerpt_en' => null]);
        $this->assertSame(1, TranslationJob::count());
        $this->mock->append($this->messageResponse(['excerpt' => 'We invite applicants']));
        app(AutoTranslator::class)->processQueue(1);
        $this->assertSame(['excerpt'], json_decode((string) $this->history[0]['request']->getBody(), true)['output_config']['format']['schema']['required']);
        $news->refresh();
        $this->assertSame('Open Day', $news->title_en);
        $this->assertSame('We invite applicants', $news->excerpt_en);
    }

    public function test_result_is_discarded_when_original_changes_during_request(): void
    {
        $this->enable();
        $news = $this->news();

        $this->mock->append(function () use ($news) {
            News::whereKey($news->id)->first()->update(['title' => 'Новий заголовок']);

            return $this->messageResponse(['title' => 'Open Day', 'excerpt' => 'Invite', 'body' => '<p>Open day.</p>']);
        });
        $result = app(AutoTranslator::class)->processQueue(1);

        $this->assertSame(1, $result['skipped']);
        $this->assertNull($news->fresh()->title_en);
        $this->assertSame(1, TranslationJob::where('status', TranslationJob::PENDING)->count(), 'Новий запис черги для зміненого оригіналу');
        $this->assertSame(1000, TranslationJob::where('status', TranslationJob::SKIPPED)->value('input_tokens'), 'Витрачені токени враховано');
    }

    public function test_api_errors_fail_or_retry_and_stop_the_batch(): void
    {
        $this->enable();
        $this->news();
        $this->news(['title' => 'Друга новина']);

        $this->mock->append(new Response(401, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']])));
        $result = app(AutoTranslator::class)->processQueue(5);
        $this->assertSame('auth', $result['stopped']);
        $this->assertSame(1, TranslationJob::where('status', TranslationJob::FAILED)->count());
        $this->assertStringContainsString('Ключ API не прийнято', TranslationJob::where('status', TranslationJob::FAILED)->value('error'));
        $this->assertSame(1, TranslationJob::where('status', TranslationJob::PENDING)->count(), 'Решта черги чекає');

        $this->assertSame(1, AutoTranslator::retryFailed());

        // Відповідь без обов'язкового поля — помилка запису, не часткове збереження
        $this->mock->append($this->messageResponse(['title' => 'Open Day']));
        app(AutoTranslator::class)->processQueue(1);
        $this->assertStringContainsString('немає перекладу поля', TranslationJob::where('status', TranslationJob::FAILED)->value('error'));
        $this->assertSame(0, News::whereNotNull('title_en')->count());
    }

    public function test_monthly_budget_stops_the_queue(): void
    {
        $this->enable(['auto_translate_budget' => '0.5']);
        TranslationJob::create(['translatable_type' => News::class, 'translatable_id' => 999, 'status' => TranslationJob::DONE, 'input_tokens' => 10, 'cost_usd' => 0.6]);
        $this->news();

        $result = app(AutoTranslator::class)->processQueue(5);

        $this->assertSame('budget', $result['stopped']);
        $this->assertCount(0, $this->history);
        $this->assertSame(1, TranslationJob::where('status', TranslationJob::PENDING)->count());
    }

    public function test_settings_texts_are_translated_only_for_translatable_keys(): void
    {
        $this->enable(['auto_translate_types' => 'settings']);
        Setting::updateOrCreate(['key' => 'contact_phone'], ['value' => '(048) 000-00-00', 'group' => 'contacts', 'type' => 'text']);
        Setting::updateOrCreate(['key' => 'work_hours'], ['value' => 'Пн–Пт 8:00–17:00', 'group' => 'contacts', 'type' => 'text']);
        $this->news();

        $this->assertSame(['Тексти налаштувань (підвал, оголошення, адреса…): work_hours'], TranslationJob::pluck('label')->all());

        $this->mock->append($this->messageResponse(['value' => 'Mon–Fri 8:00–17:00'], model: 'claude-opus-5-5'));
        app(AutoTranslator::class)->processQueue(1);
        $setting = Setting::where('key', 'work_hours')->first();
        $this->assertSame('Mon–Fri 8:00–17:00', $setting->value_en);
        $this->assertTrue($setting->hasIndexableEnglishTranslation());
        app()->setLocale('en');
        $this->assertSame('Mon–Fri 8:00–17:00', Setting::publicGet('work_hours'), 'Кеш settings.* скинуто звичайним save()');
    }

    public function test_enqueue_missing_collects_records_without_current_translation(): void
    {
        $this->news();
        Page::query()->update(['title_en' => null]);
        $this->enable(['auto_translate_types' => 'news,pages']);

        $count = AutoTranslator::enqueueMissing();

        $this->assertGreaterThan(1, $count);
        $this->assertSame($count, TranslationJob::count());
        $this->assertSame(0, AutoTranslator::enqueueMissing(), 'Повторний виклик не дублює чергу');
        $this->artisan('otfk:auto-translate --limit=0 --missing')->assertSuccessful();
    }

    public function test_api_key_is_encrypted_never_rendered_and_hidden_from_raw_settings(): void
    {
        $this->actingAs(User::firstOrFail());
        $this->mock->append($this->modelsResponse());

        Livewire::test(AutoTranslateSettings::class)
            ->set('data.api_key', self::KEY)
            ->set('data.auto_translate_enabled', true)
            ->set('data.auto_translate_model', 'claude-haiku-5-5')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.api_key', null);

        $stored = Setting::where('key', AutoTranslator::API_KEY_SETTING)->value('value');
        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertSame(self::KEY, Crypt::decryptString($stored));
        $this->assertSame(self::KEY, AutoTranslator::apiKey());
        $this->assertTrue(AutoTranslator::enabled());
        $this->assertSame('claude-haiku-5-5', AutoTranslator::model());

        $this->get(AutoTranslateSettings::getUrl())->assertOk()
            ->assertDontSee(self::KEY)->assertDontSee($stored)
            ->assertSee('sk-ant-…cdef')->assertSee('Claude Haiku 5.5');
        $this->get(SettingResource::getUrl())->assertOk()->assertDontSee(AutoTranslator::API_KEY_SETTING);

        // Збереження без нового ключа не стирає збережений
        Livewire::test(AutoTranslateSettings::class)->call('save')->assertHasNoErrors();
        $this->assertSame(self::KEY, AutoTranslator::apiKey());
    }

    public function test_models_are_cached_for_a_week_and_refreshed_on_page_visit(): void
    {
        $this->actingAs(User::firstOrFail());
        $this->enable();

        $this->mock->append($this->modelsResponse());
        $this->get(AutoTranslateSettings::getUrl())->assertOk();
        $this->assertCount(1, $this->history);
        $this->assertSame(['claude-haiku-5-5', 'claude-legacy-x', 'claude-opus-5-5'], TranslationModel::orderBy('id')->pluck('id')->all());
        $options = AutoTranslator::modelOptions();
        $this->assertArrayHasKey('claude-opus-5-5', $options);
        $this->assertArrayNotHasKey('claude-legacy-x', $options, 'Моделі без structured outputs не пропонуються');
        $this->assertStringContainsString('$4 / $20', $options['claude-opus-5-5']);

        // Протягом тижня — без запитів до API
        $this->travel(6)->days();
        $this->get(AutoTranslateSettings::getUrl())->assertOk();
        $this->assertCount(1, $this->history);

        $this->travel(2)->days();
        $this->mock->append($this->modelsResponse());
        $this->get(AutoTranslateSettings::getUrl())->assertOk();
        $this->assertCount(2, $this->history);
    }

    public function test_dashboard_shows_monthly_tokens_and_cost(): void
    {
        $this->actingAs(User::firstOrFail());
        $this->enable();
        TranslationModel::create(['id' => 'claude-opus-5-5', 'display_name' => 'Claude Opus 5.5', 'fetched_at' => now()]);
        TranslationJob::create(['translatable_type' => News::class, 'translatable_id' => 1, 'label' => 'Новини: Тест', 'status' => TranslationJob::DONE, 'model' => 'claude-opus-5-5', 'input_tokens' => 120000, 'output_tokens' => 30000, 'cost_usd' => 1.08]);
        TranslationJob::create(['translatable_type' => News::class, 'translatable_id' => 2, 'label' => 'Новини: Помилка', 'status' => TranslationJob::FAILED, 'error' => 'Модель відмовилася перекладати цей текст.']);

        $this->get(AutoTranslateSettings::getUrl())->assertOk()
            ->assertSee('Використання API')
            ->assertSee('$1.08')
            ->assertSee('120 000')
            ->assertSee('30 000')
            ->assertSee('Модель відмовилася перекладати цей текст.');
    }

    public function test_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->get(AutoTranslateSettings::getUrl())->assertForbidden();
    }

    public function test_pricing_matches_longest_model_prefix(): void
    {
        $this->assertSame([4.0, 20.0], Pricing::rates('claude-opus-5-5'));
        $this->assertSame([5.0, 25.0], Pricing::rates('claude-opus-4-5-20251101'));
        $this->assertSame([15.0, 75.0], Pricing::rates('claude-opus-4-0'));
        $this->assertSame([0.1, 0.5], Pricing::rates('claude-haiku-5-5'));
        $this->assertNull(Pricing::rates('claude-unknown'));
        $this->assertEqualsWithDelta(0.0006, Pricing::cost('claude-haiku-5-5', 1000, 1000), 0.0000001);
    }
}
