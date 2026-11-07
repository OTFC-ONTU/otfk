{{-- Кнопки-посилання на сторінці без підсторінок: $links; $top — над текстом (прапорець «Вгорі»), без нумерації.
     Окремого заголовка немає; сама сторінка не перетворюється на хаб. --}}
@if ($links->isNotEmpty())
    <div @class(['grid gap-4 sm:grid-cols-2', 'mb-8' => $top ?? false, 'mt-10' => ! ($top ?? false)])>
        @foreach ($links as $i => $link)
            @include('pages.partials.section-link-card', ['link' => $link, 'number' => ($top ?? false) ? null : $i + 1])
        @endforeach
    </div>
@endif
