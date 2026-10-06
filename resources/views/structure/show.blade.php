<x-layouts.app :title="$department->localized('title')" :description="\Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $department->localized('description')))), 160)">

    @if (! empty($adminPreview))
        <x-draft-notice message="{{ __('feature.preview_of_this_department_changes_are_unsaved') }}" />
    @elseif (! $department->is_published)
        <x-draft-notice />
    @endif

    @php
        // Українське відмінювання лічильника співробітників
        $staffWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.staff_members');
            }

            return match (true) {
                $mod10 === 1 => __('feature.staff_member'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.staff_members_2'),
                default => __('feature.staff_members'),
            };
        };

        // Іконка групи: типів рівно три (Department::TYPES), решта — запасний варіант
        $icon = match ($department->type) {
            'viddilennya' => 'building-office-2',
            'tsyklova-komisiya' => 'user-group',
            'kafedra' => 'academic-cap',
            default => 'building-office-2',
        };

        $staffCount = $department->staff->count();
    @endphp

    {{-- Світла шапка-картка — у стилі детальної новини та інших внутрішніх сторінок --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.structure'), 'url' => \App\Support\LocalizedUrl::route('structure.index')],
                ['label' => $department->localized('title')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур типу підрозділу — заповнює порожнечу праворуч --}}
                <x-ico :name="$icon" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <span class="badge bg-brand-50 text-brand-800 ring-1 ring-brand-100">
                        <x-ico :name="$icon" class="h-4 w-4" aria-hidden="true" />
                        {{ $department->type_label }}
                    </span>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ $department->localized('title') }}</h1>
                    <div class="accent-rule"></div>

                    @if ($staffCount)
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                <x-ico name="users" class="h-4 w-4" aria-hidden="true" />
                                {{ $staffCount }} {{ $staffWord($staffCount) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site grid gap-10 py-12 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if (filled($department->localized('description')))
                <x-prose.article :drop-cap="false">
                    {!! \App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($department->localized('description')))) !!}
                </x-prose.article>
            @elseif ($department->staff->isEmpty())
                <x-empty-state icon="building-office-2" title="{{ __('public.no_department') }}" />
            @endif
        </div>

        {{-- Липкий сайдбар: коротка довідка та швидкі переходи --}}
        <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
            <div class="card p-6">
                <h2 class="text-lg font-bold text-brand-950">{{ __('feature.about_this_department') }}</h2>
                <div class="accent-rule"></div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex items-start gap-3">
                        <x-ico :name="$icon" class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" aria-hidden="true" />
                        <div>
                            <dt class="text-slate-500">{{ __('feature.department_type') }}</dt>
                            <dd class="font-semibold text-slate-800">{{ $department->type_label }}</dd>
                        </div>
                    </div>
                    @if ($staffCount)
                        <div class="flex items-start gap-3">
                            <x-ico name="users" class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" aria-hidden="true" />
                            <div>
                                <dt class="text-slate-500">{{ __('public.department_staff') }}</dt>
                                <dd class="font-semibold text-slate-800">{{ $staffCount }} {{ $staffWord($staffCount) }}</dd>
                            </div>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="card p-6">
                <h2 class="text-lg font-bold text-brand-950">{{ __('feature.quick_links') }}</h2>
                <div class="accent-rule"></div>
                <ul class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        ['building-office-2', __('feature.all_college_departments'), \App\Support\LocalizedUrl::route('structure.index')],
                        ['user-group', __('public.administration'), \App\Support\LocalizedUrl::route('staff.administration')],
                        ['academic-cap', __('public.specialties'), \App\Support\LocalizedUrl::route('specialties.index')],
                        ['envelope', __('public.contacts'), \App\Support\LocalizedUrl::route('contacts')],
                    ] as [$linkIcon, $label, $url])
                        <li>
                            <a href="{{ $url }}" class="flex items-center gap-2.5 font-semibold text-slate-700 transition hover:text-brand-700">
                                <x-ico :name="$linkIcon" class="h-4 w-4 shrink-0 text-brand-500" aria-hidden="true" />
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </section>

    @if ($department->staff->isNotEmpty())
        <section class="border-t border-slate-200/70 bg-slate-50/60">
            <div class="container-site py-12">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-extrabold text-brand-950">{{ __('public.department_staff') }}</h2>
                        <div class="accent-rule"></div>
                    </div>
                    <span class="badge bg-white text-brand-800 ring-1 ring-brand-100">
                        {{ $staffCount }} {{ $staffWord($staffCount) }}
                    </span>
                </div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($department->staff as $person)
                        <x-staff-card :person="$person" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="container-site py-12">
        @if ($others->isNotEmpty())
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.other_departments') }}</h2>
                    <div class="accent-rule"></div>
                </div>
                <a href="{{ \App\Support\LocalizedUrl::route('structure.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                    {{ __('feature.all_departments') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                </a>
            </div>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4 2xl:gap-6">
                @foreach ($others as $other)
                    <x-department-card :department="$other" />
                @endforeach
            </div>
        @endif

        <a href="{{ \App\Support\LocalizedUrl::route('structure.index') }}" class="btn-outline mt-10"><x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_structure') }}</a>
    </section>

</x-layouts.app>
