<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentCategoryResource\Pages\EditDocumentCategory;
use App\Filament\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Resources\GalleryResource\Pages\EditGallery;
use App\Filament\Resources\VideoResource\Pages\EditVideo;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentsAndMediaTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function document(): Document
    {
        $category = DocumentCategory::create([
            'title' => 'Українська категорія документів', 'title_en' => 'English document category',
            'translation_published' => true,
        ]);

        return $category->documents()->create([
            'title' => 'Український документ', 'description' => 'Український опис документа',
            'title_en' => 'English document', 'description_en' => 'Distinctive English description',
            'translation_published' => true, 'is_published' => true,
            'file_path' => 'documents/shared.pdf', 'external_url' => 'https://example.test/original.pdf',
        ]);
    }

    private function gallery(): Gallery
    {
        $gallery = Gallery::create([
            'title' => 'Український альбом', 'description' => 'Український опис альбому',
            'title_en' => 'English album', 'description_en' => 'English album description',
            'slug' => 'translation-album', 'is_published' => true, 'is_archive' => true,
            'translation_published' => true,
        ]);
        $gallery->photos()->create([
            'image' => 'gallery/missing.jpg', 'caption' => 'Український підпис',
            'caption_en' => 'English caption', 'translation_published' => true, 'sort_order' => 0,
        ]);
        $gallery->photos()->create(['image' => 'gallery/blank.jpg', 'caption' => null, 'sort_order' => 1]);

        return $gallery;
    }

    public function test_documents_translate_categories_cards_and_preserve_original_file_links(): void
    {
        $document = $this->document();
        $path = '/dokumenty/'.$document->category->slug;
        $this->get('/en/dokumenty')->assertOk()->assertSee('English document category')->assertDontSee('Українська категорія документів');
        $this->get('/en'.$path)->assertOk()->assertSee('English document')->assertSee('Distinctive English description')
            ->assertSee('https://example.test/original.pdf')->assertDontSee('Український документ');
        $this->get($path)->assertSee('Український документ')->assertDontSee('Distinctive English description');
        $document->category->update(['translation_published' => false]);
        $this->get('/en'.$path)->assertSee('Українська категорія документів')->assertSee('English document');
        $document->update(['external_url' => null]);
        $this->get('/en'.$path)->assertSee('/storage/documents/shared.pdf', false);
        $this->assertSame('documents/shared.pdf', $document->fresh()->file_path);
        Document::whereKey($document->id)->update(['description_en' => null]);
        $this->get('/en'.$path)->assertSee('Український документ')->assertSee('Український опис документа')->assertDontSee('English document</p>', false);
        $document->refresh()->update(['is_published' => false]);
        $this->get('/en'.$path)->assertDontSee('Український документ');
    }

    public function test_document_search_and_suggestions_follow_publication_completeness_and_locale(): void
    {
        $document = $this->document();
        $this->get('/en/poshuk?q=Distinctive')->assertOk()->assertSee('English document')->assertSee('https://example.test/original.pdf');
        $this->get('/en/poshuk/pidkazky?q=Distinctive')->assertOk()
            ->assertJsonFragment(['title' => 'English document', 'url' => 'https://example.test/original.pdf']);
        $this->get('/en/poshuk?q=Український%20документ')->assertDontSee('https://example.test/original.pdf');
        $this->get('/poshuk?q=Distinctive')->assertDontSee('https://example.test/original.pdf');
        $this->get('/poshuk?q=Український%20документ')->assertSee('Український документ');
        foreach ([['translation_published' => false], ['title_en' => null], ['description_en' => null]] as $change) {
            Document::whereKey($document->id)->update($change);
            $this->get('/en/poshuk?q=Distinctive')->assertDontSee('https://example.test/original.pdf');
            $this->get('/en/poshuk/pidkazky?q=Distinctive')->assertJsonMissing(['title' => 'English document']);
            $this->get('/en/poshuk?q=Український%20документ')->assertSee('Український опис документа');
            $this->get('/en/poshuk/pidkazky?q=Український%20документ')->assertJsonFragment(['title' => 'Український документ']);
            Document::whereKey($document->id)->update(['title_en' => 'English document', 'description_en' => 'Distinctive English description', 'translation_published' => true]);
        }
        $document->update(['is_published' => false]);
        $this->get('/en/poshuk?q=Distinctive')->assertDontSee('https://example.test/original.pdf');
        $this->get('/en/poshuk/pidkazky?q=Distinctive')->assertJsonMissing(['title' => 'English document']);
    }

    public function test_video_titles_and_alt_translate_on_home_and_catalogue_without_changing_youtube(): void
    {
        $video = Video::create([
            'title' => 'Українське відео', 'description' => 'Український опис відео',
            'title_en' => 'English video', 'description_en' => 'English video description',
            'youtube_id' => 'testVideoId', 'is_published' => true, 'translation_published' => true,
        ]);
        foreach (['/en', '/en/video'] as $path) {
            $this->get($path)->assertOk()->assertSee('alt="English video"', false)
                ->assertSee('https://www.youtube.com/watch?v=testVideoId')->assertDontSee('Українське відео');
        }
        $this->get('/video')->assertSee('Українське відео')->assertDontSee('English video');
        $hash = $video->translation_source_hash;
        $video->update(['youtube_id' => 'otherVideoId', 'sort_order' => 2]);
        $this->assertSame($hash, $video->translation_source_hash);
        $this->assertFalse($video->translationIsStale());
        Video::whereKey($video->id)->update(['description_en' => null]);
        $this->get('/en/video')->assertSee('alt="Українське відео"', false)->assertDontSee('English video');
        $video->refresh()->update(['is_published' => false]);
        $this->get('/en')->assertDontSee('otherVideoId');
        $this->get('/en/video')->assertDontSee('otherVideoId');
    }

    public function test_album_translates_headers_alt_and_lightbox_preserving_images_and_archive(): void
    {
        $gallery = $this->gallery();
        $this->get('/en/halereya')->assertOk()->assertSee('alt="English album"', false)->assertDontSee('Український альбом');
        $this->get('/en/halereya/'.$gallery->slug)->assertOk()->assertSee('English album description')
            ->assertSee('alt="English caption"', false)->assertSee('alt="English album"', false)
            ->assertSee('English caption')->assertSee('photo-archive', false)->assertSee('/storage/gallery/missing.jpg', false)
            ->assertDontSee('Український підпис')->assertDontSee('Український альбом');
        $this->get('/halereya/'.$gallery->slug)->assertSee('Український підпис')->assertDontSee('English caption');
        $this->assertSame('Український альбом', $gallery->fresh()->title);
    }

    public function test_album_returns_wholly_to_original_for_incomplete_draft_or_new_caption(): void
    {
        $gallery = $this->gallery();
        $photo = $gallery->photos()->firstOrFail();
        foreach ([['translation_published' => false], ['caption_en' => null]] as $change) {
            Photo::whereKey($photo->id)->update($change);
            $this->get('/en/halereya')->assertSee('Український альбом')->assertDontSee('English album');
            $this->get('/en/halereya/'.$gallery->slug)->assertSee('Український опис альбому')->assertSee('Український підпис')
                ->assertDontSee('English caption')->assertDontSee('English album');
            Photo::whereKey($photo->id)->update(['caption_en' => 'English caption', 'translation_published' => true]);
        }
        $new = $gallery->photos()->create(['image' => 'gallery/new.jpg', 'caption' => 'Новий підпис']);
        $this->get('/en/halereya/'.$gallery->slug)->assertSee('Новий підпис')->assertDontSee('English album');
        $new->delete();
        $gallery->update(['translation_published' => false]);
        $this->get('/en/halereya/'.$gallery->slug)->assertSee('Український підпис')->assertDontSee('English caption');
        $gallery->update(['is_published' => false]);
        $this->get('/en/halereya/'.$gallery->slug)->assertNotFound();
        $this->get('/en/halereya')->assertDontSee('translation-album');
    }

    public function test_all_entities_validate_publication_and_track_only_translated_source_fields(): void
    {
        $document = $this->document();
        $gallery = $this->gallery();
        $video = Video::create(['title' => 'Відео', 'title_en' => 'Video', 'youtube_id' => 'testVideoId', 'translation_published' => true]);
        foreach ([$document->category, $document, $gallery, $video, $gallery->photos()->firstOrFail()] as $model) {
            $primary = $model instanceof Photo ? 'caption' : 'title';
            $hash = $model->translation_source_hash;
            $model->update(['sort_order' => 5]);
            $this->assertFalse($model->translationIsStale());
            $model->update([$primary => 'Змінений оригінал']);
            $this->assertSame($hash, $model->translation_source_hash);
            $this->assertTrue($model->translationIsStale());
            $model->update([$primary.'_en' => 'Updated English']);
            $this->assertFalse($model->translationIsStale());
            try {
                $model->update([$primary.'_en' => ' ']);
                $this->fail('Incomplete translation published');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translation_published', $exception->errors());
            }
        }
        $video->refresh()->update(['description' => 'Новий опис']);
        app()->setLocale('en');
        $this->assertSame('Змінений оригінал', $video->localized('title'));
        $this->assertTrue($video->translation_published);
        $this->assertTrue($video->translationIsStale());
        $this->expectException(ValidationException::class);
        $video->update(['title_en' => 'Updated incomplete video']);
    }

    public function test_album_without_photos_can_translate_and_missing_description_returns_whole_album_to_original(): void
    {
        $gallery = $this->gallery();
        $gallery->photos()->delete();
        $this->get('/en/halereya/'.$gallery->slug)->assertSee('English album description')->assertDontSee('Український альбом');
        Gallery::whereKey($gallery->id)->update(['description_en' => null]);
        $this->get('/en/halereya/'.$gallery->slug)->assertSee('Український опис альбому')->assertDontSee('English album');
    }

    public function test_captions_are_escaped_in_alt_and_serialized_safely_for_lightbox(): void
    {
        $gallery = $this->gallery();
        $caption = 'Photo "quote" </script><script>alert(1)</script>';
        $gallery->photos()->firstOrFail()->update(['caption_en' => $caption]);
        $response = $this->get('/en/halereya/'.$gallery->slug)->assertOk();
        $response->assertSee('alt="'.e($caption).'"', false)->assertDontSee('</script><script>alert(1)</script>', false);
        $response->assertSee(Js::from($gallery->photos->map(fn ($photo) => ['src' => $photo->url, 'caption' => $gallery->publicCaption($photo)])->values())->toHtml(), false);
    }

    public function test_filament_saves_categories_documents_video_and_nested_photo_translations(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/missing.jpg', 'fixture');
        Storage::disk('public')->put('gallery/blank.jpg', 'fixture');
        $this->actingAs(User::firstOrFail());
        $document = $this->document();
        Livewire::test(EditDocumentCategory::class, ['record' => $document->category->getRouteKey()])
            ->fillForm(['title_en' => 'Admin category'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditDocument::class, ['record' => $document->id])
            ->fillForm(['description_en' => ''])->call('save')->assertHasFormErrors(['description_en' => 'required']);
        Livewire::test(EditDocument::class, ['record' => $document->id])
            ->fillForm(['description_en' => 'Admin description'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin description', $document->fresh()->description_en);
        $video = Video::create(['title' => 'Відео', 'youtube_id' => 'testVideoId']);
        Livewire::test(EditVideo::class, ['record' => $video->id])
            ->fillForm(['title_en' => 'Admin video', 'translation_published' => true])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($video->fresh()->translation_published);
        $gallery = $this->gallery();
        $component = Livewire::test(EditGallery::class, ['record' => $gallery->getRouteKey()]);
        $photos = $component->get('data.photos');
        $key = array_key_first($photos);
        $photos[$key]['caption_en'] = 'Admin caption';
        $component->fillForm(['title_en' => 'Admin album', 'photos' => $photos])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin caption', $gallery->photos()->firstOrFail()->caption_en);
        $this->assertSame('Admin album', $gallery->fresh()->title_en);
        $photos = $component->get('data.photos');
        $photos[$key]['caption_en'] = '';
        $component->fillForm(['photos' => $photos])->call('save')->assertHasFormErrors(['photos.'.$key.'.caption_en' => 'required']);
        $this->assertSame('Admin caption', $gallery->photos()->firstOrFail()->caption_en);
    }
}
