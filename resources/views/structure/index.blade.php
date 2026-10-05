<x-layouts.app :title="__('public.structure')">

    <x-page-hero :title="__('public.college_structure')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.structure')],
    ]" />

    <section class="container-site space-y-14 py-12">
        @forelse ($groups as $type => $group)
            <div id="{{ $type }}" class="scroll-mt-28">
                <h2 class="text-2xl font-extrabold text-slate-900">{{ $group['label'] }}</h2>
                <div class="accent-rule"></div>
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($group['items'] as $dep)
                        <a href="{{ \App\Support\LocalizedUrl::route('structure.show', $dep) }}" class="card card-interactive group flex items-start gap-4 p-5">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-700 group-hover:text-white">
                                <x-ico name="building-office-2" class="h-6 w-6" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold leading-snug text-slate-900 group-hover:text-brand-700">{{ $dep->localized('title') }}</span>
                                @if ($dep->staff_count)
                                    <span class="mt-1 block text-sm text-slate-500">{{ __('public.staff_count', ['count' => $dep->staff_count]) }}</span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <x-empty-state icon="building-office-2" :title="__('public.no_structure')" />
        @endforelse
    </section>

</x-layouts.app>
