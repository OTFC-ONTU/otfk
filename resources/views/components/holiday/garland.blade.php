@props(['theme', 'tiles' => 18])

@php
    // Святкова SVG-гірлянда, що «звисає» під шапкою (див. App\Support\HolidayTheme).
    // Малюється однаковими плитками по 160px (вишивка — візерунком), тож ширина
    // будь-яка; стилі анімацій — у <style> нижче, щоб працювало й у прев'ю адмінки.
    $garland = $theme['garland'];
    $colors = $garland['colors'];
    $type = $garland['type'];
    $sag = fn (float $x) => 3 + 20 * ($x / 80) * (1 - $x / 80); // провис мотузки на ділянці 0..80
@endphp

<div {{ $attributes->class('hd-garland hd-garland-'.$type) }} aria-hidden="true">
    @if ($type === 'embroidery')
        @php $pid = 'hd-emb-'.substr(md5(json_encode($colors)), 0, 6); @endphp
        <svg class="hd-garland-strip" width="100%" height="16" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="{{ $pid }}" width="36" height="16" patternUnits="userSpaceOnUse">
                    <rect width="36" height="16" fill="{{ $colors[2] }}" />
                    <rect width="36" height="1.4" fill="{{ $colors[1] }}" />
                    <rect y="14.6" width="36" height="1.4" fill="{{ $colors[1] }}" />
                    <polygon points="18,2.6 26,8 18,13.4 10,8" fill="{{ $colors[0] }}" />
                    <polygon points="18,5.6 20.6,8 18,10.4 15.4,8" fill="{{ $colors[1] }}" />
                    <path d="M2 5.5l4 5M6 5.5l-4 5M30 5.5l4 5M34 5.5l-4 5" stroke="{{ $colors[0] }}" stroke-width="1.5" stroke-linecap="round" />
                </pattern>
            </defs>
            <rect width="100%" height="16" fill="url(#{{ $pid }})" />
        </svg>
    @else
        <div class="hd-garland-row">
            @for ($t = 0; $t < $tiles; $t++)
                <svg width="160" height="40" viewBox="0 0 160 40" xmlns="http://www.w3.org/2000/svg">
                    @if ($type === 'lights')
                        <path d="M0 4Q40 18 80 4T160 4" fill="none" stroke="#1f2937" stroke-width="1.4" />
                        @foreach ([20, 60, 100, 140] as $i => $x)
                            @php
                                $n = $t * 4 + $i;
                                $c = $colors[$n % count($colors)];
                                $y = $sag($x % 80);
                                $delay = number_format(fmod($n * 0.37, 2.4), 2, '.', '');
                            @endphp
                            <g class="hd-bulb" style="animation-delay: -{{ $delay }}s">
                                <circle cx="{{ $x }}" cy="{{ $y + 10 }}" r="9" fill="{{ $c }}" opacity=".28" />
                                <ellipse cx="{{ $x }}" cy="{{ $y + 10 }}" rx="4" ry="6" fill="{{ $c }}" />
                                <ellipse cx="{{ $x - 1.3 }}" cy="{{ $y + 7.6 }}" rx="1.1" ry="2" fill="#fff" opacity=".6" />
                            </g>
                            <rect x="{{ $x - 2.5 }}" y="{{ $y - 0.5 }}" width="5" height="5" rx="1" fill="#1f2937" />
                        @endforeach
                    @else
                        <path d="M0 3Q40 13 80 3T160 3" fill="none" stroke="#475569" stroke-width="1.2" />
                        @foreach ([10, 30, 50, 70, 90, 110, 130, 150] as $i => $x)
                            @php
                                $n = $t * 8 + $i;
                                $c = $colors[$n % count($colors)];
                                $y = $sag($x % 80);
                            @endphp
                            <g class="hd-flag" style="animation-delay: -{{ number_format(fmod($n * 0.53, 3), 2, '.', '') }}s">
                                <polygon points="{{ $x - 8.5 }},{{ $y }} {{ $x + 8.5 }},{{ $y }} {{ $x }},{{ $y + 19 }}" fill="{{ $c }}" />
                                <polygon points="{{ $x }},{{ $y }} {{ $x + 8.5 }},{{ $y }} {{ $x }},{{ $y + 19 }}" fill="#000" opacity=".08" />
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
        .hd-bulb { animation: hd-twinkle 2.4s ease-in-out infinite; }
        .hd-flag { transform-box: fill-box; transform-origin: 50% 0; animation: hd-sway 3s ease-in-out infinite; }
        @keyframes hd-twinkle { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
        @keyframes hd-sway { 0%, 100% { transform: rotate(-4deg); } 50% { transform: rotate(4deg); } }
        @media (prefers-reduced-motion: reduce) { .hd-bulb, .hd-flag { animation: none; } }
    </style>
@endonce
