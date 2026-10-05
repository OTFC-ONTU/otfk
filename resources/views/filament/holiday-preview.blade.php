@php
    // Спрощений макет шапки та підвалу сайту з обраною святковою темою
    // (стилі inline: CSS публічного сайту в адмінці не підключено).
    $top = $theme['top'] ?? '#16223f';
    $nav = $theme['nav'] ?? '#1f3568';
    $accent = $theme['accent'];
    $brand = \App\Models\Setting::get('brand_short') ?: __('layout.brand_short');
    $brandName = \App\Models\Setting::get('brand_name') ?: __('layout.brand_name');
@endphp

@if ($theme)
    <div style="overflow:hidden;border-radius:14px;border:1px solid rgb(148 163 184 / .35);background:#fff;color:#334155;font-family:Inter,system-ui,sans-serif;">
        <div style="height:24px;background:{{ $top }};"></div>
        <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;">
            <span style="position:relative;display:inline-grid;place-items:center;width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#284aaa,#1f3568);color:#fff;font-weight:800;">
                <x-holiday.badge :theme="$theme" :size="22" style="position:absolute;top:-8px;left:-9px;transform:rotate(-12deg);" />
                О
            </span>
            <span style="line-height:1.2;">
                <span style="display:block;font-weight:800;color:#1f3568;font-size:16px;">{{ $brand }}</span>
                <span style="display:block;font-size:12px;color:#64748b;">{{ $brandName }}</span>
            </span>
        </div>
        <div style="position:relative;">
            <div style="display:flex;gap:22px;padding:11px 20px;background:{{ $nav }};box-shadow:inset 0 -2px 0 {{ $accent }}8c;color:rgb(255 255 255 / .9);font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;">
                <span>Головна</span><span>Про коледж</span><span>Абітурієнту</span><span>Студенту</span><span>Новини</span><span>Контакти</span>
            </div>
            <x-holiday.garland :theme="$theme" :tiles="8" style="position:absolute;left:0;right:0;top:100%;" />
        </div>
        <div style="height:120px;background:linear-gradient(135deg,#e2e8f0,#f1f5f9);"></div>
        <div style="border-top:3px solid {{ $accent }};background:{{ $top }};">
            <div style="display:flex;align-items:center;justify-content:center;gap:12px;padding:16px 20px;color:{{ $accent }};font-weight:700;font-size:17px;text-align:center;">
                <x-holiday.badge :theme="$theme" :size="28" />
                {{ __('layout.holiday.'.$key) }}
            </div>
            <div style="height:36px;background:rgb(0 0 0 / .15);"></div>
        </div>
    </div>
    <p style="margin-top:8px;font-size:12px;color:#64748b;">
        Макет спрощений. На сайті гірлянда ховається під час прокручування, а частинки
        @switch($theme['particles']['type'])
            @case('snow') (сніжинки) @break
            @case('leaves') (осіннє листя) @break
            @case('petals') (пелюстки) @break
            @case('confetti') (конфеті) @break
            @case('code') (символи коду) @break
            @case('sparks') (іскри) @break
        @endswitch
        падають ~10 секунд при першому відкритті сайту.
    </p>
@endif
