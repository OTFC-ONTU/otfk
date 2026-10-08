{{-- «Налаштування cookies» у підвалі: знову відкриває банер згоди (resources/js/analytics.js знімає hidden). --}}
@if (\App\Support\Analytics::bannerVisible())
    <button type="button" data-analytics-settings hidden
            class="underline underline-offset-2 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 rounded">{{ __('layout.consent.settings') }}</button>
@endif
