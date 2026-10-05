@php $errorLocale = request()->is('en', 'en/*') ? 'en' : 'uk'; @endphp
<!DOCTYPE html>
<html lang="{{ $errorLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public.maintenance', [], $errorLocale) }}</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:#0c2547;color:#fff;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;text-align:center;padding:24px;box-sizing:border-box}
        .code{font-size:80px;font-weight:800;color:#f4b740;line-height:1}
        .title{font-size:24px;margin:14px 0 8px}
        .text{color:#cbd5e1;max-width:440px;line-height:1.6;margin:0 auto}
    </style>
</head>
<body>
    <div>
        <div class="code">503</div>
        <div class="title">{{ __('public.maintenance', [], $errorLocale) }}</div>
        <p class="text">{{ __('public.maintenance_text', [], $errorLocale) }}</p>
    </div>
</body>
</html>
