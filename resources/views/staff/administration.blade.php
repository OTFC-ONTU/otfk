<x-layouts.app title="{{ __('public.administration') }}"
               description="Керівництво Одеського технічного фахового коледжу ОНТУ: директор, заступники директора та завідувачі відділень.">

    @php
        $s = \App\Models\Setting::publicMap();

        // Українське відмінювання лічильника
        $leaderWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.leaders');
            }

            return match (true) {
                $mod10 === 1 => __('feature.leader'),
                $mod10 >= 2 && $mod10 <= 4 => __('feature.leaders_2'),
                default => __('feature.leaders'),
            };
        };

        $total = $staff->count();
        $headBio = $head ? \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(strip_tags((string) $head->bio)), 320) : null;
        $hasContacts = ! empty($s['contact_address']) || ! empty($s['contact_phone'])
            || ! empty($s['contact_email']) || ! empty($s['work_hours']);
    @endphp

    {{-- Світла шапка розділу — у стилі новин, структури, спеціальностей та подій --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.administration')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="user-group" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.college_administration') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.meet_the_leadership_team_responsible_for_college') }}
                    </p>

                    @if ($total)
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                <x-ico name="users" class="h-4 w-4" aria-hidden="true" />
                                {{ $total }} {{ $leaderWord($total) }}
                            </span>
                            @if (! empty($s['contact_phone']))
                                <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 font-semibold text-brand-800 ring-1 ring-brand-100 transition hover:bg-brand-100">
                                    <x-ico name="phone" class="h-4 w-4" aria-hidden="true" />
                                    {{ __('feature.reception_phone', ['phone' => $s['contact_phone']]) }}
                                </a>
                            @endif
                            @if (! empty($s['work_hours']))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700 ring-1 ring-slate-200">
                                    <x-ico name="clock" class="h-4 w-4" aria-hidden="true" />
                                    {{ $s['work_hours'] }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site space-y-12 py-12 lg:space-y-14">
        @if ($head)
            {{-- Директор — окремим блоком над рештою керівництва --}}
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80">
                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-5 lg:gap-10 lg:p-10">
                    <div class="lg:col-span-3">
                        <div class="flex flex-wrap items-center gap-5 sm:flex-nowrap">
                            @if ($head->photo)
                                <x-picture :path="$head->photo" :alt="$head->localized('full_name')" decoding="async"
                                           class="h-24 w-24 shrink-0 rounded-2xl object-cover ring-4 ring-brand-50 sm:h-28 sm:w-28" />
                            @else
                                <span aria-hidden="true"
                                      class="grid h-24 w-24 shrink-0 place-items-center rounded-2xl bg-brand-950 text-3xl font-bold text-white ring-4 ring-brand-50 sm:h-28 sm:w-28">
                                    {{ $head->initials() ?: '—' }}
                                </span>
                            @endif

                            <div class="min-w-0">
                                <span class="badge bg-gold-50 text-gold-700 ring-1 ring-gold-300/70">{{ __('feature.college_director') }}</span>
                                <h2 class="mt-2 text-2xl font-extrabold leading-tight text-brand-950 sm:text-3xl">
                                    <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $head) }}" class="transition hover:text-brand-700">{{ $head->localized('full_name') }}</a>
                                </h2>
                                @if ($head->localized('position'))
                                    <p class="mt-2 font-semibold text-gold-700">{{ $head->localized('position') }}</p>
                                @endif
                                @if ($head->localized('academic_degree'))
                                    <p class="mt-1 text-sm text-slate-400">{{ $head->localized('academic_degree') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            @if ($head->phone)
                                <a href="tel:{{ preg_replace('/[^+\d]/', '', $head->phone) }}" class="btn-primary">
                                    <x-ico name="phone" class="h-4 w-4" aria-hidden="true" /> {{ $head->phone }}
                                </a>
                            @endif
                            @if ($head->email)
                                <a href="mailto:{{ $head->email }}" class="btn-outline">
                                    <x-ico name="envelope" class="h-4 w-4" aria-hidden="true" /> {{ $head->email }}
                                </a>
                            @endif
                            <a href="{{ \App\Support\LocalizedUrl::route('staff.show', $head) }}" class="btn-outline">
                                {{ __('feature.directors_profile') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                            </a>
                        </div>
                    </div>

                    @if ($headBio)
                        <div class="border-t border-slate-100 pt-6 lg:col-span-2 lg:border-l lg:border-t-0 lg:pl-10 lg:pt-0">
                            <p class="text-base leading-relaxed text-slate-600">{{ $headBio }}</p>
                            @if ($head->department)
                                <a href="{{ \App\Support\LocalizedUrl::route('structure.show', $head->department) }}"
                                   class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:text-brand-600">
                                    <x-ico name="building-office-2" class="h-4 w-4" aria-hidden="true" /> {{ $head->department->localized('title') }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @forelse ($groups as $group)
            <div>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-extrabold text-brand-950">{{ $group['title'] }}</h2>
                        <div class="accent-rule"></div>
                    </div>
                    <span class="badge bg-brand-50 text-brand-800 ring-1 ring-brand-100">
                        {{ $group['items']->count() }} {{ $leaderWord($group['items']->count()) }}
                    </span>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:gap-6">
                    @foreach ($group['items'] as $person)
                        <x-leader-card :person="$person" />
                    @endforeach
                </div>
            </div>
        @empty
            @unless ($head)
                <x-empty-state icon="users" title="{{ __('public.no_administration') }}" />
            @endunless
        @endforelse

        @if ($hasContacts)
            {{-- Контакти приймальні — усі значення з налаштувань сайту --}}
            <div class="rounded-2xl bg-slate-50 px-6 py-8 ring-1 ring-slate-200/80 sm:px-8">
                <h2 class="text-xl font-extrabold text-brand-950">{{ __('feature.office_contacts') }}</h2>
                <div class="accent-rule"></div>

                <dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['map-pin', __('public.address'), $s['contact_address'] ?? null, null],
                        ['phone', __('public.phone'), $s['contact_phone'] ?? null, 'tel'],
                        ['envelope', 'E-mail', $s['contact_email'] ?? null, 'mailto'],
                        ['clock', __('feature.office_hours'), $s['work_hours'] ?? null, null],
                    ] as [$icon, $label, $value, $link])
                        @if (! empty($value))
                            <div class="flex gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-brand-800 ring-1 ring-slate-200">
                                    <x-ico :name="$icon" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-slate-700">
                                        @if ($link === 'tel')
                                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $value) }}" class="text-brand-800 transition hover:text-brand-600">{{ $value }}</a>
                                        @elseif ($link === 'mailto')
                                            <a href="mailto:{{ $value }}" class="break-all text-brand-800 transition hover:text-brand-600">{{ $value }}</a>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </dd>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- Фінальний заклик до дії --}}
        <div class="overflow-hidden rounded-2xl bg-brand-950 px-6 py-8 sm:px-10">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-extrabold text-white">{{ __('feature.need_admission_advice') }}</h2>
                    <p class="mt-2 text-brand-100">
                        {{ __('feature.contact_the_admissions_office_for_information_about') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-accent">
                        {{ __('feature.college_contacts') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
