<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Support\Seo;

class StaffController extends Controller
{
    public function administration()
    {
        $staff = Staff::published()->administration()->ordered()->with('department')->get();

        // Директор — окремим блоком, решта групами (ролі виводяться з посади,
        // див. Staff::administration_role); якщо посад немає — усі в одній групі.
        $head = $staff->firstWhere('administration_role', 'head');

        $groups = collect([
            'head' => 'Керівництво коледжу',
            'deputy' => 'Заступники директора',
            'unit' => 'Керівники відділень та служб',
        ])
            ->map(fn (string $title, string $role) => [
                'title' => match ($role) {
                    'head' => __('feature.college_leadership'),
                    'deputy' => __('feature.deputy_directors'),
                    default => __('feature.department_and_service_heads'),
                },
                'items' => $staff->filter(fn (Staff $person) => $person->administration_role === $role
                    && ! ($head && $person->is($head))),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty());

        return view('staff.administration', compact('staff', 'head', 'groups'));
    }

    public function show(Staff $staff)
    {
        abort_unless($staff->is_published, 404);

        $staff->load('department');
        Seo::translation($staff);

        $colleagues = $staff->department_id
            ? Staff::published()->where('department_id', $staff->department_id)->whereKeyNot($staff->id)->ordered()->take(8)->get()
            : collect();

        return view('staff.show', compact('staff', 'colleagues'));
    }
}
