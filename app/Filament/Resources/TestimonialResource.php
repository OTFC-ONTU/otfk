<?php

namespace App\Filament\Resources;

use App\Filament\Forms\EnglishTranslation;
use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Відгуки';

    protected static ?string $modelLabel = 'відгук';

    protected static ?string $pluralModelLabel = 'Відгуки студентів';

    public static function form(Form $form): Form
    {
        return $form->schema([
            EnglishTranslation::section(contentFields: ['role' => ['label' => 'Хто це англійською'], 'quote' => ['label' => 'Англійський відгук', 'rows' => 4]], primaryField: 'name', primaryLabel: 'Ім’я англійською'),
            Forms\Components\FileUpload::make('photo')->label('Фото')->image()->avatar()->directory('testimonials')
                ->imageEditor()->imageResizeMode('contain')->imageResizeTargetWidth('600')->imageResizeTargetHeight('600')
                ->helperText('Необовʼязково — без фото покажуться ініціали.'),
            Forms\Components\TextInput::make('name')->label('Імʼя та прізвище')->required()->maxLength(255),
            Forms\Components\TextInput::make('role')->label('Хто це')->maxLength(255)
                ->placeholder('Випускник 2024, спеціальність «Компʼютерна інженерія»'),
            Forms\Components\Textarea::make('quote')->label('Відгук')->rows(4)->required()->maxLength(600)->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')->label('Порядок')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Показувати')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('translation_status')->label('Переклад EN')
                    ->state(fn (Testimonial $record) => $record->translationStatus())->badge(),
                Tables\Columns\ImageColumn::make('photo')->label('')->circular(),
                Tables\Columns\TextColumn::make('name')->label('Імʼя')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('role')->label('Хто це')->limit(40)->color('gray'),
                Tables\Columns\TextColumn::make('quote')->label('Відгук')->limit(50)->color('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('Активний')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
