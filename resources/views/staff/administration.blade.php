<x-layouts.app :title="__('public.administration')">

    <x-page-hero :title="__('public.college_administration')" :breadcrumbs="[
        ['label' => __('public.home'), 'url' => \App\Support\LocalizedUrl::route('home')],
        ['label' => __('public.administration')],
    ]" />

    <section class="container-site py-12">
        @if ($staff->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($staff as $person)
                    <x-staff-card :person="$person" />
                @endforeach
            </div>
        @else
            <x-empty-state icon="users" :title="__('public.no_administration')" />
        @endif
    </section>

</x-layouts.app>
