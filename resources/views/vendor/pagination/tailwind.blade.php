{{-- Українська пагінація в стилі сайту (перекриває дефолтний pagination::tailwind) --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('feature.pagination') }}">
        <div class="flex flex-wrap items-center justify-center gap-2">
            {{-- Попередня --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-default items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-slate-300">
                    <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('feature.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100">
                    <x-ico name="arrow-left" class="h-4 w-4" /> {{ __('feature.previous') }}
                </a>
            @endif

            {{-- Номери сторінок --}}
            <span class="hidden items-center gap-1.5 sm:inline-flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1.5 text-sm text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page"
                                      class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-700 text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('feature.page_number', ['page' => $page]) }}"
                                   class="inline-flex h-9 w-9 items-center justify-center rounded-full text-sm font-medium text-slate-600 transition hover:bg-slate-100">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            {{-- Поточна сторінка на мобільних --}}
            <span class="text-sm font-medium text-slate-500 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            {{-- Наступна --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100">
                    {{ __('feature.next') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </a>
            @else
                <span class="inline-flex cursor-default items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-slate-300">
                    {{ __('feature.next') }} <x-ico name="arrow-right" class="h-4 w-4" />
                </span>
            @endif
        </div>

        <p class="mt-3 text-center text-sm text-slate-400">
            {{ __('feature.showing_range', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
    </nav>
@endif
