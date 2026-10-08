<?php

namespace Tests\Feature;

use App\Support\LazyMedia;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Локальні зображення HTML редактора без розмірів отримують природні width/height під час виводу:
 * браузер резервує місце — без зсуву макета й без «недольоту» переходу до якоря нижче.
 */
class LazyMediaDimensionsTest extends TestCase
{
    public function test_local_images_without_size_get_natural_dimensions(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(320, 180);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('mirror/otfk.od.ua/фото 1.png', ob_get_clean());

        $html = LazyMedia::render('<p><img src="/storage/mirror/otfk.od.ua/%D1%84%D0%BE%D1%82%D0%BE%201.png" alt=""></p>'
            .'<p><img src="/storage/missing.jpg" alt=""></p>'
            .'<p><img src="/storage/mirror/otfk.od.ua/%D1%84%D0%BE%D1%82%D0%BE%201.png" width="100" alt=""></p>'
            .'<p><img src="https://example.com/a.jpg" alt=""></p>');

        $this->assertStringContainsString('width="320" height="180"', $html);
        $this->assertStringContainsString('<img src="/storage/missing.jpg" alt="" loading="lazy" decoding="async">', $html);
        $this->assertStringContainsString('width="100" alt="" loading="lazy" decoding="async">', $html);
        $this->assertStringContainsString('<img src="https://example.com/a.jpg" alt="" loading="lazy" decoding="async">', $html);
    }
}
