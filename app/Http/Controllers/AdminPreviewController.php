<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Support\AdminPreview;
use Illuminate\Support\Str;

/**
 * Превʼю незбереженої форми з адмінки: рендерить публічний шаблон
 * сторінки/новини/спеціальності/підрозділу з даними знімка
 * (App\Support\AdminPreview), без запису в БД. Доступ — лише залогіненим
 * адмінам, інакше 404 (як і чернетки).
 */
class AdminPreviewController extends Controller
{
    public function show(string $token)
    {
        abort_unless(auth()->check(), 404);

        $snapshot = AdminPreview::get($token);
        abort_unless($snapshot !== null, 404);

        return match ($snapshot['type']) {
            'page' => $this->page($snapshot['attributes']),
            'news' => $this->news($snapshot['attributes']),
            'specialty' => $this->specialty($snapshot['attributes']),
            'department' => $this->department($snapshot['attributes']),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $attributes */
    private function page(array $attributes)
    {
        $page = new Page;
        $page->forceFill($attributes);

        // Нова сторінка без id: інакше children()/siblings шукали б parent_id = NULL
        // і превʼю помилково рендерилось би хабом з усіма кореневими сторінками.
        $page->id = $page->id ?? 0;
        $page->slug = $page->slug ?: (Str::slug((string) $page->title) ?: 'preview');

        return view('pages.show', ['page' => $page, 'adminPreview' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function news(array $attributes)
    {
        $news = new News;
        $news->forceFill($attributes);
        $news->id = $news->id ?? 0;
        $news->slug = $news->slug ?: (Str::slug((string) $news->title) ?: 'preview');

        return view('news.show', [
            'news' => $news,
            'related' => News::published()->recent()->whereKeyNot($news->id)->limit(5)->get(),
            'liked' => false,
            'prev' => null,
            'next' => null,
            'abiturientLinks' => collect(),
            'adminPreview' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function specialty(array $attributes)
    {
        $specialty = new Specialty;
        $specialty->forceFill($attributes);
        $specialty->id = $specialty->id ?? 0;
        $specialty->slug = $specialty->slug ?: (Str::slug((string) $specialty->title) ?: 'preview');

        // Для наявного запису підтягнуться його ОПП, для нового (id=0) — порожньо.
        $specialty->load('programs');

        return view('specialties.show', [
            'specialty' => $specialty,
            'others' => Specialty::published()->ordered()->whereKeyNot($specialty->id)->get(),
            'adminPreview' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function department(array $attributes)
    {
        $department = new Department;
        $department->forceFill($attributes);
        $department->id = $department->id ?? 0;
        $department->slug = $department->slug ?: (Str::slug((string) $department->title) ?: 'preview');

        $department->load(['staff' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')]);

        $others = Department::published()
            ->where('type', $department->type)
            ->whereKeyNot($department->id)
            ->ordered()
            ->withCount('staff')
            ->take(4)
            ->get();

        return view('structure.show', [
            'department' => $department,
            'others' => $others,
            'adminPreview' => true,
        ]);
    }
}
