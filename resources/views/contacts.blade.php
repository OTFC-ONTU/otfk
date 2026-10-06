<x-layouts.app title="{{ __('public.contacts') }}"
               description="{{ __('feature.contact_odesa_technical_professional_college_of_ontu') }}">

    @if ($page && filled($page->publicBody()))
        <section class="container-site py-8"><div class="prose-site">{!! \App\Support\FileCards::render(\App\Support\ResponsiveTables::render(\App\Support\LocalizedHtml::links($page->publicBody()))) !!}</div></section>
    @endif

    @php
        $s = \App\Models\Setting::publicMap();

        $phoneHref = ! empty($s['contact_phone']) ? 'tel:' . preg_replace('/[^+\d]/', '', $s['contact_phone']) : null;
        $mapSearch = ! empty($s['contact_address'])
            ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($s['contact_address'])
            : null;

        // Картки контактів — тільки заповнені в адмінці ключі
        $cards = array_values(array_filter([
            ['icon' => 'map-pin', 'label' => __('public.address'), 'value' => $s['contact_address'] ?? null, 'href' => $mapSearch, 'external' => true],
            ['icon' => 'phone', 'label' => __('public.phone'), 'value' => $s['contact_phone'] ?? null, 'href' => $phoneHref, 'external' => false],
            ['icon' => 'envelope', 'label' => __('public.email'), 'value' => $s['contact_email'] ?? null, 'href' => ! empty($s['contact_email']) ? 'mailto:' . $s['contact_email'] : null, 'external' => false],
            ['icon' => 'clock', 'label' => __('public.hours'), 'value' => $s['work_hours'] ?? null, 'href' => null, 'external' => false],
        ], fn ($card) => ! empty($card['value'])));

        $socials = array_values(array_filter([
            ['icon' => 'facebook', 'label' => 'Facebook', 'url' => $s['social_facebook'] ?? null],
            ['icon' => 'instagram', 'label' => 'Instagram', 'url' => $s['social_instagram'] ?? null],
            ['icon' => 'youtube', 'label' => 'YouTube', 'url' => $s['social_youtube'] ?? null],
        ], fn ($item) => ! empty($item['url'])));
    @endphp

    {{-- Світла шапка розділу — у стилі решти внутрішніх сторінок --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.contacts')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                <x-ico name="chat-bubble-left-right" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 ring-1 ring-brand-100">
                        <x-ico name="map-pin" class="h-4 w-4" aria-hidden="true" /> {{ __('public.contacts') }}
                    </span>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ __('feature.contact_the_college') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.questions_about_admissions_studying_or_documents_call') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($phoneHref)
                            <a href="{{ $phoneHref }}" class="btn-primary">
                                <x-ico name="phone" class="h-4 w-4" aria-hidden="true" /> {{ $s['contact_phone'] }}
                            </a>
                        @endif
                        @if (! empty($s['contact_email']))
                            <a href="mailto:{{ $s['contact_email'] }}" class="btn-outline border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                                <x-ico name="paper-airplane" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.send_an_email') }}
                            </a>
                        @endif
                        @if ($mapSearch)
                            <a href="{{ $mapSearch }}" target="_blank" rel="noopener" class="btn-outline">
                                <x-ico name="map" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.get_directions') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container-site space-y-10 py-10 lg:py-12">
        {{-- Картки контактів --}}
        @if ($cards)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cards as $card)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                            <x-ico :name="$card['icon']" class="h-5 w-5" aria-hidden="true" />
                        </span>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $card['label'] }}</p>
                        @if ($card['href'])
                            <a href="{{ $card['href'] }}" @if ($card['external']) target="_blank" rel="noopener" @endif
                               class="mt-1 block font-semibold leading-snug text-brand-950 hover:text-brand-700">{{ $card['value'] }}</a>
                        @else
                            <p class="mt-1 font-semibold leading-snug text-brand-950">{{ $card['value'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            @if ($socials)
                <div class="card p-6">
                    <h2 class="text-lg font-bold text-brand-950">{{ __('feature.our_social_media') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __('feature.college_news_and_student_life_every_day') }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                               class="inline-flex min-h-11 items-center gap-2 rounded-full bg-slate-50 px-4 text-sm font-semibold text-brand-800 ring-1 ring-slate-200 transition hover:bg-brand-50">
                                <x-brand-ico :name="$social['icon']" class="h-4 w-4" aria-hidden="true" /> {{ $social['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl bg-gold-50 p-6 ring-1 ring-gold-200/80">
                <h2 class="text-lg font-bold text-brand-950">{{ __('feature.planning_to_apply') }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    {{ __('feature.call_the_admissions_office_or_read_answers') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ \App\Support\LocalizedUrl::route('faq') }}" class="btn-accent">
                        {{ __('feature.frequently_asked_questions') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="btn-outline">
                        {{ __('public.our_specialties') }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>
            </div>
        </div>

        {{-- Карта --}}
        @if (! empty($s['map_embed']))
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-brand-950">{{ __('feature.how_to_find_us') }}</h2>
                        @if (! empty($s['contact_address']))
                            <p class="mt-0.5 text-sm text-slate-500">{{ $s['contact_address'] }}</p>
                        @endif
                    </div>
                    @if ($mapSearch)
                        <a href="{{ $mapSearch }}" target="_blank" rel="noopener"
                           class="inline-flex min-h-11 items-center gap-1.5 rounded-full px-4 text-sm font-semibold text-brand-700 ring-1 ring-slate-200 transition hover:bg-slate-50">
                            <x-ico name="arrow-top-right-on-square" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.open_in_google_maps') }}
                        </a>
                    @endif
                </div>
                <iframe src="{{ $s['map_embed'] }}" title="{{ __('feature.college_location_map') }}"
                        class="h-80 w-full lg:h-[26rem]" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        @endif
    </section>

</x-layouts.app>
