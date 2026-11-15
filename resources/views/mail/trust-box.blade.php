{{-- Лист «Скриньки довіри» (App\Mail\TrustBoxMessage): текст екранується, посилання не стають клікабельними. --}}
<!DOCTYPE html>
<html lang="uk">
<head><meta charset="utf-8"><title>Скринька довіри</title></head>
<body style="margin:0;padding:24px;background:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
    <tr><td style="padding:22px 24px 8px;">
        <p style="margin:0;font-size:18px;font-weight:bold;color:#1f3568;">
            {{ $test ? 'Тестовий лист «Скриньки довіри»' : 'Нове анонімне звернення' }}
        </p>
    </td></tr>
    @if ($test)
        <tr><td style="padding:8px 24px 22px;font-size:14px;line-height:1.6;">
            Налаштування пошти працюють: звернення з сайту надходитимуть на цю адресу.
        </td></tr>
    @else
        <tr><td style="padding:8px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;font-size:13px;">
                <tr><td style="padding:6px 12px;color:#6b7280;width:150px;">Тема</td><td style="padding:6px 12px;">{{ $topic ?? 'не вказано' }}</td></tr>
                <tr><td style="padding:6px 12px;color:#6b7280;border-top:1px solid #f1f3f4;">Надіслано</td><td style="padding:6px 12px;border-top:1px solid #f1f3f4;">{{ $sentAt }}</td></tr>
                <tr><td style="padding:6px 12px;color:#6b7280;border-top:1px solid #f1f3f4;">Мова сторінки</td><td style="padding:6px 12px;border-top:1px solid #f1f3f4;">{{ $language }}</td></tr>
            </table>
        </td></tr>
        <tr><td style="padding:14px 24px;">
            <div style="border-left:3px solid #c2d6f2;padding-left:12px;font-size:15px;line-height:1.6;white-space:pre-line;">{{ $text }}</div>
        </td></tr>
    @endif
    <tr><td style="padding:12px 24px 20px;font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;">
        Лист надіслано автоматично через форму «Скринька довіри» на сайті {{ $site }}.
        Автор анонімний: сайт не зберіг текст і не знає, хто його надіслав. Відповідь на цей лист нікому не надійде.
    </td></tr>
</table>
</body>
</html>
