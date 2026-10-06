@props(['theme' => null])

@php
    // Святкові прикраси сайту: перефарбований «хром» (навігація/підвал),
    // стрічка + гірлянда над шапкою, кутові емодзі та короткий «залп» частинок.
    // Тема обирається в адмінці («Підвал і вигляд»), довідник — App\Support\HolidayTheme.
    // Атрибут data-holiday на <body> ставить layout — на нього спираються селектори нижче.
    $holiday = \App\Support\HolidayTheme::config($theme);
@endphp

@if ($holiday)
    <div class="holiday-decor" data-holiday="{{ $theme }}" aria-hidden="true">
        {{-- Святкова стрічка на самій горі сторінки (прокручується разом зі сторінкою) --}}
        <div class="holiday-ribbon" style="background: {{ $holiday['ribbon'] }}"></div>

        {{-- Гірлянда, що «звисає» зі стрічки --}}
        @if (! empty($holiday['garland']))
            <div class="holiday-garland">{{ str_repeat($holiday['garland'], (int) ceil(60 / mb_strlen($holiday['garland']))) }}</div>
        @endif

        {{-- «Залп» падаючих частинок при завантаженні: кожна падає один раз,
             через ~12 с шар ховається повністю. Позиції/тайминги детерміновані від індексу. --}}
        @if ($holiday['particles'] !== [])
            <div class="holiday-particles{{ ($holiday['mono'] ?? false) ? ' holiday-particles-mono' : '' }}">
                @for ($i = 0; $i < 18; $i++)
                    <span style="left: {{ ($i * 83 + 7) % 100 }}%;
                                 font-size: {{ number_format(0.9 + ($i % 3) * 0.4, 2, '.', '') }}rem;
                                 animation-duration: {{ number_format(5 + ($i % 5) * 0.9, 1, '.', '') }}s;
                                 animation-delay: {{ number_format(($i % 6) * 0.45, 2, '.', '') }}s;">{{ $holiday['particles'][$i % count($holiday['particles'])] }}</span>
                @endfor
            </div>
        @endif

        {{-- Великі емодзі в нижніх кутах екрана (лише на широких екранах) --}}
        @foreach (array_slice($holiday['corners'] ?? [], 0, 2) as $ci => $corner)
            <div class="holiday-corner {{ $ci === 0 ? 'holiday-corner-right' : 'holiday-corner-left' }}">{{ $corner }}</div>
        @endforeach
    </div>

    <style>
        /* Перефарбовування темного «хрому» сайту в кольори свята:
           верхня утилітарна смуга та навігація в шапці, підвал. */
        body[data-holiday] header .bg-brand-950 { background: {{ $holiday['chrome_dark'] }}; }
        body[data-holiday] header nav.bg-brand-900 { background: {{ $holiday['chrome'] }}; }
        body[data-holiday] footer.bg-brand-950 {
            background: {{ $holiday['chrome_dark'] }};
            border-top-color: {{ $holiday['accent'] }};
        }

        .holiday-ribbon { height: 8px; }

        .holiday-garland {
            height: 1.5rem;
            margin-top: -2px;
            overflow: hidden;
            text-align: center;
            font-size: 0.85rem;
            line-height: 1.5rem;
            letter-spacing: 1.9rem;
            white-space: nowrap;
            color: {{ $holiday['accent'] }};
            background: {{ $holiday['chrome_dark'] }};
        }

        .holiday-particles {
            position: fixed;
            inset: 0;
            z-index: 25; /* під липкою шапкою (z-40), над контентом */
            overflow: hidden;
            pointer-events: none;
            /* після «залпу» шар ховається зовсім */
            animation: holiday-particles-hide 0s linear 12s forwards;
        }

        .holiday-particles span {
            position: absolute;
            top: -8vh;
            opacity: 0.85;
            animation-name: holiday-fall;
            animation-timing-function: linear;
            animation-iteration-count: 1;
            animation-fill-mode: forwards;
            will-change: transform;
        }

        .holiday-particles-mono span {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 700;
            color: #4ade80;
            opacity: 0.6;
        }

        @keyframes holiday-fall {
            to { transform: translateY(120vh) rotate(300deg); }
        }

        @keyframes holiday-particles-hide {
            to { visibility: hidden; }
        }

        .holiday-corner {
            position: fixed;
            bottom: 0.75rem;
            z-index: 24;
            display: none;
            font-size: 4rem;
            line-height: 1;
            opacity: 0.9;
            filter: drop-shadow(0 2px 3px rgb(0 0 0 / 0.3));
            pointer-events: none;
        }

        .holiday-corner-right { right: 1rem; transform: rotate(6deg); }
        .holiday-corner-left { left: 1rem; transform: rotate(-6deg); }

        @media (min-width: 1024px) {
            .holiday-corner { display: block; }
        }

        /* Бейдж біля логотипа в шапці (рендериться в layout, коли тема активна) */
        .holiday-logo-badge {
            position: absolute;
            top: -0.45rem;
            left: -0.55rem;
            z-index: 1;
            font-size: 1.15rem;
            line-height: 1;
            transform: rotate(-18deg);
            filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.25));
            pointer-events: none;
        }

        @media (prefers-reduced-motion: reduce) {
            .holiday-particles { display: none; }
        }
    </style>
@endif
