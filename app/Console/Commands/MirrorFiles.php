<?php

namespace App\Console\Commands;

use App\Models\FileMirror;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MirrorFiles extends Command
{
    protected $signature = 'otfk:mirror-files
        {--limit=50 : Скільки файлів з черги обробити за запуск}
        {--retry-failed : Повернути в чергу файли зі статусом failed}
        {--verify : Перевірити наявність і хеш готових файлів; відсутні/пошкоджені знову завантажити}
        {--from= : Базовий URL старого хостингу (https://старий-домен): файли беруться з FROM/storage/PATH замість джерела}';

    protected $description = 'Завантаження файлів старого сайту з черги file_mirrors на публічний диск (і відновлення після переїзду)';

    /** Дозволені розширення: документи, архіви, зображення, медіа. SVG/HTML свідомо не дозволені (XSS з нашого домену). */
    public const EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pps', 'ppsx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
        'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'mp3', 'mp4', 'webm',
    ];

    private const MAX_ATTEMPTS = 3;

    public function handle(): int
    {
        $from = rtrim((string) $this->option('from'), '/');
        if ($from !== '' && ! preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $from)) {
            $this->error('--from має бути абсолютним http(s) URL старого хостингу.');

            return self::FAILURE;
        }

        if ($this->option('retry-failed')) {
            $n = FileMirror::query()->where('status', FileMirror::FAILED)->update(['status' => FileMirror::PENDING, 'attempts' => 0]);
            $this->line("Повернуто в чергу: {$n}");
        }

        if ($this->option('verify')) {
            $this->verify();
        }

        $ok = 0;
        $failed = 0;
        $rows = FileMirror::query()->pending()->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();

        foreach ($rows as $row) {
            $error = $this->mirror($row, $from);
            $error === null ? $ok++ : $failed++;
            if ($error !== null) {
                $this->warn("  #{$row->id}: {$error}");
            }
        }

        $left = FileMirror::query()->pending()->count();
        if ($rows->isNotEmpty() || $this->output->isVerbose()) {
            $this->info("Завантажено: {$ok}, помилок: {$failed}, у черзі лишилось: {$left}");
        }

        return self::SUCCESS;
    }

    /** Готові файли, яких немає на диску або хеш яких не збігається, знову ставляться в чергу. */
    private function verify(): void
    {
        $disk = Storage::disk('public');
        $requeued = 0;
        $checked = 0;

        FileMirror::query()->where('status', FileMirror::DONE)->orderBy('id')->chunkById(200, function ($rows) use ($disk, &$requeued, &$checked) {
            foreach ($rows as $row) {
                $checked++;
                if ($disk->exists($row->path) && hash_file('sha256', $disk->path($row->path)) === $row->sha256) {
                    continue;
                }
                $row->update(['status' => FileMirror::PENDING, 'attempts' => 0, 'error' => 'missing or changed on disk']);
                $requeued++;
            }
        });

        $this->line("Перевірено готових файлів: {$checked}, повернуто в чергу: {$requeued}");
    }

    /** Завантажує один файл. Повертає null при успіху або текст помилки. */
    private function mirror(FileMirror $row, string $from): ?string
    {
        $row->attempts++;

        try {
            $path = $row->path ?: $this->targetPath($row->source_url);
            // З --from файл береться зі старого хостингу за вже відомим шляхом, і хеш має збігтися з записаним.
            $url = $from !== '' && $row->path && $row->sha256
                ? $from.'/storage/'.$this->encodePath($row->path)
                : $this->encodeUrl($row->source_url);

            $tmp = $this->download($url, pathinfo($path, PATHINFO_EXTENSION));
            $sha = hash_file('sha256', $tmp);

            if ($from !== '' && $row->sha256 && $sha !== $row->sha256) {
                @unlink($tmp);
                throw new \RuntimeException('sha256 mismatch with recorded checksum');
            }

            $stream = fopen($tmp, 'rb');
            Storage::disk('public')->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $size = filesize($tmp);
            $mime = mime_content_type($tmp) ?: null;
            @unlink($tmp);

            $row->fill([
                'path' => $path, 'status' => FileMirror::DONE, 'error' => null,
                'size' => $size, 'sha256' => $sha, 'mime' => $mime, 'fetched_at' => now(),
            ])->save();

            return null;
        } catch (\Throwable $e) {
            $row->fill([
                'status' => $row->attempts >= self::MAX_ATTEMPTS ? FileMirror::FAILED : FileMirror::PENDING,
                'error' => Str::limit($e->getMessage(), 500),
            ])->save();

            return $e->getMessage();
        }
    }

    /** Відносний шлях на диску public: mirror/{host}/{шлях джерела}. Перевіряє хост, розширення і сегменти. */
    public function targetPath(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        $hosts = array_map('strtolower', config('services.file_mirror.hosts', []));

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! in_array($host, $hosts, true)) {
            throw new \RuntimeException("host not allowed: {$host}");
        }

        $segments = array_values(array_filter(explode('/', rawurldecode($parts['path'] ?? '')), fn ($s) => $s !== ''));
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || preg_match('#[\\\\\x00-\x1F]#', $segment)) {
                throw new \RuntimeException('unsafe path segment');
            }
        }

        $ext = strtolower(pathinfo(end($segments) ?: '', PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSIONS, true)) {
            throw new \RuntimeException("extension not allowed: .{$ext}");
        }

        return 'mirror/'.preg_replace('/^www\./', '', $host).'/'.implode('/', $segments);
    }

    /** Потокове завантаження у тимчасовий файл з лімітом розміру і перевіркою вмісту. */
    private function download(string $url, string $ext): string
    {
        $max = max(1, (int) config('services.file_mirror.max_mb', 100)) * 1024 * 1024;
        $response = Http::withoutVerifying()->timeout(180)->withOptions(['stream' => true])->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }
        if ((int) $response->header('Content-Length') > $max) {
            throw new \RuntimeException('file too large');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mirror');
        $out = fopen($tmp, 'wb');
        $body = $response->toPsrResponse()->getBody();
        $size = 0;
        while (! $body->eof()) {
            $chunk = $body->read(1024 * 1024);
            $size += strlen($chunk);
            if ($size > $max) {
                fclose($out);
                @unlink($tmp);
                throw new \RuntimeException('file too large');
            }
            fwrite($out, $chunk);
        }
        fclose($out);

        $head = (string) file_get_contents($tmp, false, null, 0, 512);
        if ($size === 0 || ! $this->contentMatches(strtolower($ext), $head)) {
            @unlink($tmp);
            throw new \RuntimeException('unexpected content (empty, HTML page or wrong format)');
        }

        return $tmp;
    }

    /** Магічні байти: сервер старого сайту замість відсутнього файлу може віддати HTML зі статусом 200. */
    private function contentMatches(string $ext, string $head): bool
    {
        $signatures = [
            'pdf' => ['%PDF'], 'jpg' => ["\xFF\xD8"], 'jpeg' => ["\xFF\xD8"], 'png' => ["\x89PNG"], 'gif' => ['GIF8'],
            'webp' => ['RIFF'], 'bmp' => ['BM'], 'docx' => ['PK'], 'xlsx' => ['PK'], 'pptx' => ['PK'], 'ppsx' => ['PK'],
            'odt' => ['PK'], 'ods' => ['PK'], 'odp' => ['PK'], 'zip' => ['PK'], 'rar' => ['Rar!'], '7z' => ["7z\xBC\xAF"],
            'doc' => ["\xD0\xCF\x11\xE0", '{\\rtf', 'PK'], 'xls' => ["\xD0\xCF\x11\xE0", 'PK'], 'ppt' => ["\xD0\xCF\x11\xE0"], 'pps' => ["\xD0\xCF\x11\xE0"],
        ];

        if (isset($signatures[$ext])) {
            foreach ($signatures[$ext] as $signature) {
                if (str_starts_with($head, $signature)) {
                    return true;
                }
            }

            return false;
        }

        return ! preg_match('/^\s*<(!doctype|html|head|body)\b/i', $head);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $path)));
    }

    /** URL джерела з кодуванням не-ASCII сегментів шляху (у старих URL трапляються кирилиця, пробіли, «‑»). */
    private function encodeUrl(string $url): string
    {
        $p = parse_url(trim($url));

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '')
            .$this->encodePath($p['path'] ?? '/')
            .(isset($p['query']) ? '?'.$p['query'] : '');
    }
}
