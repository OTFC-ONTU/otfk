{{-- Превʼю несохранённої форми в iframe (slide-over поруч із формою).
     URL — одноразовий слепок у кеші (10 хв), те саме посилання можна відкрити вкладкою. --}}
<div class="flex h-full flex-col gap-3">
    <a href="{{ $url }}" target="_blank" rel="noopener"
       class="text-sm font-medium text-primary-600 hover:underline">
        Відкрити у новій вкладці ↗
    </a>

    <iframe src="{{ $url }}" title="Попередній перегляд"
            class="w-full rounded-lg border border-gray-200 bg-white"
            style="height: calc(100vh - 14rem);"></iframe>
</div>
