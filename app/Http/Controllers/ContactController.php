<?php

namespace App\Http\Controllers;

use App\Mail\FeedbackReceived;
use App\Models\FeedbackMessage;
use App\Models\Page;
use App\Models\Setting;
use App\Support\LocalizedUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function index()
    {
        // Текст сторінки контактів — CMS-сторінка зі slug «kontakty» (дослівно з оригіналу); без неї — блок із налаштувань.
        $page = Page::published()->where('slug', 'kontakty')->first();

        return view('contacts', compact('page'));
    }

    public function store(Request $request)
    {
        // Антиспам: приховане поле-пастка (honeypot). Боти його заповнюють.
        if (filled($request->input('website'))) {
            return redirect(LocalizedUrl::route('contacts'))->with('status', __('public.feedback_sent'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $data['ip'] = $request->ip();
        $feedback = FeedbackMessage::create($data);

        // Сповіщення на пошту коледжу — після віддачі відповіді: відвідувач не
        // чекає на SMTP, а збій пошти не ламає форму (звернення вже в БД/CRM).
        $to = Setting::get('feedback_email') ?: Setting::get('contact_email');
        if ($to) {
            dispatch(function () use ($to, $feedback) {
                try {
                    Mail::to($to)->send(new FeedbackReceived($feedback));
                } catch (\Throwable $e) {
                    report($e);
                }
            })->afterResponse();
        }

        return redirect(LocalizedUrl::route('contacts'))->with('status', __('public.feedback_sent'));
    }
}
