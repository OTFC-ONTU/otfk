<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Support\SearchQuery;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class DocumentController extends Controller
{
    /** Скільки документів показуємо на одній сторінці категорії */
    private const PER_PAGE = 20;

    public function index()
    {
        $categories = DocumentCategory::ordered()
            ->withCount(['documents' => fn ($q) => $q->published()])
            ->get();

        return view('documents.index', [
            'categories' => $categories,
            'documentsCount' => $categories->sum('documents_count'),
        ]);
    }

    public function category(Request $request, DocumentCategory $documentCategory)
    {
        // Пошук по назві: у найбільшій категорії майже сотня PDF з довгими назвами.
        // Фільтруємо в PHP, бо LIKE/LOWER у SQLite не бачить регістру кирилиці,
        // а документів у категорії щонайбільше кілька сотень.
        $search = SearchQuery::from($request);

        Seo::translation($documentCategory);
        $all = $documentCategory->documents()->published()->get();
        $totalCount = $all->count();

        if ($search !== '') {
            $all = $all->filter(fn ($doc) => mb_stripos($doc->localized('title'), $search) !== false)->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage();

        $documents = new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => array_filter(['q' => $search]),
            ]
        );

        $categories = DocumentCategory::ordered()
            ->withCount(['documents' => fn ($q) => $q->published()])
            ->get();

        return view('documents.category', [
            'category' => $documentCategory,
            'documents' => $documents,
            'categories' => $categories,
            'search' => $search,
            'totalCount' => $totalCount,
            // Повний текст розділу як на оригіналі (таблиці, зображення, підзаголовки) — з прив'язаної CMS-сторінки
            'sectionPage' => $documentCategory->sectionPage(),
        ]);
    }
}
