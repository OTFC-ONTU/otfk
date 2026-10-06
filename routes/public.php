<?php

use App\Http\Controllers\BellScheduleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsFeedController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/novyny', [NewsController::class, 'index'])->name('news.index');
Route::get('/novyny/feed.xml', NewsFeedController::class)->name('news.feed');
Route::get('/novyny/{news:slug}', [NewsController::class, 'show'])->name('news.show');
Route::post('/novyny/{news:slug}/vpodobayka', [NewsController::class, 'like'])
    ->middleware('throttle:30,1')->name('news.like');

Route::get('/video', [VideoController::class, 'index'])->name('video.index');

Route::get('/rozklad-dzvinkiv', [BellScheduleController::class, 'index'])->name('bells');
Route::get('/podiyi', [EventController::class, 'index'])->name('events');
Route::get('/podiyi/{event}/ics', [EventController::class, 'ics'])->name('events.ics');

Route::get('/faq', [FaqController::class, 'index'])->name('faq');
Route::get('/kviz', [QuizController::class, 'index'])->name('quiz');

// Публічна інформація (документи)
Route::get('/dokumenty', [DocumentController::class, 'index'])->name('documents.index');
Route::get('/dokumenty/{documentCategory:slug}', [DocumentController::class, 'category'])->name('documents.category');

// Спеціальності
Route::get('/spetsialnosti', [SpecialtyController::class, 'index'])->name('specialties.index');
Route::get('/spetsialnosti/{specialty:slug}', [SpecialtyController::class, 'show'])->name('specialties.show');

// Структура та персонал
Route::get('/struktura', [StructureController::class, 'index'])->name('structure.index');
Route::get('/struktura/{department:slug}', [StructureController::class, 'show'])->name('structure.show');
Route::get('/personal/{staff:slug}', [StaffController::class, 'show'])->name('staff.show');

Route::get('/administratsiya', [StaffController::class, 'administration'])->name('staff.administration');

// Галерея
Route::get('/halereya', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/halereya/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');

// Пошук
Route::get('/poshuk', [SearchController::class, 'index'])->name('search');
Route::get('/poshuk/pidkazky', [SearchController::class, 'suggest'])
    ->middleware('throttle:60,1')->name('search.suggest');

Route::get('/kontakty', [ContactController::class, 'index'])->name('contacts');

/*
 | Динамічна редагована сторінка (catch-all). Реєструється ОСТАННЬОЮ та
 | виключає службові префікси, щоб не перехоплювати /admin, /livewire тощо.
 */
Route::get('/{page:slug}', [PageController::class, 'show'])
    ->where('page', '^(?!(?:en|admin|admin-preview|personal|livewire|novyny|video|kontakty|dokumenty|spetsialnosti|struktura|administratsiya|halereya|poshuk|sitemap|storage|up|build|vendor)$)[^/]+$')
    ->name('pages.show');
