<?php

namespace App\Http\Controllers;

use App\Models\Department;

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

        return view('structure.index', compact('groups'));
    }

    public function show(Department $department)
    {
        abort_unless($department->is_published, 404);

        $department->load(['staff' => fn ($q) => $q->where('is_published', true)->orderBy('sort_order')]);

        return view('structure.show', compact('department'));
    }
}
