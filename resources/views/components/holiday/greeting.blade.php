@props(['theme', 'holidayKey'])

<div {{ $attributes->class('holiday-greeting') }} style="position:relative;overflow:hidden;text-align:center;color:{{ $theme['accent'] }};background:radial-gradient(ellipse at 50% 0%,{{ $theme['accent'] }}26,transparent 70%),{{ $theme['top'] ?? '#16223f' }};border-bottom:1px solid #ffffff14;">
    <x-holiday.garland :theme="$theme" :tiles="18" />
    <div style="display:flex;align-items:center;justify-content:center;gap:16px;max-width:900px;margin:auto;padding:12px 24px 28px;">
        <span style="height:1px;flex:1;background:linear-gradient(90deg,transparent,{{ $theme['accent'] }}80);" aria-hidden="true"></span>
        <div style="display:flex;flex-direction:column;align-items:center;gap:12px;max-width:640px;">
            <x-holiday.badge :theme="$theme" :size="42" />
            <p class="font-display" style="margin:0;font-size:clamp(17px,2vw,23px);font-weight:700;line-height:1.4;text-wrap:balance;">{{ __('layout.holiday.'.$holidayKey) }}</p>
        </div>
        <span style="height:1px;flex:1;background:linear-gradient(90deg,{{ $theme['accent'] }}80,transparent);" aria-hidden="true"></span>
    </div>
</div>
