<?php

namespace Tests\Feature;

use App\Console\Commands\SanitizeContent;
use App\Filament\Auth\Login;
use App\Filament\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Models\Department;
use App\Models\News;
use App\Models\Page;
use App\Models\Staff;
use App\Models\User;
use App\Rules\SafeUrl;
use App\Support\HtmlSanitizer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Технічні контракти захисту адмінки (docs/security-audit.md): захисні
 * заголовки на /admin, обмеження завантажень, очищення HTML редактора,
 * безпечні схеми посилань, журнал входів, сидер без стандартного пароля.
 */
class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_get_security_headers_and_noindex(): void
    {
        $login = $this->get('/admin/login')->assertOk();
        $login->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $home = $this->get('/')->assertOk();
        $home->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
        $this->assertFalse($home->headers->has('X-Robots-Tag'));
    }

    public function test_uploads_directory_forbids_script_execution(): void
    {
        $htaccess = file_get_contents(storage_path('app/public/.htaccess'));

        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertMatchesRegularExpression('/FilesMatch\s+"[^"]*php/', $htaccess);
        $this->assertStringContainsString('sandbox', $htaccess);
        $this->assertStringContainsString('!.htaccess', file_get_contents(storage_path('app/public/.gitignore')));

        // Другий, гарантовано робочий на хостингу шар — mod_rewrite у public/.htaccess.
        $this->assertMatchesRegularExpression('/RewriteRule \^storage\/\.\*\\\\\.\(\?i:php[^\n]*\[F,L\]/', file_get_contents(public_path('.htaccess')));
    }

    public function test_inline_styles_cannot_overlay_the_page(): void
    {
        $clean = (string) HtmlSanitizer::clean('<div style="position:fixed;inset:0;z-index:2147483647;background:white;color:red">x</div>'
            .'<p style="text-align:center; background:url(https://evil.example/x.png)">y</p>');

        $this->assertSame('<div style="background: white; color: red">x</div><p style="text-align: center">y</p>', $clean);

        // Те саме через утиліти Tailwind зі збірки сайту (fixed/inset-0/z-50 існують у CSS).
        $this->assertSame(
            '<div class="bg-white text-center kept w-full">x</div>',
            HtmlSanitizer::clean('<div class="fixed inset-0 z-50 bg-white md:absolute -translate-x-4 opacity-0 text-center kept w-full">x</div>'),
        );
    }

    public function test_sanitize_content_command_reports_and_cleans_legacy_html_without_side_effects(): void
    {
        // Власний тимчасовий каталог бекапів: тест не торкається storage/app/private,
        // де можуть лежати справжні резервні копії після очищення реального контенту.
        $backupDir = sys_get_temp_dir().'/otfk-sanitize-test-'.bin2hex(random_bytes(4));
        mkdir($backupDir);
        $legacy = '<p>Старий</p><script>alert(1)</script><!--imported-from:https://otfk.od.ua/old-->';
        $page = Page::create(['title' => 'Старий', 'slug' => 'staryi', 'body' => '<p>ok</p>', 'is_published' => true,
            'title_en' => 'Old', 'body_en' => '<p>ok</p>', 'translation_published' => true]);
        // Імітуємо запис до появи касту: сирий HTML у БД, хеш перекладу актуальний для нього.
        DB::table('pages')->where('id', $page->id)->update(['body' => $legacy, 'updated_at' => '2026-01-01 00:00:00']);
        $page->refresh();
        DB::table('pages')->where('id', $page->id)->update(['translation_source_hash' => $page->currentTranslationSourceHash()]);
        $this->assertStringContainsString('<script', $page->fresh()->getRawOriginal('body'));

        // Нормалізована розмітка без втрат і пейлоад з «/» замість пробілу — класифікатор має їх розрізнити.
        $plain = Page::create(['title' => 'Норм', 'slug' => 'norm', 'body' => '<p>ok</p>', 'is_published' => true]);
        DB::table('pages')->where('id', $plain->id)->update(['body' => '<table><tr><td>1</td></tr></table><img src="/storage/a.jpg">']);
        $tricky = Page::create(['title' => 'Трюк', 'slug' => 'tryuk', 'body' => '<p>ok</p>', 'is_published' => true]);
        DB::table('pages')->where('id', $tricky->id)->update(['body' => '<p>x</p><img/src=x onerror=alert(1)>']);
        // Атрибут лишається, але з нього зникає небезпечне значення — теж «видалено», не нормалізація.
        $overlay = Page::create(['title' => 'Оверлей', 'slug' => 'overlei', 'body' => '<p>ok</p>', 'is_published' => true]);
        DB::table('pages')->where('id', $overlay->id)->update(['body' => '<div style="position:fixed;color:red" class="fixed z-50 kept">x</div>']);

        $this->artisan('otfk:sanitize-content')
            ->expectsOutputToContain('потребують очищення 4, з них із видаленими тегами/атрибутами — 3')
            ->expectsOutputToContain('#'.$plain->id.' (norm): body  (лише нормалізація розмітки)')
            ->expectsOutputToContain('#'.$tricky->id.' (tryuk): body  ← видалено: body [<img onerror>]')
            ->expectsOutputToContain('#'.$overlay->id.' (overlei): body  ← видалено: body [<div style:position> <div class:fixed> <div class:z-50>]')
            ->assertSuccessful();
        $this->assertStringContainsString('<script', $page->fresh()->getRawOriginal('body'), 'Без --apply нічого не записується');

        // Правка редактора між скануванням і записом не затирається.
        SanitizeContent::$afterScan = fn () => DB::table('pages')->where('id', $overlay->id)->update(['body' => '<p>нова правка</p><script>x</script>']);
        try {
            $this->artisan('otfk:sanitize-content', ['--apply' => true, '--backup-dir' => $backupDir])
                ->expectsOutputToContain('Резервна копія оригіналів')
                ->expectsOutputToContain('#'.$overlay->id.': змінено після сканування — пропущено')
                ->expectsOutputToContain('змінено 3 записів, пропущено 1')
                ->assertFailed();
        } finally {
            SanitizeContent::$afterScan = null;
        }
        $this->assertSame('<p>нова правка</p><script>x</script>', $overlay->fresh()->getRawOriginal('body'), 'паралельна правка збережена, її почистить наступний запуск');
        $this->assertStringNotContainsString('onerror', $tricky->fresh()->getRawOriginal('body'));

        // Найвужче вікно: правка між перечитуванням запису й записом — умовний UPDATE не збігається.
        DB::table('pages')->where('id', $overlay->id)->update(['body' => '<p>v2</p><script>y</script>']);
        SanitizeContent::$beforeWrite = fn () => DB::table('pages')->where('id', $overlay->id)->update(['body' => '<p>v3 — правка редактора</p>']);
        try {
            $this->artisan('otfk:sanitize-content', ['--apply' => true, '--backup-dir' => $backupDir])
                ->expectsOutputToContain('#'.$overlay->id.': змінено після сканування — пропущено')
                ->assertFailed();
        } finally {
            SanitizeContent::$beforeWrite = null;
        }
        $this->assertSame('<p>v3 — правка редактора</p>', $overlay->fresh()->getRawOriginal('body'), 'правка у вікні між SELECT і UPDATE не затерта');
        $fresh = $page->fresh();
        $this->assertSame('<p>Старий</p><!--imported-from:https://otfk.od.ua/old-->', $fresh->getRawOriginal('body'));
        $this->assertSame('2026-01-01 00:00:00', $fresh->getRawOriginal('updated_at'), 'saveQuietly без timestamps');
        $this->assertFalse($fresh->translationIsStale(), 'актуальний переклад не стає застарілим після очищення');

        $backups = glob($backupDir.'/sanitize-backup-*.json');
        $this->assertNotEmpty($backups);
        $this->assertEmpty(glob(storage_path('app/private/sanitize-backup-*.json')) ?: [], 'тест не пише у робочий каталог бекапів');
        $backup = json_decode(file_get_contents($backups[0]), true); // перший --apply (імена файлів хронологічні)
        $this->assertSame(config('database.default'), $backup['connection']);
        $this->assertCount(4, $backup['records'], 'бекап містить оригінали всіх відсканованих записів, включно з пропущеним');
        $first = collect($backup['records'])->firstWhere('id', $page->id);
        $this->assertSame($page->currentTranslationSourceHash(), $first['translation_source_hash'], 'бекап зберігає прежній хеш перекладу');
        $this->assertArrayHasKey('updated_at', $first);
        $this->assertStringContainsString('alert(1)', file_get_contents($backups[0]));

        // Другий запуск у ту ж секунду — окремий файл, нічого не перезаписується.
        DB::table('pages')->where('id', $plain->id)->update(['body' => '<p>ще</p><script>z</script>']);
        $this->artisan('otfk:sanitize-content', ['--apply' => true, '--backup-dir' => $backupDir])->assertSuccessful();
        $this->assertGreaterThan(count($backups), count(glob($backupDir.'/sanitize-backup-*.json')));
        foreach (glob($backupDir.'/*') as $file) {
            @unlink($file); // лише власні файли тесту
        }
        @rmdir($backupDir);
    }

    public function test_livewire_upload_rules_reject_scripts_and_svg_but_accept_images_and_pdf(): void
    {
        $rules = config('livewire.temporary_file_upload.rules');
        $this->assertNotEmpty($rules);
        $this->assertNotContains('svg', config('livewire.temporary_file_upload.preview_mimes'));

        $check = fn (UploadedFile $file) => Validator::make(['file' => $file], ['file' => $rules])->passes();

        $this->assertTrue($check(UploadedFile::fake()->image('photo.jpg')));
        $this->assertTrue($check(UploadedFile::fake()->createWithContent('doc.pdf', "%PDF-1.4\n%test")));
        $this->assertFalse($check(UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')));
        $this->assertFalse($check(UploadedFile::fake()->createWithContent('shell.phtml', '<?php echo 1;')));
        $this->assertFalse($check(UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')));
        $this->assertFalse($check(UploadedFile::fake()->createWithContent('icon.svg', '<svg onload="alert(1)"></svg>')));
        // Розширення картинки, а всередині PHP — відхиляється за фактичним MIME вмісту
        // (справжній UploadedFile: фейковий звітує MIME за назвою файлу).
        $tmp = tempnam(sys_get_temp_dir(), 'otfk');
        file_put_contents($tmp, "<?php system('id');");
        $this->assertFalse($check(new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true)));
        @unlink($tmp);
        $this->assertFalse($check(UploadedFile::fake()->create('big.pdf', 25000, 'application/pdf')));
    }

    public function test_rich_text_is_sanitized_on_save_but_legacy_markup_survives(): void
    {
        $page = Page::create([
            'title' => 'Санітайзер', 'slug' => 'sanitayzer', 'is_published' => true,
            'body' => '<!--imported-from:https://otfk.od.ua/x--><p onclick="alert(1)">Текст</p>'
                .'<script>alert(1)</script><a href="javascript:alert(2)">x</a>'
                .'<iframe src="https://evil.example/"></iframe>'
                .'<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>'
                .'<details><summary>А</summary><table><tr><td style="color: red">1</td></tr></table></details>',
        ]);

        $body = $page->fresh()->body;
        $this->assertStringContainsString('<!--imported-from:https://otfk.od.ua/x-->', $body);
        $this->assertStringContainsString('<p>Текст</p>', $body);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abc', $body);
        $this->assertStringContainsString('<td style="color: red">1</td>', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('evil.example', $body);
        $this->assertStringNotContainsString('onclick', $body);

        $news = News::create(['title' => 'Н', 'slug' => 'n-san', 'body' => '<img src="x" onerror="alert(1)">', 'is_published' => true, 'published_at' => now()]);
        $this->assertSame('<img src="x" />', $news->fresh()->body);

        $this->assertSame('<a>x</a>', HtmlSanitizer::clean('<a href="&#106;avascript:alert(1)">x</a>'));
        $this->assertSame('<img src="data:image/png;base64,AA" />', HtmlSanitizer::clean('<img src="data:image/png;base64,AA" />'));
        $this->assertSame('<p>якщо a &lt; b</p>', HtmlSanitizer::clean('<p>якщо a < b</p>'));
        $this->assertNull(HtmlSanitizer::clean(null));

        // Обходи регулярних виразів, знайдені незалежною перевіркою: DOM-парсер їх нейтралізує.
        foreach ([
            '<img/src=x onerror=alert(1)>', '<svg/onload=alert(1)>', '<a href="x"/onclick="alert(1)">x</a>',
            '<img src="x"onerror="alert(1)">', '<scr<script>ipt>alert(document.domain)</scr</script x>ipt>',
            '<obj<object>ect data="x">', "<script>alert(1)</script>\xff", '<style>body{display:none}</style>',
            '<iframe src="/\\evil.example/phish"></iframe>', '<iframe src="https://sites.google.com/view/fake"></iframe>',
            '<iframe src="https://docs.google.com/forms/d/e/x/viewform"></iframe>', '<a href="javascript:alert(1)">x</a>',
        ] as $payload) {
            $clean = (string) HtmlSanitizer::clean($payload);
            foreach (['<script', 'onerror', 'onload', 'onclick', '<object', '<style', 'javascript:', 'evil.example', 'sites.google', 'forms/d'] as $needle) {
                $this->assertStringNotContainsString($needle, $clean, "Пейлоад {$payload} лишив {$needle}: {$clean}");
            }
        }
    }

    public function test_staff_bio_is_sanitized_before_public_output(): void
    {
        $department = Department::create(['title' => 'Кафедра', 'slug' => 'kafedra-san', 'type' => 'kafedra', 'is_published' => true]);
        $staff = Staff::create([
            'full_name' => 'Тест Викладач', 'slug' => 'test-vykladach', 'position' => 'викладач', 'category' => 'teacher',
            'department_id' => $department->id, 'is_published' => true,
            'bio' => '<p>Біо</p><img src=x onerror="alert(1)"><script>alert(2)</script>',
        ]);

        $this->assertStringNotContainsString('onerror', $staff->fresh()->bio);
        $this->get('/personal/'.$staff->slug)->assertOk()->assertSee('Біо')->assertDontSee('onerror', false)->assertDontSee('<script>alert(2)', false);
    }

    public function test_lockout_after_five_failed_logins_is_journaled(): void
    {
        $log = storage_path('logs/security-lockout-test.log');
        @unlink($log);
        config(['logging.channels.security' => ['driver' => 'single', 'path' => $log, 'level' => 'info']]);
        User::factory()->create(['email' => 'lock@example.test']);

        for ($i = 0; $i < 6; $i++) {
            Livewire::test(Login::class)
                ->fillForm(['email' => 'lock@example.test', 'password' => 'wrong-password-'.$i])
                ->call('authenticate');
        }

        $journal = file_get_contents($log);
        $this->assertStringContainsString('auth.failed', $journal);
        $this->assertStringContainsString('auth.lockout', $journal);
        @unlink($log);
    }

    public function test_json_ld_escapes_script_terminators_in_titles(): void
    {
        $news = News::create([
            'title' => 'Тест</script><script>alert(1)</script>', 'slug' => 'xss-title',
            'body' => '<p>x</p>', 'is_published' => true, 'published_at' => now()->subHour(),
        ]);

        $this->get('/novyny/'.$news->slug)->assertOk()
            ->assertDontSee('</script><script>alert(1)', false);
    }

    public function test_link_fields_reject_dangerous_schemes(): void
    {
        $this->assertTrue(SafeUrl::isSafe('/novyny'));
        $this->assertTrue(SafeUrl::isSafe('news.index'));
        $this->assertTrue(SafeUrl::isSafe('#top'));
        $this->assertTrue(SafeUrl::isSafe('https://example.com/a?b=1'));
        $this->assertTrue(SafeUrl::isSafe('mailto:info@example.com'));
        $this->assertTrue(SafeUrl::isSafe(''));
        $this->assertFalse(SafeUrl::isSafe('javascript:alert(1)'));
        $this->assertFalse(SafeUrl::isSafe(" java\tscript:alert(1)"));
        $this->assertFalse(SafeUrl::isSafe('data:text/html;base64,AA'));
        $this->assertFalse(SafeUrl::isSafe('vbscript:x'));

        $this->actingAs(User::factory()->create());
        Livewire::test(CreateMenuItem::class)
            ->fillForm(['label' => 'Зло', 'link_type' => 'url', 'url' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_logins_are_journaled_and_last_login_recorded(): void
    {
        $log = storage_path('logs/security-test.log');
        @unlink($log);
        config(['logging.channels.security' => ['driver' => 'single', 'path' => $log, 'level' => 'info']]);

        $user = User::factory()->create(['email' => 'audit@example.test']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'audit@example.test', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasErrors(['data.email']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'audit@example.test', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $journal = file_get_contents($log);
        $this->assertStringContainsString('auth.failed', $journal);
        $this->assertStringContainsString('auth.login', $journal);
        $this->assertStringContainsString('audit@example.test', $journal);
        $this->assertStringNotContainsString('wrong-password', $journal);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
        @unlink($log);
    }

    public function test_seeder_refuses_default_password_outside_local_and_testing(): void
    {
        $this->assertSame('', (string) env('ADMIN_PASSWORD', ''), 'Тест очікує порожній ADMIN_PASSWORD в оточенні.');
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(\RuntimeException::class);
            (new DatabaseSeeder)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_seeder_does_not_overwrite_existing_admin_password(): void
    {
        $admin = User::firstOrFail();
        $admin->forceFill(['password' => 'Novyi-parol-2026'])->save();
        $hash = $admin->fresh()->password;

        (new DatabaseSeeder)->callSilent(DatabaseSeeder::class);
        $this->assertSame($hash, $admin->fresh()->password);
    }
}
