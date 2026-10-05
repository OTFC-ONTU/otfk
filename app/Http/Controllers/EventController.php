<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\LocalizedUrl;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index()
    {
        $upcoming = Event::published()->upcoming()->get();
        $past = Event::published()->past()->limit(6)->get();

        return view('events.index', compact('upcoming', 'past'));
    }

    /** Файл .ics — «Додати в календар» (Apple/Outlook/будь-який клієнт). */
    public function ics(Event $event)
    {
        abort_unless($event->is_published, 404);

        $esc = fn (?string $v) => str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n'],
            trim((string) $v)
        );
        $fmt = fn ($c) => $c->format('Ymd\THis\Z');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OTFK ONTU//Podiyi//UK',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()->utc()),
            'DTSTART:'.$fmt($event->utcStart()),
            'DTEND:'.$fmt($event->utcEnd()),
            'SUMMARY:'.$esc($event->localized('title')),
        ];

        if (filled($event->localized('description'))) {
            $lines[] = 'DESCRIPTION:'.$esc(Str::limit($event->localized('description'), 500));
        }
        if (filled($event->localized('location'))) {
            $lines[] = 'LOCATION:'.$esc($event->localized('location'));
        }

        $lines[] = 'URL:'.LocalizedUrl::route('events');
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="podiya-'.$event->id.'.ics"',
        ]);
    }
}
