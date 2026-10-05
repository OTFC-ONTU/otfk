<x-layouts.app :title="$department->localized('title')">

    <section class="bg-brand-950">
        <div class="container-site py-12 lg:py-14">
            <x-breadcrumbs :items="[
                ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
                ['label' => __('public.structure'), 'url' => \App\Support\LocalizedUrl::route('structure.index')],
                ['label' => $department->localized('title')],
            ]" />
            <span class="mt-4 inline-block badge bg-white/10 text-brand-100 ring-1 ring-white/15">{{ __('public.department_'.str_replace('-', '_', $department->type)) }}</span>
            <h1 class="mt-3 max-w-4xl text-3xl font-extrabold leading-tight text-white sm:text-4xl">{{ $department->localized('title') }}</h1>
            <div class="accent-rule"></div>
        </div>
    </section>

    <section class="container-site py-12">
        @if (filled($department->localized('description')))
            <div class="prose prose-slate max-w-none prose-headings:font-display prose-a:text-brand-700">
                {!! \App\Support\LocalizedHtml::links($department->localized('description')) !!}
            </div>
        @endif

        @if ($department->staff->isNotEmpty())
            <div class="mt-10">
                <h2 class="text-2xl font-extrabold text-slate-900">{{ __('public.department_staff') }}</h2>
                <div class="accent-rule"></div>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($department->staff as $person)
                        <x-staff-card :person="$person" />
                    @endforeach
                </div>
            </div>
        @endif

        @if (blank($department->localized('description')) && $department->staff->isEmpty())
            <x-empty-state icon="building-office-2" :title="__('public.no_department')" />
        @endif

        <a href="{{ \App\Support\LocalizedUrl::route('structure.index') }}" class="btn-outline mt-10"><x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_structure') }}</a>
    </section>

</x-layouts.app>
