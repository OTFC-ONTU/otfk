<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Page;

class StructureController extends Controller
{
    public function index()
    {
        $groups = [];
        foreach (Department::TYPES as $type => $label) {
            $items = Department::published()->where('type', $type)->ordered()->withCount('staff')->get();
            if ($items->isNotEmpty()) {
                $groups[$type] = ['label' => __('public.department_'.str_replace('-', '_', $type)), 'items' => $items];
            }
        }

        // Вступ і рейтингове оцінювання викладачів зі сторінки «Циклові комісії» оригіналу (CMS-сторінка ciklovi-komisiyi)
        $commissionPage = Page::published()->where('slug', 'ciklovi-komisiyi')->first();

        return view('structure.index', compact('groups', 'commissionPage'));
    }

    public function show(Department $department)
    {
        // Чернетки бачать лише залогінені адміністратори (превʼю з адмінки).
        abort_unless($department->is_published || auth()->check(), 404);

        $department->load(['staff' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')->with(['profilePage', 'qualificationPage'])]);

        // Блок «Інші підрозділи» — сусіди тієї ж групи (відділення / комісія / кафедра)
        $others = Department::published()
            ->where('type', $department->type)
            ->whereKeyNot($department->getKey())
            ->ordered()
            ->withCount('staff')
            ->take(4)
            ->get();

        return view('structure.show', compact('department', 'others'));
    }
}
