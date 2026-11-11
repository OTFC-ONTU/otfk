{{--
    Скринька довіри (App\Http\Controllers\TrustBoxController). Отримувача завжди
    називаємо «директор», скільки б адрес не було в налаштуваннях. Помилки й
    введений текст приходять у даних відповіді на POST (не з сесії).
    Без аналітики (Analytics::tracking() вимкнено для цього маршруту) і noindex.
--}}
<x-layouts.app :title="__('trust_box.title')" :description="__('trust_box.description')" robots="noindex, follow">
    @php
        $home = \App\Support\LocalizedUrl::route('home');
        $action = \App\Support\LocalizedUrl::route('trust-box.send');
        $max = \App\Services\TrustBox\TrustBox::MAX_LENGTH;
    @endphp

    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-8 lg:py-10">
            <x-breadcrumbs tone="light" :items="[
                ['label' => __('public.home'), 'url' => $home],
                ['label' => __('trust_box.title')],
            ]" />

            <div class="relative mt-4 overflow-hidden rounded-2xl bg-white px-6 py-8 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-10">
                <x-ico name="inbox-arrow-down" aria-hidden="true"
                       class="pointer-events-none absolute -right-8 top-1/2 hidden h-64 w-64 -translate-y-1/2 text-brand-50 lg:block" />
                <div class="relative max-w-3xl">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gold-800 ring-1 ring-gold-200">
                        <x-ico name="lock-closed" class="h-4 w-4" aria-hidden="true" /> {{ __('trust_box.badge') }}
                    </span>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ __('trust_box.title') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-600">{{ __('trust_box.lead') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container-site py-10 lg:py-12">
        @if ($sent)
            <div class="mx-auto max-w-2xl rounded-2xl bg-white px-6 py-10 text-center shadow-sm ring-1 ring-slate-200/80 sm:px-10" role="status">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                    <x-ico name="check" class="h-7 w-7" aria-hidden="true" />
                </span>
                <h2 class="mt-4 font-display text-2xl font-extrabold text-brand-950">{{ __('trust_box.sent_title') }}</h2>
                <p class="mx-auto mt-3 max-w-lg leading-relaxed text-slate-600">{{ __('trust_box.sent_text') }}</p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ \App\Support\LocalizedUrl::route('trust-box') }}" class="btn-outline">{{ __('trust_box.send_another') }}</a>
                    <a href="{{ $home }}" class="btn-primary">{{ __('trust_box.to_home') }}</a>
                </div>
            </div>
        @elseif (! $available)
            <div class="mx-auto max-w-2xl rounded-2xl bg-white px-6 py-10 text-center shadow-sm ring-1 ring-slate-200/80 sm:px-10">
                <h2 class="font-display text-2xl font-extrabold text-brand-950">{{ __('trust_box.unavailable_title') }}</h2>
                <p class="mx-auto mt-3 max-w-lg leading-relaxed text-slate-600">{{ __('trust_box.unavailable_text') }}</p>
                <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-primary mt-6">{{ __('trust_box.unavailable_contacts') }}</a>
            </div>
        @else
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form method="POST" action="{{ $action }}" novalidate
                      x-data="{ length: @js(mb_strlen($input['message'])), sending: false }" @submit="sending = true" @pageshow.window="sending = false"
                      class="relative min-w-0 space-y-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80 sm:p-8">
                    @csrf
                    <input type="hidden" name="issued" value="{{ $token }}">

                    <ul class="grid gap-3 sm:grid-cols-3">
                        @foreach (__('trust_box.guarantees') as $item)
                            <li class="rounded-xl bg-brand-50 px-4 py-3 text-sm leading-snug text-brand-900">
                                <span class="block font-semibold">{{ $item['title'] }}</span>{{ $item['text'] }}
                            </li>
                        @endforeach
                    </ul>

                    @if ($errors->hasAny(['form', 'captcha']))
                        <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-900 ring-1 ring-red-200" role="alert" id="trust-box-alert" tabindex="-1">
                            <p class="font-semibold">{{ __('trust_box.errors.title') }}</p>
                            <p class="mt-0.5">{{ $errors->first('form') ?: $errors->first('captcha') }}</p>
                        </div>
                    @endif

                    {{-- Пастка для ботів: прихована від людей і допоміжних технологій --}}
                    <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                        <label for="trust-box-website">{{ __('trust_box.honeypot') }}</label>
                        <input type="text" id="trust-box-website" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="space-y-1.5">
                        <label for="trust-box-topic" class="block text-sm font-semibold text-slate-800">
                            {{ __('trust_box.topic') }} <span class="font-normal text-slate-500">({{ __('trust_box.optional') }})</span>
                        </label>
                        <select id="trust-box-topic" name="topic"
                                @if ($errors->has('topic')) aria-invalid="true" aria-describedby="trust-box-topic-error" @endif
                                class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-800 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ __('trust_box.topic_none') }}</option>
                            @foreach (\App\Services\TrustBox\TrustBox::TOPICS as $topic)
                                <option value="{{ $topic }}" @selected($input['topic'] === $topic)>{{ __('trust_box.topics.'.$topic) }}</option>
                            @endforeach
                        </select>
                        @error('topic')<p id="trust-box-topic-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="trust-box-message" class="block text-sm font-semibold text-slate-800">{{ __('trust_box.message') }}</label>
                        <textarea id="trust-box-message" name="message" rows="8" required
                                  minlength="{{ \App\Services\TrustBox\TrustBox::MIN_LENGTH }}" maxlength="{{ $max }}"
                                  @input="length = $event.target.value.length"
                                  aria-describedby="trust-box-message-hint{{ $errors->has('message') ? ' trust-box-message-error' : '' }}"
                                  @if ($errors->has('message')) aria-invalid="true" @endif
                                  class="block w-full rounded-xl border px-3 py-2.5 leading-relaxed text-slate-800 focus:border-brand-500 focus:ring-brand-500 {{ $errors->has('message') ? 'border-red-500 bg-red-50/40' : 'border-slate-300' }}">{{ $input['message'] }}</textarea>
                        @error('message')<p id="trust-box-message-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 text-xs text-slate-500">
                            <span id="trust-box-message-hint">{{ __('trust_box.message_hint') }}</span>
                            <span class="tabular-nums" aria-hidden="true" x-text="@js(__('trust_box.counter', ['count' => '__C__', 'max' => $max])).replace('__C__', length)">{{ __('trust_box.counter', ['count' => mb_strlen($input['message']), 'max' => $max]) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="flex items-start gap-3 text-sm text-slate-700">
                            <input type="checkbox" id="trust-box-consent" name="consent" value="1" @checked($input['consent'])
                                   @if ($errors->has('consent')) aria-invalid="true" aria-describedby="trust-box-consent-error" @endif
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-400 text-brand-700 focus:ring-brand-500">
                            <span>{{ __('trust_box.consent') }}</span>
                        </label>
                        @error('consent')<p id="trust-box-consent-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>

                    @if ($turnstileKey)
                        <div class="space-y-1.5">
                            <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-action="trust-box"
                                 data-language="{{ app()->getLocale() === 'en' ? 'en' : 'uk' }}" data-theme="light"></div>
                            <p class="text-xs text-slate-500">{{ __('trust_box.captcha_hint') }}</p>
                        </div>
                        <script src="{{ \App\Services\TrustBox\Turnstile::SCRIPT_URL }}" async defer></script>
                    @endif

                    <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="sending">
                        <x-ico name="paper-airplane" class="h-4 w-4" aria-hidden="true" />
                        <span x-text="sending ? @js(__('trust_box.sending')) : @js(__('trust_box.submit'))">{{ __('trust_box.submit') }}</span>
                    </button>
                </form>

                <aside class="space-y-4">
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/80">
                        <h2 class="font-display text-base font-bold text-brand-950">{{ __('trust_box.next_title') }}</h2>
                        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-slate-600">
                            @foreach (__('trust_box.next') as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 border-t border-slate-100 pt-3 text-xs leading-relaxed text-slate-500">{{ __('trust_box.privacy_note') }}</p>
                    </div>
                    <div class="rounded-2xl bg-red-50 p-5 ring-1 ring-red-200">
                        <h2 class="font-display text-base font-bold text-red-800">{{ __('trust_box.help_title') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm text-slate-700">
                            @foreach (__('trust_box.help') as $line)
                                <li class="flex items-baseline justify-between gap-3">
                                    <span>{{ $line['label'] }}</span>
                                    <a href="tel:{{ preg_replace('/\D/', '', $line['phone']) }}" class="shrink-0 font-semibold tabular-nums text-red-800 hover:underline">{{ $line['phone'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
            </div>

            @if ($errors->any())
                {{-- Фокус на першій помилці (TB-32) --}}
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const target = document.querySelector('#trust-box-alert, [aria-invalid=true]');
                        if (target) target.focus();
                    });
                </script>
            @endif
        @endif
    </section>
</x-layouts.app>
