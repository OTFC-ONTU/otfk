<?php

namespace Tests\Feature;

use App\Filament\Resources\LegacyRedirectResource;
use App\Filament\Resources\LegacyRedirectResource\Pages\CreateLegacyRedirect;
use App\Filament\Resources\NotFoundLogResource;
use App\Filament\Resources\NotFoundLogResource\Pages\ListNotFoundLogs;
use App\Models\LegacyRedirect;
use App\Models\News;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\User;
use App\Support\LegacyRedirects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Карта старих адрес і журнал 404 (docs/seo-plan.md, етап 1): редирект лише
 * для вже сформованого 404, 410 для видаленого, валідація призначення,
 * імпорт CSV і доступ лише адміністратора.
 */
class LegacyRedirectsTest extends TestCase
{
    use RefreshDatabase;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archive = sys_get_temp_dir().'/otfk-legacy-'.uniqid();
        // Механізм значущих параметрів перевіряється на умовному параметрі старої CMS.
        config(['otfk.legacy.query_keys' => ['p']]);
        Page::updateOrCreate(['slug' => 'pro-koledzh'], ['title' => 'Про коледж', 'is_published' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->archive);
        parent::tearDown();
    }

    private function redirect(string $source, ?string $target = '/pro-koledzh', array $extra = []): LegacyRedirect
    {
        $parts = parse_url($source);

        return LegacyRedirect::create($extra + [
            'source_path' => $parts['path'],
            'source_query' => $parts['query'] ?? null,
            'target_url' => $target,
        ]);
    }

    // ── Відповіді ────────────────────────────────────────────────────────

    public function test_old_path_redirects_permanently_and_counts_hits(): void
    {
        $redirect = $this->redirect('/about-college.html');

        $this->get('/about-college.html')->assertStatus(301)->assertRedirect(url('/pro-koledzh'));
        $this->get('/about-college.html/?utm_source=fb')->assertStatus(301)->assertRedirect(url('/pro-koledzh'));

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_exact_query_wins_over_path_only_and_tracking_params_are_ignored(): void
    {
        News::create(['title' => 'Стара новина', 'slug' => 'stara-novyna', 'body' => '<p>x</p>', 'is_published' => true, 'published_at' => now()->subDay()]);
        $this->redirect('/old.php?p=12', '/novyny/stara-novyna', ['status_code' => 308]);
        $this->redirect('/old.php', '/novyny');

        $this->get('/old.php?utm_campaign=x&p=12')->assertStatus(308)->assertRedirect(url('/novyny/stara-novyna'));
        $this->get('/old.php?p=99')->assertStatus(301)->assertRedirect(url('/novyny'));
    }

    public function test_old_directory_index_and_trailing_slash_are_one_address(): void
    {
        config(['otfk.legacy.query_keys' => []]);
        $this->redirect('/structure/chairs/', '/novyny');

        $this->assertSame('/structure/chairs', LegacyRedirect::first()->source_path);
        // Один перехід: слеш у кінці (старий сайт) і /index.php ведуть одразу на нову адресу.
        $this->get('/structure/chairs/')->assertStatus(301)->assertRedirect(url('/novyny'));
        $this->get('/structure/chairs/index.php')->assertStatus(301)->assertRedirect(url('/novyny'));
        // Без значущих параметрів запит зіставляється лише за шляхом.
        $this->get('/structure/chairs/?id=5')->assertStatus(301)->assertRedirect(url('/novyny'));
        $this->assertSame(['/', null], LegacyRedirects::normalize('/index.php'));

        // Старі посилання http:// і www. основного домену — одразу на https без www.
        config(['otfk.seo.primary_host' => 'otfk.od.ua']);
        $this->get('http://www.otfk.od.ua/structure/chairs/')->assertStatus(301)->assertRedirect('https://otfk.od.ua/novyny');
        $this->get('http://otfk.od.ua/structure/chairs/index.php')->assertRedirect('https://otfk.od.ua/novyny');
    }

    public function test_migration_renormalizes_previously_imported_sources(): void
    {
        // Запис, збережений за старої нормалізації: хеш шляху з index.php.
        DB::table('legacy_redirects')->insert([
            'source_hash' => LegacyRedirects::hash('/structure/old/index.php', null),
            'source_path' => '/structure/old/index.php', 'source_query' => null,
            'action' => LegacyRedirect::REDIRECT, 'target_url' => '/novyny', 'status_code' => 301,
            'is_active' => true, 'hits' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get('/structure/old/')->assertNotFound();

        (require database_path('migrations/2026_10_08_130000_renormalize_legacy_redirect_sources.php'))->up();

        $this->assertSame('/structure/old', LegacyRedirect::first()->source_path);
        $this->get('/structure/old/')->assertStatus(301)->assertRedirect(url('/novyny'));
    }

    public function test_missed_lookups_are_not_cached(): void
    {
        $path = '/scanner-probe-'.uniqid();
        $this->get($path)->assertNotFound();
        $version = (int) Cache::get('legacy_redirects.version', 0);
        $this->assertFalse(Cache::has("legacy_redirects.{$version}.".LegacyRedirects::hash($path, null)));

        // Знайдений запис кешується як і раніше.
        $this->redirect('/stara-adresa', '/novyny');
        $this->get('/stara-adresa')->assertStatus(301);
        $version = (int) Cache::get('legacy_redirects.version', 0);
        $this->assertTrue(Cache::has("legacy_redirects.{$version}.".LegacyRedirects::hash('/stara-adresa', null)));
    }

    public function test_htaccess_keeps_old_site_slash_paths_for_laravel(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        // Каталоги старого сайту позначаються прапорцем, який обходять www, HTTPS і прибирання слешу.
        $this->assertMatchesRegularExpression('~RewriteCond %\{REQUEST_URI\} \^/\(\?:([a-z_|]+)\)/\n\s*RewriteRule \^ - \[E=OTFK_LEGACY:1\]~', $htaccess);
        $this->assertSame(3, substr_count($htaccess, 'RewriteCond %{ENV:OTFK_LEGACY} !=1'));
        $this->assertMatchesRegularExpression('~RewriteCond %\{ENV:OTFK_LEGACY\} !=1\n(?:\s*RewriteCond[^\n]*\n)*\s*RewriteRule \^ %1 \[L,R=301\]~', $htaccess);
        $this->assertLessThan(strpos($htaccess, 'RewriteCond %{HTTP_HOST} ^www'), strpos($htaccess, 'E=OTFK_LEGACY:1'));
        preg_match('~\^/\(\?:([a-z_|]+)\)/~', $htaccess, $m);
        $exempt = explode('|', $m[1]);

        foreach (['news', 'structure', 'student', 'applicant', 'public_information', 'uploads'] as $old) {
            $this->assertContains($old, $exempt);
        }
        // Каталог-виняток не може збігатися з верхнім сегментом маршруту нового сайту.
        $live = collect(app('router')->getRoutes())->map(fn ($r) => explode('/', ltrim($r->uri(), '/'))[0])->unique();
        $this->assertSame([], array_values(array_intersect($exempt, $live->all())));
    }

    public function test_cyrillic_and_encoded_paths_match(): void
    {
        $this->redirect('/uploads/Фото звіт.jpg');

        $this->get('/uploads/%D0%A4%D0%BE%D1%82%D0%BE%20%D0%B7%D0%B2%D1%96%D1%82.jpg')->assertStatus(301);
        // Регістр шляху значущий, як на Apache.
        $this->get('/uploads/фото звіт.jpg')->assertNotFound();
    }

    public function test_gone_returns_410_with_useful_links(): void
    {
        $this->redirect('/old-news/42', null, ['action' => LegacyRedirect::GONE, 'status_code' => 410]);

        $this->get('/old-news/42')
            ->assertStatus(410)
            ->assertSee('Матеріал видалено')
            ->assertSee('href="'.url('/abituriyentu').'"', false)
            ->assertSee('href="'.url('/spetsialnosti').'"', false)
            ->assertHeaderMissing('Location');
    }

    public function test_live_page_is_never_overridden_and_inactive_records_are_ignored(): void
    {
        // Запис для живої адреси (наприклад, створений до публікації сторінки) не спрацьовує.
        Page::create(['title' => 'Жива', 'slug' => 'zhyva-storinka', 'is_published' => true]);
        $this->redirect('/zhyva-storinka', '/novyny');
        $this->get('/zhyva-storinka')->assertOk();

        $this->redirect('/vymknenyi', '/novyny', ['is_active' => false]);
        $this->get('/vymknenyi')->assertNotFound();
    }

    public function test_admin_paths_cannot_be_redirect_sources(): void
    {
        $this->expectException(ValidationException::class);
        $this->redirect('/admin/staryi', '/novyny');
    }

    // ── Валідація карти ──────────────────────────────────────────────────

    public function test_external_loops_and_chains_are_rejected(): void
    {
        foreach (['https://evil.example/x', '//evil.example/x', 'novyny', '/admin', "/novyny\n"] as $target) {
            try {
                $this->redirect('/source-'.md5($target), $target);
                $this->fail("Прийнято призначення {$target}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('target_url', $e->errors());
            }
        }

        try {
            $this->redirect('/loop', '/loop/?p=1');
            $this->fail('Прийнято цикл');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('цикл', implode(' ', $e->errors()['target_url']));
        }

        $this->redirect('/a', '/b-target');
        try {
            $this->redirect('/b', '/a'); // /b → /a → /b-target
            $this->fail('Прийнято ланцюжок');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target_url', $e->errors());
        }
        try {
            $this->redirect('/b-target', '/novyny'); // /a → /b-target → /novyny
            $this->fail('Прийнято ланцюжок');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('source', $e->errors());
        }
    }

    // ── Журнал 404 ───────────────────────────────────────────────────────

    public function test_not_found_log_aggregates_and_strips_sensitive_data(): void
    {
        $this->get('/uploads/2015/staryi.pdf?token=secret&p=7&utm_source=x', ['Referer' => 'https://google.com/search?q=otfk#x'])->assertNotFound();
        $this->get('/uploads/2015/staryi.pdf?p=7&session=abc')->assertNotFound();
        $this->get('/uploads/2015/staryi.pdf')->assertNotFound();

        $this->assertSame(2, NotFoundLog::count());
        $log = NotFoundLog::where('query', 'p=7')->firstOrFail();
        $this->assertSame('/uploads/2015/staryi.pdf', $log->path);
        $this->assertSame(2, $log->hits);
        $this->assertSame('https://google.com/search', $log->referrer);
        $this->assertSame(NotFoundLog::NEW, $log->status);

        foreach (NotFoundLog::all() as $row) {
            $this->assertStringNotContainsString('secret', json_encode($row->getAttributes()));
            $this->assertStringNotContainsString('utm', json_encode($row->getAttributes()));
        }

        // POST, адмінка і Livewire не журналюються.
        $this->post('/nemaye/post')->assertNotFound();
        $this->get('/admin/nemaye')->assertNotFound();
        $this->assertSame(2, NotFoundLog::count());
    }

    public function test_log_respects_row_limit_and_prunes_old_rows(): void
    {
        config(['otfk.not_found_log.max_rows' => 1]);
        $this->get('/pershyi-404')->assertNotFound();
        $this->get('/druhyi-404')->assertNotFound();
        $this->get('/pershyi-404')->assertNotFound();

        $this->assertSame(['/pershyi-404'], NotFoundLog::pluck('path')->all());
        $this->assertSame(2, NotFoundLog::first()->hits);

        NotFoundLog::query()->update(['last_seen_at' => now()->subDays(91)]);
        $this->artisan('model:prune', ['--model' => [NotFoundLog::class]])->assertSuccessful();
        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_log_failure_does_not_break_404(): void
    {
        Schema::drop('not_found_logs');

        $this->get('/bez-zhurnalu')->assertNotFound()->assertSee('Сторінку не знайдено');
    }

    // ── Імпорт CSV ───────────────────────────────────────────────────────

    public function test_csv_import_dry_run_reports_conflicts_and_apply_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('mirror/otfk.od.ua/uploads/doc.pdf', '%PDF-1.4');
        Storage::disk('public')->put('news/imported/foto.jpg', 'jpg');

        $csv = $this->archive.'/map.csv';
        File::ensureDirectoryExists($this->archive);
        file_put_contents($csv, "\xEF\xBB\xBFsource,target,code,note\n"
            ."/uploads/doc.pdf,/storage/mirror/otfk.od.ua/uploads/doc.pdf,,документ\n"
            ."https://www.otfk.od.ua/uploads/foto.jpg,/storage/news/imported/foto.jpg,301,\n"
            ."/old.php?p=5,,410,видалено\n"
            ."/uploads/doc.pdf,/novyny,301,дубль\n"
            ."/pro-koledzh,/novyny,301,жива адреса\n"
            ."/zovnishnii,https://example.com/,301,\n"
            ."/nema-faila,/storage/mirror/nemaye.pdf,301,\n"
            ."/lantsyug,/uploads/foto.jpg,301,ланцюжок\n");

        $this->artisan('otfk:legacy-redirects', ['file' => $csv, '--archive-dir' => $this->archive])
            ->expectsTable(['Нових', 'Змінених', 'Без змін', 'Конфліктів'], [[3, 0, 0, 5]])
            ->assertFailed();
        $this->assertSame(0, LegacyRedirect::count());

        $this->artisan('otfk:legacy-redirects', ['file' => $csv, '--apply' => true, '--archive-dir' => $this->archive])->assertFailed();
        $this->assertSame(3, LegacyRedirect::count());
        $this->assertCount(2, glob($this->archive.'/*_*')); // копія CSV + звіт

        $this->get('/uploads/doc.pdf')->assertRedirect(url('/storage/mirror/otfk.od.ua/uploads/doc.pdf'));
        $this->get('/uploads/foto.jpg')->assertRedirect(url('/storage/news/imported/foto.jpg'));
        $this->get('/old.php?p=5')->assertStatus(410);

        // Повторний запуск нічого не змінює.
        $this->artisan('otfk:legacy-redirects', ['file' => $csv, '--archive-dir' => $this->archive])
            ->expectsTable(['Нових', 'Змінених', 'Без змін', 'Конфліктів'], [[0, 0, 3, 5]]);
    }

    // ── Адмінка ──────────────────────────────────────────────────────────

    public function test_only_admin_can_manage_redirects_and_404_log(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->get(LegacyRedirectResource::getUrl())->assertForbidden();
        $this->get(NotFoundLogResource::getUrl())->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get(LegacyRedirectResource::getUrl())->assertOk();
        $this->get(NotFoundLogResource::getUrl())->assertOk();
    }

    public function test_admin_form_validates_and_creates_redirect(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateLegacyRedirect::class)
            ->fillForm(['source' => 'https://otfk.od.ua/stara.html?utm_source=x', 'action' => LegacyRedirect::REDIRECT, 'target_url' => 'https://evil.example/', 'status_code' => 301])
            ->call('create')
            ->assertHasFormErrors(['target_url']);

        Livewire::test(CreateLegacyRedirect::class)
            ->fillForm(['source' => '/pro-koledzh', 'action' => LegacyRedirect::REDIRECT, 'target_url' => '/novyny', 'status_code' => 301])
            ->call('create')
            ->assertHasFormErrors(['source']);

        Livewire::test(CreateLegacyRedirect::class)
            ->fillForm(['source' => 'https://otfk.od.ua/stara.html?utm_source=x', 'action' => LegacyRedirect::REDIRECT, 'target_url' => '/pro-koledzh', 'status_code' => 308])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('legacy_redirects', ['source_path' => '/stara.html', 'source_query' => null, 'target_url' => '/pro-koledzh', 'status_code' => 308]);
    }

    public function test_redirect_is_created_from_404_log(): void
    {
        $this->get('/staryi-rozdil/abituriyentam')->assertNotFound();
        $log = NotFoundLog::firstOrFail();
        $this->actingAs(User::factory()->create());

        Livewire::test(ListNotFoundLogs::class)
            ->callTableAction('redirect', $log, data: ['action' => LegacyRedirect::REDIRECT, 'target_url' => '/nemaye-takoyi'])
            ->assertNotified('Редирект не створено');
        $this->assertSame(0, LegacyRedirect::count());

        Livewire::test(ListNotFoundLogs::class)
            ->callTableAction('redirect', $log, data: ['action' => LegacyRedirect::REDIRECT, 'target_url' => '/pro-koledzh']);

        $this->assertSame(NotFoundLog::RESOLVED, $log->fresh()->status);
        $this->get('/staryi-rozdil/abituriyentam')->assertRedirect(url('/pro-koledzh'));
    }
}
