{{-- Кнопки-посилання на сторінці без підсторінок: під текстом, сама сторінка не перетворюється на хаб --}}
@if ($sectionLinks->isNotEmpty())
    <div class="mt-10">
        <h2 class="text-2xl font-extrabold text-brand-950">{{ __('feature.section_links') }}</h2>
        <div class="accent-rule"></div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach ($sectionLinks as $i => $link)
                @include('pages.partials.section-link-card', ['link' => $link, 'number' => $i + 1])
            @endforeach
        </div>
    </div>
@endif
