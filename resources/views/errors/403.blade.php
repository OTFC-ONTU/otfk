<x-layouts.app title="{{ __('public.forbidden') }}">

    <section class="border-b border-slate-200/70 bg-slate-50/80">
        <div class="container-site py-12 lg:py-16">
            <div class="relative overflow-hidden rounded-2xl bg-white px-6 py-10 shadow-sm ring-1 ring-slate-200/80 sm:px-10 sm:py-12">
                <p aria-hidden="true"
                   class="pointer-events-none absolute -right-4 top-1/2 hidden -translate-y-1/2 font-display text-[12rem] font-extrabold leading-none text-brand-50 lg:block">403</p>

                <div class="relative max-w-2xl">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gold-700 ring-1 ring-gold-300/70">
                        <x-ico name="lock-closed" class="h-4 w-4" aria-hidden="true" /> {{ __('feature.error_403') }}
                    </span>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight text-brand-950 sm:text-4xl">{{ __('public.forbidden') }}</h1>
                    <div class="accent-rule"></div>
                    <p class="mt-5 text-lg leading-relaxed text-slate-500">
                        {{ __('feature.you_do_not_have_permission_to_view') }}
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="btn-primary">
                            <x-ico name="home" class="h-4 w-4" aria-hidden="true" /> {{ __('public.back_home') }}
                        </a>
                        <a href="{{ \App\Support\LocalizedUrl::route('contacts') }}" class="btn-outline">{{ __('feature.email_us') }}</a>
                        <a href="{{ \App\Support\LocalizedUrl::route('search') }}" class="btn-outline">{{ __('public.site_search') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
