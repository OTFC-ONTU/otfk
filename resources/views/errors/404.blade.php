@include('errors.partials.missing', [
    'code' => 404,
    'badge' => __('feature.error_404'),
    'title' => __('public.not_found'),
    'text' => __('public.not_found_text'),
    'moved' => __('public.not_found_moved'),
])
