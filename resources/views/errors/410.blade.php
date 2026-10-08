{{-- 410 Gone: матеріал свідомо видалено (запис карти legacy_redirects з дією «gone») --}}
@include('errors.partials.missing', [
    'code' => 410,
    'badge' => __('feature.error_410'),
    'title' => __('public.gone'),
    'text' => __('public.gone_text'),
    'moved' => __('public.gone_next'),
])
