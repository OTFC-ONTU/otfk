@php
    $errorLocale = request()->segment(1) === 'en' ? 'en' : 'uk';
    app()->setLocale($errorLocale);
@endphp
<!DOCTYPE html>
<html lang="{{ $errorLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('public.server_error') }}</title>
    {{-- Сторінка навмисне автономна: без Blade-компонентів, маршрутів і зібраних
         ассетів — вона має відрендеритись навіть коли застосунок зламано. --}}
    <style>
        :root{--navy:#16223f;--gold:#d98e1e}
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:#f8fafc;color:var(--navy);display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px}
        .card{position:relative;overflow:hidden;max-width:640px;width:100%;background:#fff;border-radius:16px;padding:40px 32px;box-shadow:0 1px 2px rgba(15,23,42,.06);outline:1px solid #e2e8f0}
        .ghost{position:absolute;right:-8px;top:50%;transform:translateY(-50%);font-size:180px;font-weight:800;line-height:1;color:#eef2f8;pointer-events:none}
        .inner{position:relative;max-width:420px}
        .badge{display:inline-block;background:#fdf5e7;color:#a56a12;border:1px solid #f0d5a5;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        h1{font-size:30px;margin:14px 0 0;line-height:1.2}
        .rule{width:56px;height:4px;background:var(--gold);border-radius:2px;margin:16px 0 0}
        p{color:#64748b;line-height:1.6;margin:20px 0 0;font-size:17px}
        a{display:inline-block;margin-top:26px;background:var(--navy);color:#fff;padding:13px 26px;border-radius:999px;text-decoration:none;font-weight:600}
        a:hover{background:#22304f}
        @media (max-width:520px){.ghost{display:none}h1{font-size:26px}}
    </style>
</head>
<body>
    <div class="card">
        <div class="ghost" aria-hidden="true">500</div>
        <div class="inner">
            <span class="badge">{{ __('feature.error_500') }}</span>
            <h1>{{ __('public.error_title') }}</h1>
            <div class="rule"></div>
            <p>{{ __('feature.an_unexpected_server_error_occurred_please_try') }}</p>
            <a href="/">{{ __('public.back_home') }}</a>
        </div>
    </div>
</body>
</html>
