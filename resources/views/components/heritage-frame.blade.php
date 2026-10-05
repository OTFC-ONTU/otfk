@props(['date' => null, 'showSignoff' => true])

@php
    $dateline = __('public.city');
    if ($date) {
        $dateline .= ' · ' . $date->copy()->locale(app()->getLocale())->translatedFormat('j F Y');
    }
@endphp

<div class="heritage-frame">
    <p class="heritage-dateline">{{ $dateline }}</p>
    <div class="heritage-rule" aria-hidden="true"></div>

    {{ $slot }}

    <div class="heritage-seal" aria-hidden="true" title="{{ __('public.heritage_official') }}">
        <x-ico name="academic-cap" class="h-7 w-7" />
    </div>

    @if ($showSignoff)
        <p class="heritage-signoff">
            {{ __('public.regards') }}<br>
            <span class="not-italic font-semibold text-brand-900">{{ app()->getLocale() === 'en' ? \App\Models\Setting::publicGet('brand_name', __('layout.brand_name')) : config('app.name') }}</span>
        </p>
    @endif
</div>
