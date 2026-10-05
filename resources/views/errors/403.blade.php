<x-layouts.app :title="__('public.forbidden')">
    <section class="container-site flex min-h-[55vh] flex-col items-center justify-center py-20 text-center">
        <p class="font-display text-7xl font-extrabold leading-none text-brand-700 sm:text-8xl">403</p>
        <h1 class="mt-5 text-2xl font-bold text-slate-900 sm:text-3xl">{{ __('public.forbidden') }}</h1>
        <p class="mt-3 max-w-md text-slate-500">{{ __('public.forbidden_text') }}</p>
        <div class="mt-8">
            <a href="{{ \App\Support\LocalizedUrl::route('home') }}" class="btn-accent">{{ __('public.back_home') }}</a>
        </div>
    </section>
</x-layouts.app>
