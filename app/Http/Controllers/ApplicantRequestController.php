<?php

namespace App\Http\Controllers;

use App\Mail\ApplicantRequestReceived;
use App\Models\ApplicantRequest;
use App\Models\Setting;
use App\Models\Specialty;
use App\Support\LocalizedUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ApplicantRequestController extends Controller
{
    public function create()
    {
        $specialties = Specialty::published()->ordered()->get();

        return view('applicants.create', compact('specialties'));
    }

    public function store(Request $request)
    {
        // Антиспам: приховане поле-пастка
        if (filled($request->input('website'))) {
            return redirect(LocalizedUrl::route('applicants.create'))->with('status', __('public.application_bot_sent'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['ip'] = $request->ip();
        $applicant = ApplicantRequest::create($data);

        // Лист приймальній комісії — після віддачі відповіді: абітурієнт не чекає
        // на SMTP, а збій пошти не ламає форму (заявка вже збережена в БД/CRM).
        $to = Setting::get('feedback_email') ?: Setting::get('contact_email');
        if ($to) {
            dispatch(function () use ($to, $applicant) {
                try {
                    Mail::to($to)->send(new ApplicantRequestReceived($applicant));
                } catch (\Throwable $e) {
                    report($e);
                }
            })->afterResponse();
        }

        return redirect(LocalizedUrl::route('applicants.create'))->with('status', __('public.application_sent'));
    }
}
