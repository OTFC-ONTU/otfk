<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Превʼю форми доступне лише адміністратору.
Route::get('/admin-preview/{token}', [App\Http\Controllers\AdminPreviewController::class, 'show'])->name('admin.preview');

// Службові SEO-адреси спільні для всього сайту.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/robots.txt', function () {
    $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /livewire\n\nSitemap: ".url('/sitemap.xml')."\n";

    return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->name('robots');

// Одна карта маршрутів забезпечує однакові прив'язки моделей і захист форм.
Route::prefix('en')->name('en.')->group(base_path('routes/public.php'));

// Український catch-all залишається останнім у загальній карті маршрутів.
require __DIR__.'/public.php';
