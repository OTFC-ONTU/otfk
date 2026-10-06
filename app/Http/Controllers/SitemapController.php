<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Page;
use App\Models\Specialty;
use App\Models\Staff;

class SitemapController extends Controller
{
    public function index()
    {
        $entries = [];

        // Статичні розділи: пріоритет вручну, без точної дати зміни.
        $sections = [
            'news.index' => '0.9', 'specialties.index' => '0.9',
            'events' => '0.8', 'documents.index' => '0.6', 'structure.index' => '0.6',
            'galleries.index' => '0.6', 'contacts' => '0.6', 'faq' => '0.6',
            'staff.administration' => '0.5', 'video.index' => '0.5', 'bells' => '0.5',
        ];
        $entries[] = ['loc' => url('/'), 'lastmod' => null, 'changefreq' => 'daily', 'priority' => '1.0'];
        $entries[] = ['loc' => route('news.feed'), 'lastmod' => now()->toDateString(), 'changefreq' => 'daily', 'priority' => '0.5'];
        foreach ($sections as $name => $priority) {
            $entries[] = ['loc' => route($name), 'lastmod' => null, 'changefreq' => 'weekly', 'priority' => $priority];
        }

        // Динамічні сторінки: lastmod з updated_at (Google так розумніше планує обхід).
        foreach (News::published()->get() as $n) {
            $entries[] = ['loc' => route('news.show', $n), 'lastmod' => optional($n->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.7'];
        }
        foreach (Page::published()->get() as $p) {
            $entries[] = ['loc' => url('/' . $p->slug), 'lastmod' => optional($p->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.6'];
        }
        foreach (Specialty::published()->get() as $s) {
            $entries[] = ['loc' => route('specialties.show', $s), 'lastmod' => optional($s->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.7'];
        }
        foreach (Gallery::published()->get() as $g) {
            $entries[] = ['loc' => route('galleries.show', $g), 'lastmod' => optional($g->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.5'];
        }
        foreach (Staff::published()->whereNotNull('slug')->get() as $person) {
            $entries[] = ['loc' => route('staff.show', $person), 'lastmod' => optional($person->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.4'];
        }
        foreach (DocumentCategory::all() as $c) {
            $entries[] = ['loc' => route('documents.category', $c), 'lastmod' => optional($c->updated_at)->toDateString(), 'changefreq' => 'monthly', 'priority' => '0.5'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $seen = [];
        foreach ($entries as $e) {
            if (isset($seen[$e['loc']])) {
                continue;
            }
            $seen[$e['loc']] = true;

            $xml .= '  <url><loc>' . htmlspecialchars($e['loc']) . '</loc>';
            if ($e['lastmod']) {
                $xml .= '<lastmod>' . $e['lastmod'] . '</lastmod>';
            }
            $xml .= '<changefreq>' . $e['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $e['priority'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
