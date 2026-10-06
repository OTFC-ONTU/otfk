@props(['theme', 'tiles' => 18])

@php
    // Святкова SVG-гірлянда, що «звисає» під шапкою (див. App\Support\HolidayTheme).
    // Малюється однаковими плитками по 160px (вишивка — візерунком), тож ширина
    // будь-яка; стилі анімацій — у <style> нижче, щоб працювало й у прев'ю адмінки.
    $garland = $theme['garland'];
    $colors = $garland['colors'];
    $type = $garland['type'];
    $sag = fn (float $x) => 3 + 24 * ($x / 160) * (1 - $x / 160);
    $ink = fn (string $color) => (hexdec(substr($color, 1, 2)) * .299
        + hexdec(substr($color, 3, 2)) * .587 + hexdec(substr($color, 5, 2)) * .114) > 160
        ? ($theme['nav'] ?? '#1f3568') : '#ffffff';
@endphp

<div {{ $attributes->class('hd-garland hd-garland-'.$type) }} aria-hidden="true">
    @if ($type === 'embroidery')
        @php $pid = 'hd-emb-'.substr(md5(json_encode($colors)), 0, 6); @endphp
        <svg class="hd-garland-strip" width="100%" height="24" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="{{ $pid }}" width="36" height="24" patternUnits="userSpaceOnUse">
                    <rect width="36" height="24" fill="{{ $colors[2] }}" />
                    <path d="M0 2h36M0 22h36" stroke="{{ $colors[0] }}" stroke-width="2" stroke-dasharray="2 2" />
                    <path d="M0 5h36M0 19h36" stroke="{{ $colors[1] }}" stroke-opacity=".5" stroke-dasharray="1 3" />
                    <path d="m18 5 7 7-7 7-7-7Z" fill="{{ $colors[0] }}" />
                    <path d="m18 8 4 4-4 4-4-4Z" fill="{{ $colors[2] }}" />
                    <path d="m18 10 2 2-2 2-2-2Z" fill="{{ $colors[1] }}" />
                    <path d="M2 9l6 6M8 9l-6 6M28 9l6 6M34 9l-6 6" stroke="{{ $colors[0] }}" stroke-width="2" />
                </pattern>
            </defs>
            <rect width="100%" height="24" fill="url(#{{ $pid }})" />
        </svg>
    @else
        <div class="hd-garland-row">
            @for ($t = 0; $t < $tiles; $t++)
                <svg width="160" height="48" viewBox="0 0 160 48" xmlns="http://www.w3.org/2000/svg">
                    @if ($type === 'lights')
                        <path d="M0 3Q80 15 160 3" fill="none" stroke="{{ $theme['accent'] }}" stroke-opacity=".65" stroke-width="1.4" />
                        @foreach ([20, 60, 100, 140] as $i => $x)
                            @php
                                $n = $t * 4 + $i;
                                $c = $colors[$n % count($colors)];
                                $y = $sag($x);
                                $delay = number_format(fmod($n * 0.37, 2.4), 2, '.', '');
                            @endphp
                            @if ($i % 2 === 1)
                                <path d="M{{ $x }} {{ $y }}v7" stroke="{{ $theme['accent'] }}" stroke-width="1.2" />
                                <g transform="translate({{ $x - 10 }},{{ $y + 6 }})">
                                    <g class="hd-flag">
                                        <circle cx="10" cy="11" r="10" fill="{{ $theme['nav'] ?? '#1f3568' }}" stroke="{{ $theme['accent'] }}" />
                                        <x-holiday.motif :theme="$theme" x="3" y="4" width="14" height="14" style="color:{{ $theme['accent'] }}" />
                                    </g>
                                </g>
                            @else
                                <g class="hd-bulb" style="animation-delay: -{{ $delay }}s">
                                    <circle cx="{{ $x }}" cy="{{ $y + 10 }}" r="12" fill="{{ $c }}" opacity=".18" />
                                    <ellipse cx="{{ $x }}" cy="{{ $y + 10 }}" rx="4" ry="6" fill="{{ $c }}" />
                                    <ellipse cx="{{ $x - 1.3 }}" cy="{{ $y + 7.6 }}" rx="1.1" ry="2" fill="#fff" opacity=".7" />
                                </g>
                                <rect x="{{ $x - 2.5 }}" y="{{ $y - 0.5 }}" width="5" height="5" rx="1" fill="#475569" />
                            @endif
                        @endforeach
                    @else
                        <path d="M0 3Q80 15 160 3" fill="none" stroke="#94a3b8" stroke-width="1.2" />
                        @foreach ([20, 60, 100, 140] as $i => $x)
                            @php
                                $n = $t * 4 + $i;
                                $c = $colors[$n % count($colors)];
                                $y = $sag($x);
                            @endphp
                            <g transform="translate({{ $x - 11 }},{{ $y }})">
                                <g class="hd-flag" style="animation-delay: -{{ number_format(fmod($n * 0.53, 4), 2, '.', '') }}s">
                                    @if ($theme['badge'] === 'egg')
                                        <path d="M11 0C5 0 1 12 1 18a10 10 0 0 0 20 0C21 12 17 0 11 0Z" fill="{{ $c }}" />
                                        <path d="M2 16q4-4 9 0t9 0M3 22q4-4 8 0t8 0" fill="none" stroke="#fff" stroke-width="2" opacity=".8" />
                                    @else
                                        <path d="M0 0H22V28L11 23 0 28Z" fill="{{ $c }}" />
                                        <x-holiday.motif :theme="$theme" x="4" y="5" width="14" height="14" style="color:{{ $ink($c) }}" />
                                    @endif
                                </g>
                            </g>
                        @endforeach
                    @endif
                </svg>
            @endfor
        </div>
    @endif
</div>

@once
    <style>
        .hd-garland { pointer-events: none; overflow: hidden; line-height: 0; }
        .hd-garland-row { display: flex; width: max-content; }
        .hd-garland-row svg { flex: none; display: block; }
        .hd-bulb { animation: hd-twinkle 3.6s ease-in-out infinite; }
        .hd-flag { transform-box: fill-box; transform-origin: 50% 0; animation: hd-sway 4s ease-in-out infinite; }
        @keyframes hd-twinkle { 0%, 100% { opacity: 1; } 50% { opacity: .65; } }
        @keyframes hd-sway { 0%, 100% { transform: rotate(-2deg); } 50% { transform: rotate(2deg); } }
        @media (prefers-reduced-motion: reduce) { .hd-bulb, .hd-flag { animation: none; } }
    </style>
@endonce
