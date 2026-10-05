<x-layouts.app :title="__('public.public_information')">

    <x-page-hero :title="__('public.public_information')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.public_information')],
    ]" />

    <section class="container-site py-12">
        @if ($categories->isNotEmpty())
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $cat)
                    <a href="{{ \App\Support\LocalizedUrl::route('documents.category', $cat) }}" class="card card-interactive group flex items-start gap-4 p-6">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 transition group-hover:bg-brand-700 group-hover:text-white">
                            <x-ico name="folder" class="h-6 w-6" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-slate-900 group-hover:text-brand-700">{{ $cat->localized('title') }}</span>
                            <span class="mt-1 block text-sm text-slate-500">{{ __('public.documents_count', ['count' => $cat->documents_count]) }}</span>
                        </span>
                        <x-ico name="arrow-right" class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-1 group-hover:text-brand-600" />
                    </a>
                @endforeach
            </div>
        @else
            <x-empty-state icon="folder" :title="__('public.no_document_categories')" />
        @endif
    </section>

</x-layouts.app>
