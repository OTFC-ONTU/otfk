<x-layouts.app :title="__('public.videos')">

    <x-page-hero :title="__('public.video_materials')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.videos')],
    ]" />

    <section class="container-site py-12">
        @if ($videos->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    <a href="{{ $video->watch_url }}" target="_blank" rel="noopener" class="card group overflow-hidden">
                        <div class="relative aspect-video overflow-hidden bg-slate-900">
                            <img src="{{ $video->thumbnail }}" alt="{{ $video->localized('title') }}" loading="lazy" decoding="async" class="h-full w-full object-cover opacity-90 transition group-hover:scale-105 group-hover:opacity-100">
                            <span class="absolute inset-0 grid place-items-center">
                                <span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-brand-700 shadow-lg transition group-hover:scale-110">
                                    <x-ico name="play" variant="solid" class="h-6 w-6" />
                                </span>
                            </span>
                        </div>
                        <div class="p-4">
                            <h3 class="line-clamp-2 font-semibold text-slate-800 group-hover:text-brand-700">{{ $video->localized('title') }}</h3>
                            @if ($video->published_at)
                                <p class="mt-1 text-xs text-slate-400">{{ $video->published_at->translatedFormat('j F Y') }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $videos->links() }}</div>
        @else
            <x-empty-state icon="play" :title="__('public.no_videos')" />
        @endif
    </section>

</x-layouts.app>
