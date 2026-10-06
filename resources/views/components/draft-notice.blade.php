{{-- Плашка режиму чернетки/превʼю: контент видно лише залогіненим адміністраторам --}}
@props(['message' => __('feature.draft_unpublished_only_signedin_administrators_can_view')])

<div class="border-b border-amber-200 bg-amber-50">
    <div class="container-site flex items-center gap-2 py-2.5 text-sm font-medium text-amber-800">
        <x-ico name="eye-slash" class="h-4 w-4 shrink-0" />
        <span>{{ $message }}</span>
    </div>
</div>
