@props(['path', 'alt' => '', 'sized' => false])

@php
    use App\Support\ImageDimensions;
    use App\Support\ImageOptimizer;

    $webpPath = ImageOptimizer::webpPath($path);
    // sized: природні width/height з файла — браузер резервує пропорцію до завантаження (CLS).
    // Потрібно для зображень без фіксованої CSS-висоти (обкладинки статей, логотип).
    $dims = $sized && ! $attributes->has('width') && ! $attributes->has('height') ? ImageDimensions::of($path) : null;
@endphp

<picture>
    @if ($webpPath)
        <source srcset="{{ asset('storage/' . $webpPath) }}" type="image/webp">
    @endif
    <img src="{{ asset('storage/' . $path) }}" alt="{{ $alt }}" @if ($dims) width="{{ $dims['width'] }}" height="{{ $dims['height'] }}" @endif {{ $attributes }}>
</picture>
