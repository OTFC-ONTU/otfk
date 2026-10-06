<x-layouts.app title="{{ __('public.faq') }}" description="{{ __('public.faq_description') }}">

    @php
        $s = \App\Models\Setting::publicMap();

        // Українське відмінювання слова «питання» для лічильника
        $questionWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.questions_2');
            }

            return match (true) {
                $mod10 >= 1 && $mod10 <= 4 => __('feature.questions'),
                default => __('feature.questions_2'),
            };
        };

        // Пошуковий «стіг сіна» для кожного питання — фільтрація йде на клієнті, без запитів
        $haystacks = $faqs->map(fn ($f) => mb_strtolower($f->localized('question').' '.$f->localized('answer')))->values()->all();

        $hasContacts = ! empty($s['contact_phone']) || ! empty($s['contact_email'])
            || ! empty($s['contact_address']) || ! empty($s['work_hours']);

        $socials = array_filter([
            'facebook' => $s['social_facebook'] ?? null,
            'instagram' => $s['social_instagram'] ?? null,
            'youtube' => $s['social_youtube'] ?? null,
        ]);
    @endphp

    {{-- Розмітка FAQPage для розширених результатів Google --}}
    @if ($faqs->isNotEmpty())
        @php
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f->localized('question'),
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->localized('answer')],
                ])->values()->all(),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    {{-- Світла шапка розділу — у стилі новин, відео, галереї та спеціальностей --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.applicants'), 'url' => \App\Support\LocalizedUrl::to('/abituriyentu')],
                ['label' => __('public.faq')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="chat-bubble-left-right" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.faq') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.find_answers_to_common_questions_about_admissions') }}
                    </p>
                    @if ($faqs->isNotEmpty())
                        <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-sm font-semibold text-gold-700 ring-1 ring-gold-300/70">
                            <x-ico name="question-mark-circle" class="h-4 w-4" />
                            {{ $faqs->count() }} {{ $questionWord($faqs->count()) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-10 lg:py-14">
        @if ($faqs->isEmpty())
            <x-empty-state icon="question-mark-circle" title="{{ __('public.no_faq') }}" />
        @else
            <div class="grid gap-8 lg:grid-cols-3 lg:items-start">

                {{-- Пошук + акордеон питань --}}
                <div class="lg:col-span-2"
                     x-data="{
                         q: '',
                         open: null,
                         items: @js($haystacks),
                         get needle() { return this.q.trim().toLowerCase() },
                         match(i) { return this.needle === '' || this.items[i].includes(this.needle) },
                         get found() {
                             return this.needle === ''
                                 ? this.items.length
                                 : this.items.filter(t => t.includes(this.needle)).length
                         },
                     }">

                    {{-- Живий пошук по питаннях і відповідях (без перезавантаження сторінки) --}}
                    <div class="relative">
                        <x-ico name="magnifying-glass" aria-hidden="true"
                               class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                        <label for="faq-search" class="sr-only">{{ __('feature.search_questions') }}</label>
                        <input id="faq-search" type="search" x-model="q" placeholder="{{ __('feature.search_questions_2') }}"
                               autocomplete="off"
                               class="w-full rounded-xl border-0 bg-white py-3.5 pl-12 pr-4 text-base text-slate-900 shadow-sm ring-1 ring-slate-200/80 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-400">
                    </div>

                    <p class="mt-3 text-center text-sm text-slate-500">
                        <span x-show="needle === ''">{{ __('feature.type_a_keyword_to_find_an_answer') }}</span>
                        <span x-show="needle !== ''" x-cloak>
                            {{ __('feature.found') }} <span class="font-semibold text-brand-800" x-text="found"></span>
                            <span x-text="found === 1 ? @js(__('feature.questions')) : (found >= 2 && found <= 4 ? @js(__('feature.questions')) : @js(__('feature.questions_2')))"></span>
                        </span>
                    </p>

                    {{-- Дві колонки лише коли питань достатньо — інакше сітка виглядає дірявою --}}
                    <div @class(['mt-6 grid gap-4', 'xl:grid-cols-2 xl:items-start' => $faqs->count() >= 4])>
                        @foreach ($faqs as $i => $faq)
                            <div x-show="match({{ $i }})"
                                 class="overflow-hidden rounded-2xl bg-white shadow-sm transition"
                                 :class="open === {{ $i }} ? 'ring-2 ring-gold-400' : 'ring-1 ring-slate-200/80'">
                                <h2>
                                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                                            :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                                            aria-controls="faq-answer-{{ $i }}"
                                            class="flex w-full items-start gap-3 px-5 py-4 text-left">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 transition"
                                              :class="open === {{ $i }} && 'bg-gold-100 text-gold-700'">{{ $i + 1 }}</span>
                                        <span class="flex-1 font-semibold text-slate-900">{{ $faq->localized('question') }}</span>
                                        <span class="mt-0.5 shrink-0 text-slate-400 transition"
                                              :class="open === {{ $i }} && 'rotate-180 text-gold-600'">
                                            <x-ico name="chevron-down" class="h-5 w-5" aria-hidden="true" />
                                        </span>
                                    </button>
                                </h2>
                                <div id="faq-answer-{{ $i }}" x-show="open === {{ $i }}" x-transition.opacity.duration.200ms x-cloak>
                                    <div class="border-t border-slate-100 px-5 py-4 pl-14 text-sm leading-relaxed text-slate-600">
                                        {!! nl2br(e($faq->localized('answer'))) !!}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Порожній результат пошуку --}}
                    <div x-show="found === 0" x-cloak
                         class="mt-6 rounded-2xl bg-slate-50 px-6 py-10 text-center ring-1 ring-slate-200/80">
                        <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-white text-slate-400 ring-1 ring-slate-200">
                            <x-ico name="magnifying-glass" class="h-6 w-6" />
                        </span>
                        <p class="mt-4 font-semibold text-brand-950">{{ __('feature.no_results_match_your_search') }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ __('feature.try_another_keyword_or_ask_the_admissions') }}</p>
                        <button type="button" @click="q = ''" class="btn-outline mt-5">{{ __('feature.clear_search') }}</button>
                    </div>
                </div>

                {{-- Бічна колонка — приймальна комісія завжди поруч --}}
                <aside class="space-y-6 lg:sticky lg:top-24">
                    <div class="rounded-2xl bg-brand-50/70 p-6 text-center ring-1 ring-brand-100">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-white text-brand-700 shadow-sm ring-1 ring-brand-100">
                            <x-ico name="chat-bubble-left-right" class="h-8 w-8" aria-hidden="true" />
                        </span>
                        <h2 class="mt-4 text-xl font-extrabold text-brand-950">{{ __('public.unanswered') }}</h2>
                        <p class="mt-2 font-semibold text-brand-800">{{ __('feature.contact_the_admissions_office') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('feature.contact_us_and_we_will_be_happy') }}</p>

                        @if ($hasContacts)
                            <ul class="mt-5 space-y-3 border-t border-brand-100 pt-5 text-left text-sm text-slate-600">
                                @if (! empty($s['contact_phone']))
                                    <li class="flex gap-3">
                                        <x-ico name="phone" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" />
                                        <span>
                                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_phone'] }}</a>
                                            <span class="block text-xs text-slate-500">{{ __('feature.calls_during_office_hours') }}</span>
                                        </span>
                                    </li>
                                @endif
                                @if (! empty($s['contact_email']))
                                    <li class="flex gap-3">
                                        <x-ico name="envelope" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" />
                                        <span>
                                            <a href="mailto:{{ $s['contact_email'] }}" class="break-all font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_email'] }}</a>
                                            <span class="block text-xs text-slate-500">{{ __('feature.we_will_reply_to_your_email') }}</span>
                                        </span>
                                    </li>
                                @endif
                                @if (! empty($s['contact_address']))
                                    <li class="flex gap-3">
                                        <x-ico name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" />
                                        <span>{{ $s['contact_address'] }}</span>
                                    </li>
                                @endif
                                @if (! empty($s['work_hours']))
                                    <li class="flex gap-3">
                                        <x-ico name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" />
                                        <span>{{ $s['work_hours'] }}</span>
                                    </li>
                                @endif
                            </ul>
                        @endif

                        <div class="mt-6 space-y-3">
                            <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-accent w-full">
                                {{ __('public.contacts') }} <x-ico name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>

                    @if (! empty($socials))
                        <div class="rounded-2xl bg-white p-6 ring-1 ring-slate-200/80">
                            <p class="flex gap-3 text-sm leading-relaxed text-slate-600">
                                <x-ico name="information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-brand-400" />
                                <span>{{ __('feature.follow_admission_news_and_college_events_on') }}</span>
                            </p>
                            <div class="mt-4 flex justify-center gap-3">
                                @foreach ($socials as $network => $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       aria-label="{{ __('feature.network_college', ['network' => ucfirst($network)]) }}"
                                       class="grid h-11 w-11 place-items-center rounded-full bg-brand-50 text-brand-700 transition hover:bg-brand-100 hover:text-brand-800">
                                        <x-brand-ico :name="$network" class="h-5 w-5" />
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </aside>
            </div>

            {{-- Фінальна смуга — прямий шлях до приймальної комісії --}}
            <div class="mt-12 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10 lg:mt-14">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <span class="hidden h-16 w-16 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-700 sm:grid">
                        <x-ico name="building-library" class="h-8 w-8" aria-hidden="true" />
                    </span>
                    <div class="max-w-2xl flex-1">
                        <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.questions_about_admissions') }}</h2>
                        <p class="mt-2 text-slate-600">
                            {{ __('feature.contact_the_admissions_office_for_help_choosing') }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-accent">
                            {{ __('feature.contact_the_admissions_office_2') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="btn-outline">
                            {{ __('public.our_specialties') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </section>

</x-layouts.app>
