{{-- Кнопки-посилання на сторінці без підсторінок: $links; $top — над текстом (прапорець «Вгорі»), без заголовка.
     Сама сторінка при цьому не перетворюється на хаб. --}}
@if ($links->isNotEmpty())
    <div @class(['mb-8' => $top ?? false, 'mt-10' => ! ($top ?? false)])>
        @unless ($top ?? false)
            <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.section_links') }}</h2>
            <div class="accent-rule"></div>
        @endunless
        <div @class(['grid gap-4 sm:grid-cols-2', 'mt-6' => ! ($top ?? false)])>
            @foreach ($links as $i => $link)
                @include('pages.partials.section-link-card', ['link' => $link, 'number' => ($top ?? false) ? null : $i + 1])
            @endforeach
        </div>
    </div>
@endif
