<x-layouts.app :title="__('public.apply')" :description="__('public.apply_description')">

    <x-page-hero :title="__('public.apply')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.applicants'), 'url' => \App\Support\LocalizedUrl::to('/abituriyentu')],
        ['label' => __('public.apply')],
    ]">
        <p class="mt-4 max-w-2xl text-brand-100">{{ __('public.apply_intro') }}</p>
    </x-page-hero>

    <section class="container-site py-12">
        <div class="mx-auto max-w-2xl">
            <div class="card p-6 sm:p-8">
                @if (session('status'))
                    <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <x-ico name="check-circle" variant="solid" class="h-5 w-5 shrink-0 text-emerald-500" />
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ \App\Support\LocalizedUrl::route('applicants.store') }}" class="space-y-5"
                      x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    {{-- Honeypot (антиспам) --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">{{ __('public.honeypot') }}</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <label for="name" class="label">{{ __('public.full_name') }} <span class="text-rose-500">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required class="input @error('name') ring-rose-400 @enderror" placeholder="{{ __('public.name_example') }}">
                        @error('name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="label">{{ __('public.phone') }} <span class="text-rose-500">*</span></label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="input @error('phone') ring-rose-400 @enderror" placeholder="+380 __ ___ __ __">
                            @error('phone') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="label">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input @error('email') ring-rose-400 @enderror">
                            @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="specialty_id" class="label">{{ __('public.specialty_interest') }}</label>
                        <select id="specialty_id" name="specialty_id" class="input">
                            <option value="">{{ __('public.undecided') }}</option>
                            @foreach ($specialties as $sp)
                                <option value="{{ $sp->id }}" @selected(old('specialty_id', request('specialty_id')) == $sp->id)>{{ $sp->localized('title') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="message" class="label">{{ __('public.question_comment') }}</label>
                        <textarea id="message" name="message" rows="4" class="input" placeholder="{{ __('public.question_example') }}">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="sending">
                        <span x-show="!sending" class="inline-flex items-center gap-2">
                            <x-ico name="paper-airplane" class="h-4 w-4" /> {{ __('public.send_application') }}
                        </span>
                        <span x-show="sending" x-cloak class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" /></svg> {{ __('public.sending') }}
                        </span>
                    </button>
                    <p class="text-xs text-slate-400">{{ __('public.consent') }}</p>
                </form>
            </div>
        </div>
    </section>

</x-layouts.app>
