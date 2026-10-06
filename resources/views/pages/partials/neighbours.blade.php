{{-- Сусідні сторінки розділу — під урочистою heritage-сторінкою, де сайдбар зруйнував би стиль «листа» --}}

@if ($neighbours->isNotEmpty())
    <section class="border-t border-slate-200/70 bg-slate-50/60">
        <div class="container-site py-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.other_pages_in_this_section') }}</h2>
                    <div class="accent-rule"></div>
                </div>
                @if ($page->parent)
                    <a href="{{ \App\Support\LocalizedUrl::to('/' . $page->parent->slug) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 transition hover:gap-2.5">
                        {{ __('feature.whole_section', ['title' => $page->parent->localized('title')]) }} <x-ico name="arrow-right" class="h-4 w-4" aria-hidden="true" />
                    </a>
                @endif
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($neighbours->take(8) as $neighbour)
                    <a href="{{ \App\Support\LocalizedUrl::to('/' . $neighbour->slug) }}" class="card card-interactive group flex items-center gap-3 p-5">
                        <x-ico name="document-text" class="h-5 w-5 shrink-0 text-brand-400" aria-hidden="true" />
                        <span class="min-w-0 flex-1 font-semibold leading-snug text-slate-800 group-hover:text-brand-700">{{ $neighbour->localized('title') }}</span>
                        <x-ico name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600" aria-hidden="true" />
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
