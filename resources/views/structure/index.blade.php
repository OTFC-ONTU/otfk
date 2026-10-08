<x-layouts.app title="{{ __('public.structure') }}" description="{{ __('feature.meta_structure_description') }}">

    @php
        // Українське відмінювання лічильників
        $plural = function (int $n, string $one, string $few, string $many): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return $many;
            }

            return match (true) {
                $mod10 === 1 => $one,
                $mod10 >= 2 && $mod10 <= 4 => $few,
                default => $many,
            };
        };

        $unitWord = fn (int $n) => $plural($n, __('feature.department'), __('feature.departments'), __('feature.departments_2'));
        $staffWord = fn (int $n) => $plural($n, __('feature.staff_member'), __('feature.staff_members_2'), __('feature.staff_members'));

        // Іконка групи: типів рівно три (Department::TYPES), решта — запасний варіант
        $typeIcon = fn (string $type) => match ($type) {
            'viddilennya' => 'building-office-2',
            'tsyklova-komisiya' => 'user-group',
            'kafedra' => 'academic-cap',
            default => 'building-office-2',
        };

        // Department::TYPES зберігає назву в однині (вона потрібна як бейдж на картці
        // підрозділу) — для заголовків груп беремо множину
        $groupTitle = fn (string $type, string $label) => match ($type) {
            'tsyklova-komisiya' => __('feature.subject_commissions'),
            'kafedra' => __('feature.academic_departments'),
            default => $label,
        };

        $totalUnits = collect($groups)->sum(fn ($group) => $group['items']->count());
        $totalStaff = collect($groups)->sum(fn ($group) => $group['items']->sum('staff_count'));
    @endphp

    {{-- Світла шапка розділу — у стилі новин, відео, галереї, спеціальностей та подій --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.structure')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур будівлі — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="building-office-2" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.college_structure') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.explore_the_college_departments_and_subject_commissions') }}
                    </p>

                    @if ($totalUnits)
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                <x-ico name="building-office-2" class="h-4 w-4" aria-hidden="true" />
                                {{ $totalUnits }} {{ $unitWord($totalUnits) }}
                            </span>
                            @if ($totalStaff)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100">
                                    <x-ico name="users" class="h-4 w-4" aria-hidden="true" />
                                    {{ $totalStaff }} {{ $staffWord($totalStaff) }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if (count($groups))
        {{-- Навігація по групах: посилання-якорі замість простого списку секцій --}}
        <nav aria-label="{{ __('feature.department_groups') }}" class="border-b border-slate-200/70 bg-white">
            <div class="container-site flex flex-wrap gap-3 py-4">
                @foreach ($groups as $type => $group)
                    <a href="#{{ $type }}"
                       class="group inline-flex items-center gap-2.5 rounded-full bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:py-2 ring-1 ring-slate-200 transition hover:bg-brand-950 hover:text-white hover:ring-brand-950">
                        <x-ico :name="$typeIcon($type)" class="h-4 w-4 text-brand-500 transition group-hover:text-gold-400" aria-hidden="true" />
                        {{ $groupTitle($type, $group['label']) }}
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-brand-800 ring-1 ring-slate-200 transition group-hover:bg-white/15 group-hover:text-white group-hover:ring-white/20">
                            {{ $group['items']->count() }}
                        </span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <section class="container-site space-y-14 py-12 lg:space-y-16">
        @forelse ($groups as $type => $group)
            <div id="{{ $type }}" class="scroll-mt-28">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-950 text-gold-400">
                            <x-ico :name="$typeIcon($type)" class="h-6 w-6" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 class="text-2xl font-extrabold text-brand-950">{{ $groupTitle($type, $group['label']) }}</h2>
                            <div class="accent-rule"></div>
                        </div>
                    </div>
                    <span class="badge bg-brand-50 text-brand-800 ring-1 ring-brand-100">
                        {{ $group['items']->count() }} {{ $unitWord($group['items']->count()) }}
                    </span>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4 2xl:gap-6">
                    @foreach ($group['items'] as $dep)
                        <x-department-card :department="$dep" />
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty-state icon="building-office-2" title="{{ __('public.no_structure') }}" />
        @endforelse

        @if (count($groups))
            {{-- Фінальний заклик до дії: куди йти, якщо потрібного підрозділу тут немає --}}
            <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <span class="hidden h-16 w-16 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-700 sm:grid">
                        <x-ico name="question-mark-circle" class="h-8 w-8" aria-hidden="true" />
                    </span>
                    <div class="max-w-2xl flex-1">
                        <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.cannot_find_the_department_you_need') }}</h2>
                        <p class="mt-2 text-slate-600">
                            {{ __('feature.find_college_leaders_on_the_administration_page') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('staff.administration') }}" class="btn-primary">
                            {{ __('public.administration') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('public.contacts') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </section>

</x-layouts.app>
