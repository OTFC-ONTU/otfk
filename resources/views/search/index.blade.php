<x-layouts.app :title="__('public.search')">

    <x-page-hero :title="__('public.site_search')" :show-rule="false" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.search')],
    ]">
        <form action="{{ \App\Support\LocalizedUrl::route('search') }}" method="GET" class="mt-6 flex max-w-xl gap-2">
            <input type="search" name="q" value="{{ $q }}" autofocus placeholder="{{ __('public.search_placeholder') }}"
                   class="input flex-1" />
            <button type="submit" class="btn-accent"><x-ico name="magnifying-glass" class="h-4 w-4" /> {{ __('public.find') }}</button>
        </form>
    </x-page-hero>

    <section class="container-site py-12">
        @if ($q === '')
            <x-empty-state icon="magnifying-glass" :title="__('public.search_intro')" />
        @elseif ($results->isEmpty())
            <x-empty-state icon="magnifying-glass" :title="__('public.search_empty', ['query' => $q])" />
        @else
            <p class="mb-6 text-sm text-slate-500">{{ __('public.results_found') }} <span class="font-semibold text-slate-800">{{ $results->count() }}</span></p>
            <ul class="space-y-3">
                @foreach ($results as $r)
                    <li class="card card-interactive p-5">
                        <a href="{{ $r['url'] }}" class="group block">
                            <span class="badge bg-brand-50 text-brand-700">{{ $r['type'] }}</span>
                            <p class="mt-2 font-bold text-slate-900 group-hover:text-brand-700">{{ $r['title'] }}</p>
                            @if (! empty($r['excerpt']))
                                <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $r['excerpt'] }}</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

</x-layouts.app>
