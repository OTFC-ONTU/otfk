<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Адреси файлів диска public відносні (/storage/…): поля файлів і вкладення редактора працюють
 * на будь-якому хості адмінки без CORS, а збережений HTML не прив'язується до хоста (піддомен,
 * тестовий хостинг) — після переключення домену картинки не вказують на старий хост.
 */
class PublicStorageUrlTest extends TestCase
{
    public function test_public_disk_urls_are_relative(): void
    {
        $this->assertSame('/storage/news/a.jpg', Storage::disk('public')->url('news/a.jpg'));
    }
}
