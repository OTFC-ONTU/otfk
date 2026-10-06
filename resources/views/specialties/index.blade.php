<x-layouts.app title="{{ __('public.specialties') }}" description="Спеціальності Одеського технічного фахового коледжу ОНТУ: напрями підготовки, освітні ступені, терміни та форми навчання.">

    @php
        // Українське відмінювання слова «спеціальність» для лічильника
        $specialtyWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.specialties');
            }

            return match (true) {
                $mod10 === 1 => __('feature.specialty'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.specialties_2'),
                default => __('feature.specialties'),
            };
        };
    @endphp

    {{-- Світла шапка розділу — у стилі новин, відео та галереї --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.specialties')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                <div class="relative flex flex-wrap items-end justify-between gap-6">
                    <div class="max-w-3xl">
                        <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.our_specialties') }}</h1>
                        <div class="accent-rule"></div>
                        <p class="mt-5 text-lg leading-relaxed text-slate-500">
                            {{ __('public.specialties_intro') }}
                        </p>
                    </div>

                    @if ($specialties->isNotEmpty())
                        <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200/80">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700">
                                <x-ico name="academic-cap" class="h-6 w-6" />
                            </span>
                            <span class="leading-tight">
                                <span class="block text-2xl font-extrabold text-brand-950">{{ $specialties->count() }}</span>
                                <span class="block text-sm text-slate-500">{{ $specialtyWord($specialties->count()) }}</span>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-12">
        @if ($specialties->isNotEmpty())
            <div class="grid gap-6 md:grid-cols-2 2xl:gap-8">
                @foreach ($specialties as $sp)
                    <x-specialty-card :specialty="$sp" />
                @endforeach
            </div>

            {{-- Фінальний заклик до дії — заявка та квіз на вибір спеціальності --}}
            <div class="mt-12 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10 lg:mt-14">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <span class="hidden h-16 w-16 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-700 sm:grid">
                        <x-ico name="academic-cap" class="h-8 w-8" aria-hidden="true" />
                    </span>
                    <div class="max-w-2xl flex-1">
                        <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.ready_to_take_your_first_step_towards') }}</h2>
                        <p class="mt-2 text-slate-600">
                            {{ __('feature.choose_a_specialty_explore_the_details_and') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary">
                            {{ __('feature.contact_the_admissions_office_2') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('quiz') }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('feature.take_the_specialty_quiz') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        @else
            <x-empty-state icon="academic-cap" title="{{ __('public.no_specialties') }}" />
        @endif
    </section>

</x-layouts.app>
