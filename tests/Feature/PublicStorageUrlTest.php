<?php

namespace Tests\Feature;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Адреси файлів адмінки (Storage::url) будуються від хоста запиту: адмінка, відкрита через www,
 * інший порт або https при APP_URL з http, не отримує крос-доменних адрес, які браузер блокує.
 */
class PublicStorageUrlTest extends TestCase
{
    public function test_public_disk_url_follows_request_host(): void
    {
        PublicStorageUrl::useRequestHost(Request::create('https://www.just-test.shop/admin/news/1/edit'));
        Storage::forgetDisk('public');

        $this->assertSame('https://www.just-test.shop/storage/news/a.jpg', Storage::disk('public')->url('news/a.jpg'));

        PublicStorageUrl::useRequestHost(Request::create('http://localhost:8003/admin'));
        Storage::forgetDisk('public');

        $this->assertSame('http://localhost:8003/storage/news/a.jpg', Storage::disk('public')->url('news/a.jpg'));
    }
}
