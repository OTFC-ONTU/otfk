<x-layouts.app title="{{ __('public.quiz') }}" description="{{ __('feature.meta_quiz_description') }}">

    @php
        $s = \App\Models\Setting::publicMap();

        // Українське відмінювання слова «питання» для лічильника
        $questionWord = function (int $n): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;

            if ($mod100 >= 11 && $mod100 <= 14) {
                return __('feature.questions_2');
            }

            return $mod10 >= 1 && $mod10 <= 4 ? __('feature.questions') : __('feature.questions_2');
        };

        $ready = $questions->isNotEmpty() && $specialties->isNotEmpty();
        $count = $questions->count();

        // Спеціальність за замовчуванням — коли жоден варіант не дав балів
        $fallbackId = $specialties->first()?->id ?? 0;

        $hasContacts = ! empty($s['contact_phone']) || ! empty($s['contact_email']);
    @endphp

    {{-- Світла шапка розділу — у стилі новин, відео, галереї, спеціальностей та FAQ --}}
    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.applicants'), 'url' => \App\Support\LocalizedUrl::to('/abituriyentu')],
                ['label' => __('public.quiz_breadcrumb')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                {{-- Декоративний контур — заповнює порожнечу праворуч на великих екранах --}}
                <x-ico name="puzzle-piece" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />

                <div class="relative max-w-3xl">
                    <h1 class="text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl lg:text-[2.75rem]">{{ __('public.quiz') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.a_short_career_quiz_answer_honestly_to') }}
                    </p>
                    @if ($ready)
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-sm font-semibold text-gold-700 ring-1 ring-gold-300/70">
                                <x-ico name="question-mark-circle" class="h-4 w-4" />
                                {{ $count }} {{ $questionWord($count) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-brand-100">
                                <x-ico name="clock" class="h-4 w-4" />
                                {{ __('feature.about_a_minute') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-brand-100">
                                <x-ico name="lock-open" class="h-4 w-4" />
                                {{ __('feature.no_registration') }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-10 lg:py-14">
        @if (! $ready)
            <x-empty-state icon="puzzle-piece" title="{{ __('public.no_quiz') }}" />
        @else
            <div x-data="quiz({{ $count }}, {{ $fallbackId }})" @keydown.window="onKey($event)">

                {{-- ІНТРО --}}
                <div x-show="step === 'intro'" class="grid gap-8 lg:grid-cols-3 lg:items-start">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80 sm:p-8 lg:col-span-2">
                        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-gold-100 text-gold-600">
                            <x-ico name="puzzle-piece" class="h-7 w-7" aria-hidden="true" />
                        </span>
                        <h2 class="mt-5 text-2xl font-extrabold text-brand-950 sm:text-3xl">{{ __('public.quiz_intro') }}</h2>
                        <p class="mt-3 max-w-xl text-slate-500">
                            {{ __('feature.quiz_intro_count', ['count' => $count, 'word' => $questionWord($count)]) }}
                        </p>

                        <ol class="mt-8 grid gap-5 sm:grid-cols-3">
                            @foreach ([
                                ['cursor-arrow-rays', __('feature.choose_your_answers'), __('feature.choose_one_answer_per_question_the_one')],
                                ['chart-bar', __('feature.calculate_matches'), __('feature.each_answer_adds_points_to_a_college')],
                                ['academic-cap', __('feature.get_your_result'), __('feature.a_suggested_specialty_and_links_to_learn')],
                            ] as $i => [$icon, $stepTitle, $stepText])
                                <li class="relative rounded-xl bg-slate-50 p-5 ring-1 ring-slate-200/70">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-bold text-brand-700 shadow-sm ring-1 ring-slate-200">{{ $i + 1 }}</span>
                                    <p class="mt-3 flex items-center gap-2 font-bold text-brand-950">
                                        <x-ico :name="$icon" class="h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
                                        {{ $stepTitle }}
                                    </p>
                                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $stepText }}</p>
                                </li>
                            @endforeach
                        </ol>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button type="button" @click="start()" class="btn-accent w-full justify-center px-8 py-3.5 text-base sm:w-auto">
                                {{ __('public.quiz_start') }} <x-ico name="arrow-right" class="h-4 w-4" />
                            </button>
                            <p class="text-sm text-slate-400">{{ __('feature.no_forms_to_fill_in_just_click') }}</p>
                        </div>
                    </div>

                    {{-- Що можна отримати — реальні спеціальності з бази --}}
                    <aside class="rounded-2xl bg-brand-50/70 p-6 ring-1 ring-brand-100">
                        <h2 class="text-lg font-extrabold text-brand-950">{{ __('feature.specialties_in_the_quiz') }}</h2>
                        <p class="mt-1.5 text-sm text-slate-600">{{ __('feature.one_of_these_will_be_your_result') }}</p>
                        <ul class="mt-5 space-y-3">
                            @foreach ($specialties as $specialty)
                                <li>
                                    <a href="{{ \App\Support\LocalizedUrl::route('specialties.show', $specialty) }}"
                                       class="flex items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200/80 transition hover:ring-gold-300">
                                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700">
                                            <x-ico :name="$specialty->icon_name" class="h-5 w-5" aria-hidden="true" />
                                        </span>
                                        <span class="min-w-0">
                                            @if ($specialty->code)
                                                <span class="block text-xs font-bold text-gold-700">{{ $specialty->code }}</span>
                                            @endif
                                            <span class="block text-sm font-semibold leading-snug text-brand-950">{{ $specialty->localized('title') }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="btn-outline mt-6 w-full justify-center border-gold-300 text-gold-700 ring-gold-300 hover:bg-gold-50">
                            {{ __('feature.all_specialties') }} <x-ico name="arrow-right" class="h-4 w-4" />
                        </a>
                    </aside>
                </div>

                {{-- ПИТАННЯ --}}
                <div x-show="typeof step === 'number'" x-cloak class="mx-auto max-w-3xl">
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80 sm:p-8">
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm font-semibold text-slate-500">
                                {{ __('feature.question') }} <span class="text-brand-800" x-text="stepNumber"></span> {{ __('feature.quiz_of_total', ['count' => $count]) }}
                            </p>
                            <button type="button" x-show="history.length > 0" @click="back()"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-brand-700">
                                <x-ico name="arrow-left" class="h-4 w-4" aria-hidden="true" /> {{ __('public.back') }}
                            </button>
                        </div>

                        {{-- Прогрес: смуга + сегменти по одному на питання --}}
                        <div class="mt-4" role="progressbar" aria-label="{{ __('feature.quiz_progress') }}"
                             aria-valuemin="0" aria-valuemax="{{ $count }}" :aria-valuenow="history.length">
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-gold-400 transition-all duration-300" :style="'width:' + progress + '%'"></div>
                            </div>
                            <div class="mt-2 flex gap-1.5">
                                @for ($i = 0; $i < $count; $i++)
                                    <span class="h-1.5 flex-1 rounded-full transition"
                                          :class="history.length > {{ $i }} ? 'bg-gold-400' : (stepIndex === {{ $i }} ? 'bg-brand-300' : 'bg-slate-100')"></span>
                                @endfor
                            </div>
                        </div>

                        {{-- Питання рендеряться сервером: працюють без JS-даних і видні пошуковим системам --}}
                        @foreach ($questions as $qi => $question)
                            @php $payload = $question->publicPayload(); @endphp
                            <div x-show="stepIndex === {{ $qi }}" x-cloak aria-live="polite" aria-atomic="true">
                                <h2 class="mt-7 text-xl font-extrabold leading-snug text-brand-950 sm:text-2xl">{{ $payload['q'] }}</h2>

                                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                    @foreach ($question->options as $oi => $option)
                                        <button type="button"
                                                data-step="{{ $qi }}" data-opt="{{ $oi }}"
                                                @click="answer({{ (int) $option->specialty_id }}, {{ (int) $option->points }})"
                                                class="flex min-h-[3.75rem] w-full items-center gap-3 rounded-xl bg-slate-50 px-4 py-4 text-left text-base font-medium text-slate-700 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:ring-brand-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 active:scale-[.99]">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white text-sm font-bold text-slate-400 ring-1 ring-slate-200">{{ chr(65 + $oi) }}</span>
                                            <span>{{ $payload['options'][$oi]['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <p class="mt-6 hidden items-center gap-2 text-xs text-slate-400 sm:flex">
                            <x-ico name="command-line" class="h-4 w-4" aria-hidden="true" />
                            {{ __('feature.quiz_keyboard', ['count' => $questions->max(fn ($q) => $q->options->count())]) }}
                        </p>
                    </div>

                    <p class="mt-4 text-center text-sm text-slate-400">
                        {{ __('feature.the_quiz_is_for_guidance_only_your') }}
                    </p>
                </div>

                {{-- РЕЗУЛЬТАТ --}}
                <div x-show="step === 'result'" x-cloak aria-live="polite" aria-atomic="true" class="mx-auto max-w-4xl">
                    @foreach ($specialties as $specialty)
                        <div x-show="winnerId === {{ $specialty->id }}" x-cloak
                             class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80">
                            <div class="relative overflow-hidden bg-gradient-to-br from-brand-800 to-brand-950 px-6 py-9 text-center sm:px-10">
                                <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full text-white/[0.07]">
                                    <defs>
                                        <pattern id="quiz-grid-{{ $specialty->id }}" width="28" height="28" patternUnits="userSpaceOnUse">
                                            <path d="M28 0H0V28" fill="none" stroke="currentColor" stroke-width="1" />
                                        </pattern>
                                    </defs>
                                    <rect width="100%" height="100%" fill="url(#quiz-grid-{{ $specialty->id }})" />
                                </svg>

                                <div class="relative">
                                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white/10 text-gold-300 ring-1 ring-white/15">
                                        <x-ico :name="$specialty->icon_name" class="h-8 w-8" aria-hidden="true" />
                                    </span>
                                    <p class="mt-5 text-sm font-semibold uppercase tracking-wide text-gold-300">{{ __('public.quiz_result') }}</p>
                                    <h2 class="mt-2 text-2xl font-extrabold text-white sm:text-3xl">{{ $specialty->localized('title') }}</h2>
                                    @if ($specialty->code)
                                        <p class="mt-2 text-sm text-brand-200">{{ __('feature.specialty_code_label', ['code' => $specialty->code]) }}</p>
                                    @endif
                                    <p class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-gold-400/15 px-3 py-1 text-sm font-semibold text-gold-200 ring-1 ring-gold-300/40">
                                        <x-ico name="sparkles" class="h-4 w-4" aria-hidden="true" />
                                        {{ __('feature.match') }} <span x-text="percentOf({{ $specialty->id }})"></span>%
                                    </p>
                                </div>
                            </div>

                            <div class="p-6 text-center sm:p-8">
                                @if ($specialty->localized('short_description'))
                                    <p class="mx-auto max-w-2xl leading-relaxed text-slate-600">{{ $specialty->localized('short_description') }}</p>
                                @endif

                                <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                                    <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-accent justify-center px-8 py-3.5 text-base">
                                        {{ __('feature.contact_the_college_2') }} <x-ico name="arrow-right" class="h-4 w-4" />
                                    </a>
                                    <a href="{{ \App\Support\LocalizedUrl::route('specialties.show', $specialty) }}" class="btn-outline justify-center px-8 py-3.5 text-base">
                                        {{ __('public.about_specialty') }} <x-ico name="arrow-right" class="h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- Як розподілилися відповіді --}}
                    <div x-show="answered > 0" x-cloak class="mt-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80 sm:p-8">
                        <h2 class="text-lg font-extrabold text-brand-950">{{ __('feature.how_your_answers_were_distributed') }}</h2>
                        <p class="mt-1.5 text-sm text-slate-500">{{ __('feature.explore_your_second_and_third_results_too') }}</p>

                        <ul class="mt-6 space-y-4">
                            @foreach ($specialties as $specialty)
                                <li x-show="scoreOf({{ $specialty->id }}) > 0" x-cloak>
                                    <div class="flex items-baseline justify-between gap-4 text-sm">
                                        <a href="{{ \App\Support\LocalizedUrl::route('specialties.show', $specialty) }}" class="font-semibold text-brand-900 hover:text-brand-600">
                                            @if ($specialty->code)<span class="text-gold-700">{{ $specialty->code }}</span> @endif{{ $specialty->localized('title') }}
                                        </a>
                                        <span class="shrink-0 font-semibold text-slate-500"><span x-text="percentOf({{ $specialty->id }})"></span>%</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full transition-all duration-500"
                                             :class="winnerId === {{ $specialty->id }} ? 'bg-gold-400' : 'bg-brand-300'"
                                             :style="'width:' + percentOf({{ $specialty->id }}) + '%'"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-7 flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
                            <button type="button" @click="restart()" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
                                <x-ico name="arrow-path" class="h-4 w-4" aria-hidden="true" /> {{ __('public.quiz_restart') }}
                            </button>
                            <a href="{{ \App\Support\LocalizedUrl::route('specialties.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
                                <x-ico name="squares-2x2" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.view_all_specialties') }}
                            </a>
                        </div>
                    </div>

                    {{-- Фінальна смуга — живий контакт після результату --}}
                    <div class="mt-8 overflow-hidden rounded-2xl bg-gradient-to-br from-brand-50 to-white px-6 py-8 ring-1 ring-brand-100 sm:px-10">
                        <div class="flex flex-wrap items-center justify-between gap-6">
                            <span class="hidden h-16 w-16 shrink-0 place-items-center rounded-full bg-gold-100 text-gold-700 sm:grid">
                                <x-ico name="chat-bubble-left-right" class="h-8 w-8" aria-hidden="true" />
                            </span>
                            <div class="max-w-2xl flex-1">
                                <h2 class="text-xl font-extrabold text-brand-950 sm:text-2xl">{{ __('feature.unsure_about_your_result') }}</h2>
                                <p class="mt-2 text-slate-600">
                                    Приймальна комісія допоможе зважити всі варіанти та розповість про вступ на кожну спеціальність.
                                    @if ($hasContacts)
                                        @if (! empty($s['contact_phone']))
                                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $s['contact_phone']) }}" class="font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_phone'] }}</a>
                                        @endif
                                        @if (! empty($s['contact_email']))
                                            <a href="mailto:{{ $s['contact_email'] }}" class="break-all font-semibold text-brand-800 hover:text-brand-600">{{ $s['contact_email'] }}</a>
                                        @endif
                                    @endif
                                </p>
                            </div>
                            <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline px-6 py-3">
                                {{ __('public.contacts') }} <x-ico name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function quiz(total, fallbackId) {
                    return {
                        total,
                        fallbackId,
                        step: 'intro',      // 'intro' | номер питання | 'result'
                        scores: {},         // specialty_id => бали
                        history: [],        // [specialty_id, бали] — щоб працювала кнопка «Назад»

                        start() { this.step = 0 },

                        // Індекс поточного питання (-1 на інтро та результаті) і людський номер
                        get stepIndex() { return typeof this.step === 'number' ? this.step : -1 },
                        get stepNumber() { return this.stepIndex + 1 },
                        get answered() { return this.history.length },
                        get progress() { return this.total ? Math.round(this.answered / this.total * 100) : 0 },

                        answer(sid, pts) {
                            if (sid) this.scores[sid] = (this.scores[sid] || 0) + pts
                            this.history.push([sid, pts])
                            this.step = (this.step + 1 < this.total) ? this.step + 1 : 'result'
                        },

                        back() {
                            const prev = this.history.pop()
                            if (prev && prev[0]) this.scores[prev[0]] -= prev[1]
                            this.step = this.history.length
                        },

                        scoreOf(id) { return this.scores[id] || 0 },
                        percentOf(id) { return this.answered ? Math.round(this.scoreOf(id) / this.answered * 100) : 0 },

                        get winnerId() {
                            const top = Object.entries(this.scores).sort((a, b) => b[1] - a[1])[0]
                            return top && top[1] > 0 ? Number(top[0]) : this.fallbackId
                        },

                        // Клавіші 1–9 обирають варіант поточного питання
                        onKey(e) {
                            if (typeof this.step !== 'number' || e.metaKey || e.ctrlKey || e.altKey) return
                            const n = parseInt(e.key, 10)
                            if (!n) return
                            const btn = document.querySelector('[data-step="' + this.step + '"][data-opt="' + (n - 1) + '"]')
                            if (btn) { e.preventDefault(); btn.click() }
                        },

                        restart() { this.scores = {}; this.history = []; this.step = 'intro' },
                    }
                }
            </script>
        @endif
    </section>

</x-layouts.app>
