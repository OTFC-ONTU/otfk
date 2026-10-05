<x-layouts.app :title="$gallery->albumLocalized('title')">

    <x-page-hero :title="$gallery->albumLocalized('title')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.gallery'), 'url' => \App\Support\LocalizedUrl::route('galleries.index')],
        ['label' => $gallery->albumLocalized('title')],
    ]">
        @if ($gallery->albumLocalized('description'))
            <p class="mt-3 max-w-2xl text-brand-100">{{ $gallery->albumLocalized('description') }}</p>
        @endif
    </x-page-hero>

    <section @class(['container-site py-12', 'photo-archive' => $gallery->is_archive]) x-data="{ open: false, src: '', cap: '' }">
        @if ($gallery->is_archive)
            <p class="mb-6 inline-flex items-center gap-2 rounded-full bg-gold-50 px-3 py-1.5 text-sm font-medium text-gold-800 ring-1 ring-gold-200">
                <x-ico name="archive-box" class="h-4 w-4" /> {{ __('public.archive_album') }}
            </p>
        @endif
        @if ($gallery->photos->isNotEmpty())
            <div class="photo-archive-grid grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($gallery->photos as $photo)
                    <button type="button" @click="open = true; src = @js($photo->url); cap = @js($gallery->publicCaption($photo))"
                            @class([
                                'photo-archive-item group relative aspect-square overflow-hidden bg-slate-100',
                                'rounded-sm ring-2 ring-gold-300/60 shadow-[inset_0_0_24px_rgb(30_35_63/0.12)]' => $gallery->is_archive,
                                'rounded-xl' => ! $gallery->is_archive,
                            ])>
                        <x-picture :path="$photo->image" :alt="$gallery->publicCaption($photo) ?: $gallery->albumLocalized('title')" loading="lazy"
                                   @class([
                                       'h-full w-full object-cover transition duration-300 group-hover:scale-105',
                                       'sepia-[0.18] contrast-[1.03]' => $gallery->is_archive,
                                   ]) />
                        <span @class([
                            'absolute inset-0 transition',
                            'bg-amber-950/0 group-hover:bg-amber-950/15' => $gallery->is_archive,
                            'bg-brand-950/0 group-hover:bg-brand-950/20' => ! $gallery->is_archive,
                        ])></span>
                    </button>
                @endforeach
            </div>

            {{-- Лайтбокс --}}
            <div x-show="open" x-cloak @keydown.escape.window="open = false" @click="open = false"
                 class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/90 p-4" x-transition.opacity>
                <button @click="open = false" class="absolute right-5 top-5 text-white/80 hover:text-white"><x-ico name="x-mark" class="h-8 w-8" /></button>
                <figure @click.stop class="max-h-full max-w-4xl">
                    <img :src="src" alt="" class="max-h-[80vh] w-auto rounded-lg">
                    <figcaption x-show="cap" x-text="cap" class="mt-3 text-center text-sm text-white/80"></figcaption>
                </figure>
            </div>
        @else
            <x-empty-state icon="photo" :title="__('public.no_photos')" />
        @endif

        <a href="{{ \App\Support\LocalizedUrl::route('galleries.index') }}" class="btn-outline mt-10"><x-ico name="arrow-left" class="h-4 w-4" /> {{ __('public.back_gallery') }}</a>
    </section>

</x-layouts.app>
