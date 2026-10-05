@php $s = \App\Models\Setting::publicMap(); @endphp

<x-layouts.app :title="__('public.contacts')">

    <x-page-hero :title="__('public.contacts')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.contacts')],
    ]" />

    <section class="container-site grid gap-10 py-12 lg:grid-cols-2">
        {{-- Інформація: текст CMS-сторінки «kontakty» або, без неї, контакти з налаштувань --}}
        @if ($page && filled($page->localized('body')))
            <div>
                <x-prose.article>
                    {!! \App\Support\LocalizedHtml::links($page->localized('body')) !!}
                </x-prose.article>
            </div>
        @else
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ __('public.contact_us') }}</h2>
            <p class="mt-2 text-slate-500">{{ __('public.contact_intro') }}</p>

            <ul class="mt-8 space-y-5">
                @foreach (array_filter([
                    ['map-pin', __('public.address'), $s['contact_address'] ?? null],
                    ['phone', __('public.phone'), $s['contact_phone'] ?? null],
                    ['envelope', __('public.email'), $s['contact_email'] ?? null],
                    ['clock', __('public.hours'), $s['work_hours'] ?? null],
                ], fn ($r) => ! empty($r[2])) as [$icon, $label, $value])
                    <li class="flex gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-ico :name="$icon" class="h-5 w-5" /></span>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                            <p class="font-medium text-slate-800">{{ $value }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if (! empty($s['map_embed']))
                <div class="mt-8 overflow-hidden rounded-2xl ring-1 ring-slate-200">
                    <iframe src="{{ $s['map_embed'] }}" class="h-64 w-full" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            @endif
        </div>

        @endif

        {{-- Форма --}}
        <div class="card p-6 sm:p-8">
            <h2 class="text-xl font-bold text-slate-900">{{ __('public.feedback_form') }}</h2>

            @if (session('status'))
                <div class="mt-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <x-ico name="check-circle" variant="solid" class="h-5 w-5 shrink-0 text-emerald-500" />
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ \App\Support\LocalizedUrl::route('contacts.store') }}" class="mt-6 space-y-5"
                  x-data="{ sending: false }" @submit="sending = true">
                @csrf
                {{-- Honeypot (антиспам): приховане поле, яке заповнюють лише боти --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('public.honeypot') }}</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="label">{{ __('public.name') }} <span class="text-rose-500">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="input @error('name') ring-rose-400 @enderror">
                        @error('name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="label">{{ __('public.phone') }}</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="input">
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="email" class="label">{{ __('public.email') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="input @error('email') ring-rose-400 @enderror">
                        @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="subject" class="label">{{ __('public.subject') }}</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" class="input">
                    </div>
                </div>
                <div>
                    <label for="message" class="label">{{ __('public.message') }} <span class="text-rose-500">*</span></label>
                    <textarea id="message" name="message" rows="5" required class="input @error('message') ring-rose-400 @enderror">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="sending">
                    <span x-show="!sending" class="inline-flex items-center gap-2">
                        <x-ico name="paper-airplane" class="h-4 w-4" /> {{ __('public.send_feedback') }}
                    </span>
                    <span x-show="sending" x-cloak class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" /></svg> {{ __('public.sending') }}
                    </span>
                </button>
            </form>
        </div>
    </section>

</x-layouts.app>
