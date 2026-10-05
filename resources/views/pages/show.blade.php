@php $children = $page->children()->published()->ordered()->get(); @endphp

<x-layouts.app :title="$page->localized('meta_title') ?: $page->localized('title')" :description="$page->localized('meta_description') ?: $page->localized('excerpt')">

    @php
        $pageBreadcrumbs = [['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')]];
        if ($page->parent) {
            $pageBreadcrumbs[] = ['label' => $page->parent->localized('title'), 'url' => \App\Support\LocalizedUrl::to('/' . $page->parent->slug)];
        }
        $pageBreadcrumbs[] = ['label' => $page->localized('title')];
    @endphp
    <x-page-hero :title="$page->localized('title')" :breadcrumbs="$pageBreadcrumbs" :heritage="$page->is_heritage" class="max-w-4xl leading-tight" />

    <section class="container-site py-12">
        @if ($page->cover_image)
            <x-picture :path="$page->cover_image" :alt="$page->localized('title')" loading="lazy" decoding="async" class="mb-8 w-full rounded-2xl object-cover" />
        @endif

        <x-lead-excerpt :excerpt="$page->localized('excerpt')" :body="$page->localized('body')" :heritage="$page->is_heritage" />

        @if (filled($page->localized('body')))
            <x-prose.article :heritage="$page->is_heritage" :drop-cap="$page->slug === 'istoriya'">
                {!! \App\Support\LocalizedHtml::links($page->localized('body')) !!}
            </x-prose.article>
        @endif

        {{-- Підрозділи (якщо це сторінка-розділ); відступ зверху — лише коли вище є контент --}}
        @if ($children->isNotEmpty())
            <div @class(['grid gap-4 sm:grid-cols-2 lg:grid-cols-3', 'mt-10' => $page->cover_image || filled($page->localized('excerpt')) || filled($page->localized('body'))])>
                @foreach ($children as $child)
                    <a href="{{ \App\Support\LocalizedUrl::to('/' . $child->slug) }}" class="card card-interactive group flex items-center justify-between gap-3 p-5">
                        <span class="font-semibold text-slate-800 group-hover:text-brand-700">{{ $child->localized('title') }}</span>
                        <x-ico name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600" />
                    </a>
                @endforeach
            </div>
        @endif

        @if (blank($page->localized('body')) && $children->isEmpty() && blank($page->localized('excerpt')))
            <x-empty-state icon="document-text" :title="__('public.no_page_content')" />
        @endif
    </section>

</x-layouts.app>
