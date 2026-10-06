<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\AccreditationContent;
use App\Support\FileCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_lists_and_embedded_pdf_use_the_shared_card_without_changing_inline_links(): void
    {
        $html = FileCards::render('<ol><li>1) <a href="/storage/report.PDF?version=2#page=3">Report</a></li></ol>'
            .'<h2>Strategy</h2><p><iframe src="/storage/strategy.pdf"></iframe></p>'
            .'<p>Read <a href="/storage/inline.pdf">this reference</a>.</p>'
            .'<iframe src="https://www.youtube.com/embed/test"></iframe>');

        $this->assertSame(2, substr_count($html, 'class="file-card not-prose"'));
        $this->assertSame(3, substr_count($html, 'href="/storage/report.PDF?version=2#page=3"'));
        $this->assertStringContainsString('>Strategy</a>', $html);
        $this->assertStringContainsString('<p>Read <a href="/storage/inline.pdf">this reference</a>.</p>', $html);
        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/test"></iframe>', $html);
        $this->assertStringNotContainsString('<iframe src="/storage/strategy.pdf"', $html);
    }

    public function test_comments_scripts_and_attribute_text_are_preserved(): void
    {
        $protected = '<!--<p><a href="/hidden.pdf">Hidden</a></p>-->'
            .'<script>const text = \'<p><a href="/script.pdf">Script</a></p>\';</script>';
        $this->assertSame($protected, FileCards::render($protected));
        $html = FileCards::render('<p><a data-href="/wrong.pdf" title="href=\'/also-wrong.pdf\'" href="/right.pdf">&lt;Report&gt;</a></p>');
        $this->assertSame(3, substr_count($html, 'href="/right.pdf"'));
        $this->assertStringNotContainsString('/wrong.pdf', $html);
        $this->assertStringContainsString('&lt;Report&gt;', $html);
    }

    public function test_legacy_accreditation_recovers_nested_sections_in_both_languages_without_writing_content(): void
    {
        $body = $this->legacyBody('Accreditation');
        $page = Page::updateOrCreate(['slug' => 'litsenzuvannya-ta-akredytatsiya'], [
            'slug' => 'litsenzuvannya-ta-akredytatsiya', 'title' => 'Ліцензування',
            'body' => $this->legacyBody('Акредитація'), 'is_published' => true,
            'title_en' => 'Licensing', 'body_en' => $body, 'translation_published' => true,
        ]);
        foreach (['/litsenzuvannya-ta-akredytatsiya', '/en/litsenzuvannya-ta-akredytatsiya'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(6, substr_count($html, '<details class="content-accordion">'));
            $this->assertSame(4, substr_count($html, 'class="file-card not-prose"'));
            $this->assertStringNotContainsString('<iframe', $html);
            $this->assertStringContainsString('https://registry.example.org', $html);
        }
        $this->assertSame($body, $page->fresh()->body_en);
        $this->assertSame($this->legacyBody('Акредитація'), $page->fresh()->body);
        $unrelated = str_replace('imported-from:', 'other-source:', $body);
        $this->assertSame($unrelated, AccreditationContent::restore($unrelated));
        $restored = AccreditationContent::restore($body);
        $this->assertSame($restored, AccreditationContent::restore($restored));
        $this->assertSame(6, substr_count(AccreditationContent::restore(str_replace('accreditation-->', 'accreditation/-->', $body)), '<details'));
    }

    private function legacyBody(string $title): string
    {
        return '<p>'.$title.'</p><p>College accreditation</p><p>Junior bachelor</p>'
            .'<p>Program one</p><p>Program one</p><ol><li><a href="/storage/one.pdf">Self evaluation</a></li></ol>'
            .'<p>Program two</p><p>Program two</p><ol><li><a href="/storage/two.pdf">Expert report</a></li></ol>'
            .'<p>Professional education</p><p>Professional education</p><p><iframe src="/storage/professional.pdf"></iframe></p>'
            .'<p>Higher education</p><p>Higher education</p><p><iframe src="/storage/higher.pdf"></iframe></p>'
            .'<p>Certificates</p><p>Certificates</p><p><a href="https://registry.example.org">Registry</a></p>'
            .'<!--imported-from:https://otfk.od.ua/licensing_and_accreditation-->';
    }
}
