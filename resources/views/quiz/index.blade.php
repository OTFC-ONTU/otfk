<x-layouts.app :title="__('public.quiz')" :description="__('public.quiz_description')">

    <x-page-hero :title="__('public.quiz')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.applicants'), 'url' => \App\Support\LocalizedUrl::to('/abituriyentu')],
        ['label' => __('public.quiz_breadcrumb')],
    ]" />

    <section class="container-site py-12">
        <div class="mx-auto max-w-2xl">
            @if ($questions->isEmpty() || $specialties->isEmpty())
                <x-empty-state icon="puzzle-piece" :title="__('public.no_quiz')" />
            @else
                <div x-data="quiz(
                        @js($questions->map(fn ($q) => $q->publicPayload())->values()),
                        @js($specialties->keyBy('id')->map(fn ($s) => [
                            'title' => $s->localized('title'), 'code' => $s->code,
                            'short' => $s->localized('short_description'),
                            'url' => \App\Support\LocalizedUrl::route('specialties.show', $s),
                            'apply' => \App\Support\LocalizedUrl::route('applicants.create') . '?specialty_id=' . $s->id,
                        ]))
                     )">

                    {{-- Інтро --}}
                    <div x-show="step === 'intro'" class="card p-8 text-center sm:p-10">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-gold-100 text-gold-600">
                            <x-ico name="puzzle-piece" class="h-8 w-8" />
                        </span>
                        <h2 class="mt-5 text-2xl font-extrabold text-slate-900">{{ __('public.quiz_intro') }}</h2>
                        <p class="mx-auto mt-3 max-w-md text-slate-500">
                            <span x-text="@js(__('public.quiz_intro_text')).replace(':count', questions.length)"></span>
                        </p>
                        <button type="button" @click="step = 0" class="btn-accent mt-7 px-8">{{ __('public.quiz_start') }}</button>
                    </div>

                    {{-- Питання --}}
                    <div x-show="typeof step === 'number'" x-cloak aria-live="polite" aria-atomic="true" class="card p-6 sm:p-8">
                        <div class="flex items-center justify-between gap-4 text-sm text-slate-400">
                            <span x-text="@js(__('public.quiz_progress')).replace(':number', step + 1).replace(':total', questions.length)"></span>
                            <button type="button" x-show="step > 0" @click="back()" class="inline-flex items-center gap-1 font-medium text-slate-500 hover:text-brand-700">
                                <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back') }}
                            </button>
                        </div>
                        {{-- Прогрес --}}
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gold-400 transition-all duration-300"
                                 :style="'width:' + (typeof step === 'number' ? (step / questions.length * 100) : 0) + '%'"></div>
                        </div>

                        <h2 class="mt-6 text-xl font-extrabold text-slate-900" x-text="questions[step]?.q"></h2>

                        <div class="mt-5 space-y-2.5">
                            <template x-for="(opt, oi) in (questions[step]?.options ?? [])" :key="oi">
                                <button type="button" @click="answer(opt)"
                                        class="flex w-full items-center gap-3 rounded-xl bg-slate-50 px-4 py-3.5 text-left text-sm font-medium text-slate-700 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:ring-brand-300 active:scale-[.99]">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-slate-400 ring-1 ring-slate-200"
                                          x-text="String.fromCharCode(65 + oi)"></span>
                                    <span x-text="opt.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Результат --}}
                    <div x-show="step === 'result'" x-cloak aria-live="polite" aria-atomic="true" class="card overflow-hidden">
                        <div class="bg-brand-950 px-8 pb-8 pt-9 text-center">
                            <p class="text-sm font-medium uppercase tracking-wide text-gold-300">{{ __('public.quiz_result') }}</p>
                            <h2 class="mt-2 text-2xl font-extrabold text-white sm:text-3xl" x-text="winner()?.title"></h2>
                            <p class="mt-1 text-sm text-brand-200" x-text="winner() ? @js(__('public.specialty_code')).replace(':code', winner().code) : ''"></p>
                        </div>
                        <div class="p-8 text-center">
                            <p class="mx-auto max-w-md text-sm leading-relaxed text-slate-600" x-text="winner()?.short"></p>
                            <p x-show="runnerUp()" class="mt-3 text-xs text-slate-400">
                                {{ __('public.quiz_alternative') }} <span class="font-semibold text-slate-500" x-text="runnerUp()?.title"></span>
                            </p>
                            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                                <a :href="winner()?.apply" class="btn-accent">{{ __('public.apply') }}</a>
                                <a :href="winner()?.url" class="btn-outline">{{ __('public.about_specialty') }}</a>
                            </div>
                            <button type="button" @click="restart()" class="mt-5 inline-flex items-center gap-1.5 text-sm text-slate-400 transition hover:text-brand-700">
                                <x-ico name="arrow-path" class="h-4 w-4" /> {{ __('public.quiz_restart') }}
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                    function quiz(questions, specialties) {
                        return {
                            questions, specialties,
                            step: 'intro',          // 'intro' | номер питання | 'result'
                            scores: {},             // specialty_id => бали
                            history: [],            // вибрані варіанти (для «Назад»)
                            answer(opt) {
                                if (opt.sid) this.scores[opt.sid] = (this.scores[opt.sid] || 0) + opt.pts;
                                this.history.push(opt);
                                this.step = (this.step + 1 < this.questions.length) ? this.step + 1 : 'result';
                            },
                            back() {
                                const prev = this.history.pop();
                                if (prev && prev.sid) this.scores[prev.sid] -= prev.pts;
                                this.step = Math.max(0, (typeof this.step === 'number' ? this.step : this.questions.length) - 1);
                            },
                            ranking() {
                                return Object.entries(this.scores).sort((a, b) => b[1] - a[1]);
                            },
                            winner() {
                                const top = this.ranking()[0];
                                return top ? this.specialties[top[0]] : Object.values(this.specialties)[0];
                            },
                            runnerUp() {
                                const r = this.ranking();
                                return (r.length > 1 && r[1][1] > 0 && r[0][1] - r[1][1] <= 1) ? this.specialties[r[1][0]] : null;
                            },
                            restart() { this.scores = {}; this.history = []; this.step = 'intro'; },
                        };
                    }
                </script>
            @endif
        </div>
    </section>

</x-layouts.app>
