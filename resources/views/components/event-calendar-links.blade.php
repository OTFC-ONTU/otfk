@props(['event'])

{{-- Кнопки «додати в календар»: Google (посилання) і .ics для Apple/Outlook --}}
<div {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    <a href="{{ $event->google_calendar_url }}" target="_blank" rel="noopener"
       class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:text-brand-800 hover:ring-brand-200"
       title="{{ __('public.add_google_calendar') }}">
        <x-ico name="calendar-days" class="h-3.5 w-3.5" aria-hidden="true" /> {{ __('public.google_calendar') }}
    </a>
    <a href="{{ \App\Support\LocalizedUrl::route('events.ics', $event) }}"
       class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:text-brand-800 hover:ring-brand-200"
       title="{{ __('public.download_ics') }}">
        <x-ico name="arrow-down-tray" class="h-3.5 w-3.5" aria-hidden="true" /> {{ __('feature.download_ics') }}
    </a>
</div>
