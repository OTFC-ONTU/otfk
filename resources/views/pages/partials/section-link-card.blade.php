{{-- Кнопка-посилання у вигляді плитки підсторінки: $link, $number, необовʼязковий $searchIndex для пошуку на хабі --}}
<a href="{{ \App\Support\LocalizedUrl::to($link->url) }}"
   @if ($link->open_new_tab) target="_blank" @endif
   @if ($link->rel()) rel="{{ $link->rel() }}" @endif
   @isset($searchIndex) x-show="match({{ $searchIndex }})" @endisset
   class="card card-interactive group flex items-center gap-4 p-4">
    <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm font-bold text-brand-800 transition group-hover:bg-brand-950 group-hover:text-gold-400">
        {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
    </span>
    <span class="min-w-0 flex-1 font-semibold leading-snug text-slate-800 group-hover:text-brand-700">
        {{ $link->localized('title') }}
        @if ($link->open_new_tab)
            <span class="sr-only">({{ __('feature.opens_in_new_tab') }})</span>
        @endif
    </span>
    @if ($link->open_new_tab)
        <x-ico name="arrow-top-right-on-square" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-brand-600" aria-hidden="true" />
    @else
        <x-ico name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600" aria-hidden="true" />
    @endif
</a>
