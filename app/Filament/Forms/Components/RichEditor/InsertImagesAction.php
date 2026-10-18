<?php

namespace App\Filament\Forms\Components\RichEditor;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Кнопка «Вставити фото» редактора — заміна AttachFilesAction Filament (та сама назва дії
 * attachFiles, її викликає кнопка панелі). Стандартна дія приймає лише один файл: FilePond
 * без allowMultiple мовчки відкидає перетягування кількох фото («Max files»). Тут нове
 * вставлення приймає кілька фото (кожне — окремим абзацом у порядку списку), заміна
 * виділеного фото — як і раніше, одним файлом. PDF і документи вставляються кнопкою
 * «PDF-файл / відео» (EmbedPlugin) — на сайті це картка файлу.
 */
class InsertImagesAction
{
    public const NAME = 'attachFiles';

    public const LABEL = 'Вставити фото';

    public const HINT = 'Лише зображення (JPG, PNG, GIF, WEBP); можна кілька одразу — перетягніть або виберіть. PDF і документи вставляйте кнопкою «PDF-файл / відео»: на сайті вони стануть карткою файлу з переглядом.';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(self::LABEL)
            ->modalHeading(fn (array $arguments): string => filled($arguments['src'] ?? null) ? 'Замінити фото' : self::LABEL)
            ->modalDescription(self::HINT)
            ->modalSubmitActionLabel(fn (array $arguments): string => filled($arguments['src'] ?? null) ? 'Зберегти' : 'Вставити')
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'alt' => $arguments['alt'] ?? null,
            ])
            ->schema(function (array $arguments, RichEditor $component): array {
                $replacing = filled($arguments['src'] ?? null);

                return [
                    FileUpload::make('file')
                        ->label($replacing ? 'Інше фото (необов\'язково)' : 'Фото')
                        ->hiddenLabel(! $replacing)
                        ->multiple(! $replacing)
                        ->appendFiles()
                        ->reorderable(! $replacing)
                        ->maxFiles($replacing ? null : 30)
                        ->acceptedFileTypes($component->getFileAttachmentsAcceptedFileTypes())
                        ->maxSize($component->getFileAttachmentsMaxSize())
                        ->storeFiles(false)
                        ->required(! $replacing)
                        ->validationMessages(['mimetypes' => 'Тут лише фото. PDF і документи — кнопкою «PDF-файл / відео».']),
                    TextInput::make('alt')
                        ->label($replacing ? 'Опис фото (alt)' : 'Опис фото (alt) — для всіх вибраних')
                        ->helperText('Коротко, що на фото: для незрячих читачів і пошуку.')
                        ->maxLength(1000),
                ];
            })
            ->action(function (array $arguments, array $data, RichEditor $component, Component $livewire): void {
                $alt = filled($data['alt'] ?? null) ? $data['alt'] : null;
                $files = array_values(array_filter(is_array($data['file'] ?? null) ? $data['file'] : [$data['file'] ?? null]));

                $images = array_map(function ($file) use ($component, $livewire, $alt): array {
                    $id = (string) Str::orderedUuid();
                    data_set($livewire, "componentFileAttachments.{$component->getStatePath()}.{$id}", $file);

                    return ['type' => 'image', 'attrs' => ['alt' => $alt, 'id' => $id, 'src' => $component->getUploadedFileAttachmentTemporaryUrl($file)]];
                }, $files);

                if (filled($arguments['src'] ?? null)) {
                    // Як у Filament: виділення інколи приходить як текст, а не вузол — тоді оновлення не спрацює
                    if (($arguments['editorSelection']['type'] ?? null) !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor']--;
                        unset($arguments['editorSelection']['head']);
                    }

                    $component->runCommands(
                        [EditorCommand::make('updateAttributes', arguments: ['image', [
                            'alt' => $alt,
                            'id' => $images[0]['attrs']['id'] ?? $arguments['id'] ?? null,
                            'src' => $images[0]['attrs']['src'] ?? $arguments['src'],
                        ]])],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                if ($images === []) {
                    return;
                }

                // Одне фото — у місце курсора (як раніше); кілька — кожне окремим абзацом, щоб не злипалися в рядок
                $content = count($images) === 1
                    ? $images[0]
                    : array_map(fn (array $image): array => ['type' => 'paragraph', 'content' => [$image]], $images);

                $component->runCommands(
                    [EditorCommand::make('insertContent', arguments: [$content])],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
