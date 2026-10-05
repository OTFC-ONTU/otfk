<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class EnglishTranslation
{
    public static function section(bool $withSeo = false, ?array $contentFields = null, string $primaryField = 'title', string $primaryLabel = 'Англійський заголовок', bool $optionalPrimary = false, ?int $primaryRows = null): Section
    {
        $translationFields = array_map(fn ($field) => $field.'_en', array_merge(
            [$primaryField],
            $contentFields === null ? ['excerpt', 'body'] : array_keys($contentFields),
            $withSeo ? ['meta_title', 'meta_description'] : [],
        ));
        $requiresCompleteTranslation = fn (Get $get, ?Model $record) => self::requiresCompleteTranslation($get, $record, $translationFields);
        $primaryInput = $primaryRows
            ? Textarea::make($primaryField.'_en')->rows($primaryRows)
            : TextInput::make($primaryField.'_en')->maxLength(255);
        $fields = [
            Placeholder::make('translation_status')
                ->label('Стан перекладу')
                ->content(fn (?Model $record) => $record?->translationStatus() ?? 'Відсутній'),
            Toggle::make('translation_published')->label('Опублікувати англійський переклад')->default(false)->live()
                ->helperText('Без опублікованого перекладу показується український матеріал. Зміна оригіналу не знімає переклад з публікації.'),
            $primaryInput->label($primaryLabel)
                ->required(fn (Get $get, ?Model $record) => $requiresCompleteTranslation($get, $record) && (! $optionalPrimary || filled($get($primaryField))))->columnSpanFull(),
            Textarea::make('excerpt_en')->label('Англійський короткий опис')->rows(2)->columnSpanFull(),
            Textarea::make('body_en')->label('Англійський текст (HTML)')->rows(16)->columnSpanFull()
                ->required(fn (Get $get, ?Model $record) => $requiresCompleteTranslation($get, $record) && filled($get('body')))
                ->helperText('Вставте переклад HTML зі збереженими тегами, посиланнями та зображеннями. Збереження зміненого перекладу підтверджує його актуальність до поточного оригіналу.'),
        ];

        if ($contentFields !== null) {
            $fields = array_slice($fields, 0, 3);
            foreach ($contentFields as $source => $config) {
                $input = ($config['rows'] ?? null)
                    ? Textarea::make($source.'_en')->rows($config['rows'])
                    : TextInput::make($source.'_en')->maxLength(255);
                $fields[] = $input->label($config['label'])->columnSpanFull()
                    ->required(fn (Get $get, ?Model $record) => $requiresCompleteTranslation($get, $record) && filled($get($source)));
            }
        }

        if ($withSeo) {
            $fields[] = TextInput::make('meta_title_en')->label('Англійський SEO-заголовок')->maxLength(255);
            $fields[] = Textarea::make('meta_description_en')->label('Англійський SEO-опис')->rows(2)->maxLength(500);
        }

        return Section::make('Англійський переклад')->schema($fields)->columns(2)->collapsed()->columnSpanFull();
    }

    /** Зміна лише оригіналу дозволена: неповний переклад тимчасово повертає український матеріал. */
    private static function requiresCompleteTranslation(Get $get, ?Model $record, array $translationFields): bool
    {
        if (! $get('translation_published')) {
            return false;
        }
        if (! $record || ! $record->translation_published) {
            return true;
        }

        foreach ($translationFields as $field) {
            if ((string) $get($field) !== (string) $record->getAttribute($field)) {
                return true;
            }
        }

        return false;
    }

    public static function academicSection(bool $specialty = false, bool $html = true): Section
    {
        $fields = [];
        if ($specialty) {
            $fields['short_description'] = ['label' => 'Англійський короткий опис', 'rows' => 2];
        }
        $fields['description'] = ['label' => $html ? 'Англійський опис (HTML)' : 'Англійський опис', 'rows' => $html ? 16 : 2];
        if ($specialty) {
            $fields['degree'] = ['label' => 'Освітній ступінь англійською'];
            $fields['study_form'] = ['label' => 'Форма навчання англійською'];
            $fields['duration'] = ['label' => 'Термін навчання англійською'];
        }

        return self::section(contentFields: $fields);
    }
}
