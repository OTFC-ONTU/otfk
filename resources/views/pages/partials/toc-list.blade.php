{{-- Список якорів по сторінці — спільний для сайдбару (десктоп) і згортання (мобільний) --}}

<ul class="space-y-2 text-sm">
    @foreach ($headings as $heading)
        <li>
            <a href="#{{ $heading['id'] }}"
               @class([
                   'flex items-start gap-2 py-1 font-semibold text-slate-700 transition hover:text-brand-700',
                   'pl-4 font-medium text-slate-600' => $heading['level'] > 2,
               ])>
                <x-ico name="chevron-right" class="mt-1 h-3.5 w-3.5 shrink-0 text-gold-500" aria-hidden="true" />
                <span>{{ $heading['text'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
