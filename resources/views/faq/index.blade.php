<x-layouts.app :title="__('public.faq')" :description="__('public.faq_description')">

    {{-- Розмітка FAQPage для розширених результатів Google --}}
    @if ($faqs->isNotEmpty())
        @php
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f->localized('question'),
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->localized('answer')],
                ])->values()->all(),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    <x-page-hero :title="__('public.faq')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.applicants'), 'url' => \App\Support\LocalizedUrl::to('/abituriyentu')],
        ['label' => __('public.faq')],
    ]" />

    <section class="container-site py-12">
        <div class="mx-auto max-w-3xl">
            @if ($faqs->isEmpty())
                <x-empty-state icon="question-mark-circle" :title="__('public.no_faq')" />
            @else
                <div class="space-y-3" x-data="{ open: null }">
                    @foreach ($faqs as $i => $faq)
                        <div class="card overflow-hidden">
                            <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                                <span class="font-semibold text-slate-900">{{ $faq->localized('question') }}</span>
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700 transition"
                                      :class="open === {{ $i }} && 'rotate-180 bg-brand-700 text-white'">
                                    <x-ico name="chevron-down" class="h-4 w-4" />
                                </span>
                            </button>
                            <div x-show="open === {{ $i }}" x-transition.opacity.duration.200ms x-cloak>
                                <div class="border-t border-slate-100 px-5 py-4 text-sm leading-relaxed text-slate-600">
                                    {!! nl2br(e($faq->localized('answer'))) !!}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10 rounded-2xl bg-brand-50 p-6 text-center ring-1 ring-brand-100">
                    <p class="font-semibold text-brand-900">{{ __('public.unanswered') }}</p>
                    <p class="mt-1 text-sm text-brand-700">{{ __('public.faq_apply') }}</p>
                    <a href="{{ \App\Support\LocalizedUrl::route('applicants.create') }}" class="btn-primary mt-4">{{ __('public.apply') }}</a>
                </div>
            @endif
        </div>
    </section>

</x-layouts.app>
