@props(['href', 'title', 'extension' => 'PDF', 'meta' => '', 'description' => '', 'download' => false])

<div class="file-card-container">
<div {{ $attributes->class('file-card not-prose') }}>
    <span class="file-card__icon" aria-hidden="true"><x-ico name="document-text" class="h-6 w-6" /></span>
    <div class="file-card__body">
        @if ($href)<a href="{{ $href }}" target="_blank" rel="noopener" class="file-card__title">{{ $title }}</a>@else<span class="file-card__title">{{ $title }}</span>@endif
        <span class="file-card__meta">{{ strtoupper($extension) }}@if ($meta)@if ($extension) · @endif{{ $meta }}@endif</span>
        @if ($description)<p class="file-card__description">{{ $description }}</p>@endif
    </div>
    @if ($href)<div class="file-card__actions">
        <a href="{{ $href }}" target="_blank" rel="noopener" class="file-card__download" aria-label="{{ __('feature.view').': '.$title }}">
            <x-ico name="eye" class="h-4 w-4" aria-hidden="true" /><span class="file-card__view-label">{{ __('feature.view') }}</span>
        </a>
        <a href="{{ $href }}" @if ($download) download @else target="_blank" @endif rel="noopener" class="file-card__download" aria-label="{{ __('public.download').': '.$title }}">
            <x-ico name="arrow-down-tray" class="h-4 w-4" aria-hidden="true" /><span class="file-card__download-label">{{ __('public.download') }}</span>
        </a>
    </div>@endif
</div>
</div>
