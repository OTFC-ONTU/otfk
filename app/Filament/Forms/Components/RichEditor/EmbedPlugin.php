<?php

namespace App\Filament\Forms\Components\RichEditor;

use App\Support\EmbedSource;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;

/**
 * Кнопка «PDF / відео» редактора: вставляє <iframe> з PDF (посилання або завантажений файл),
 * відео YouTube, документом Google Drive/Docs або картою Google. Адреса нормалізується
 * EmbedSource; на сайті PDF показується карткою файлу (FileCards), відео — плеєром.
 */
class EmbedPlugin implements RichContentPlugin
{
    public const NAME = 'embed';

    public const UNSUPPORTED = 'Це посилання не можна вбудувати. Підходять YouTube, Google Drive/Docs, карта Google (адреса вбудовування) або PDF на цьому сайті — інші PDF завантажте файлом.';

    /** Атрибути, з якими імпорт старого сайту вже вбудовував iframe — той самий вигляд на сайті. */
    private const DEFAULT_ATTRIBUTES = ['class' => 'w-full', 'style' => 'min-height:24rem;border:0', 'loading' => 'lazy'];

    private const VIDEO_ATTRIBUTES = [
        'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
        'allowfullscreen' => 'allowfullscreen',
        'referrerpolicy' => 'strict-origin-when-cross-origin',
    ];

    public static function make(): static
    {
        return new static;
    }

    public function getTipTapPhpExtensions(): array
    {
        return [new EmbedExtension];
    }

    public function getTipTapJsExtensions(): array
    {
        $file = public_path('js/admin/rich-editor-embed.js');

        return [asset('js/admin/rich-editor-embed.js').'?v='.(File::exists($file) ? File::lastModified($file) : 0)];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make(self::NAME)
                ->label('PDF / відео')
                ->icon(Heroicon::OutlinedFilm)
                ->action(arguments: '$getEditor().isActive(\'embed\') ? { src: $getEditor().getAttributes(\'embed\').src, title: $getEditor().getAttributes(\'embed\').title } : {}')
                ->activeKey('embed'),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make(self::NAME)
                ->label('PDF / відео')
                ->modalHeading('Вбудувати PDF або відео')
                ->modalDescription('Посилання на YouTube, документ Google Drive/Docs, карту Google (адреса «Вбудувати карту») або PDF — або завантажте PDF. На сайті PDF показується карткою файлу з переглядом, відео — плеєром.')
                ->modalSubmitActionLabel('Вставити')
                ->modalWidth(Width::Large)
                ->fillForm(fn (array $arguments): array => ['url' => $arguments['src'] ?? null, 'title' => $arguments['title'] ?? null])
                ->schema([
                    TextInput::make('url')
                        ->label('Посилання')
                        ->placeholder('https://www.youtube.com/watch?v=… або https://drive.google.com/file/d/…')
                        ->maxLength(2048)
                        ->requiredWithout('file')
                        ->live(onBlur: true)
                        ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (filled($value) && EmbedSource::normalize((string) $value) === null) {
                                $fail(self::UNSUPPORTED);
                            }
                        })
                        ->hidden(fn (Get $get): bool => filled($get('file'))),
                    FileUpload::make('file')
                        ->label('або PDF-файл')
                        ->disk('public')
                        ->directory('documents/vbudovani')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(20480)
                        ->preserveFilenames()
                        ->live()
                        ->hidden(fn (Get $get): bool => filled($get('url'))),
                    TextInput::make('title')
                        ->label('Назва')
                        ->helperText('Підпис картки PDF і назва для екранних читачів. Порожня — береться назва файлу або попередній заголовок.')
                        ->maxLength(255),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $source = filled($data['file'] ?? null)
                        ? ['src' => '/storage/'.ltrim((string) $data['file'], '/'), 'kind' => EmbedSource::KIND_PDF]
                        : EmbedSource::normalize($data['url'] ?? null);

                    if ($source === null) {
                        return;
                    }

                    $component->runCommands(
                        [EditorCommand::make('insertContent', arguments: [[
                            'type' => 'embed',
                            'attrs' => self::attributes($source, $data['title'] ?? null),
                        ]])],
                        editorSelection: $arguments['editorSelection'] ?? null,
                    );
                }),
        ];
    }

    /** @param array{src: string, kind: string} $source */
    public static function attributes(array $source, ?string $title = null): array
    {
        return array_filter([
            'src' => $source['src'],
            'title' => filled($title) ? trim($title) : null,
            ...self::DEFAULT_ATTRIBUTES,
            ...($source['kind'] === EmbedSource::KIND_VIDEO ? self::VIDEO_ATTRIBUTES : []),
        ], fn ($value) => $value !== null);
    }
}
