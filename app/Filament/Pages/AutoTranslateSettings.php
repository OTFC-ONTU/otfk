<?php

namespace App\Filament\Pages;

use App\Filament\Support\SettingsFormPage;
use App\Models\TranslationJob;
use App\Services\AutoTranslation\AutoTranslator;
use App\Services\AutoTranslation\Pricing;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Автопереклад на англійську через Claude API (App\Services\AutoTranslation\AutoTranslator):
 * ключ API (зберігається зашифрованим, у форму не повертається), вибір моделі (список із
 * Models API, кеш у translation_models на тиждень — оновлюється при відкритті сторінки),
 * що перекладати, місячний ліміт і дашборд токенів/приблизної вартості за місяць.
 */
class AutoTranslateSettings extends SettingsFormPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-language';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Автопереклад';

    protected static ?string $title = 'Автопереклад (Claude API)';

    protected static ?string $slug = 'settings-auto-translate';

    protected static string $settingsGroup = 'auto_translate';

    protected static function keys(): array
    {
        return [
            'auto_translate_enabled' => 'text',
            'auto_translate_model' => 'text',
            'auto_translate_publish' => 'text',
            'auto_translate_overwrite' => 'text',
            'auto_translate_types' => 'text',
            'auto_translate_budget' => 'text',
            'auto_translate_instructions' => 'textarea',
        ];
    }

    public function mount(): void
    {
        parent::mount();

        // Список моделей оновлюється не частіше разу на тиждень і лише коли сторінку відкривають.
        if (AutoTranslator::apiKey() !== null && AutoTranslator::modelsAreStale()) {
            $error = AutoTranslator::refreshModels();
            if ($error !== null) {
                Notification::make()->title('Не вдалося оновити список моделей')->body($error)->warning()->send();
            }
        }
    }

    protected function fromSettings(array $state): array
    {
        $state['auto_translate_enabled'] = ($state['auto_translate_enabled'] ?? '') === '1';
        $state['auto_translate_publish'] = ($state['auto_translate_publish'] ?? '1') !== '0';
        $state['auto_translate_overwrite'] = ($state['auto_translate_overwrite'] ?? '1') !== '0';
        $state['auto_translate_model'] = $state['auto_translate_model'] ?: config('auto_translate.default_model');
        $state['auto_translate_types'] = AutoTranslator::enabledTypes();
        $state['api_key'] = null;

        return $state;
    }

    protected function toSettings(array $state): array
    {
        foreach (['auto_translate_enabled', 'auto_translate_publish', 'auto_translate_overwrite'] as $flag) {
            $state[$flag] = ($state[$flag] ?? false) ? '1' : '0';
        }
        $state['auto_translate_types'] = implode(',', array_intersect(array_keys(AutoTranslator::TYPES), (array) ($state['auto_translate_types'] ?? [])));
        $state['auto_translate_budget'] = filled($state['auto_translate_budget'] ?? null) ? (string) (float) $state['auto_translate_budget'] : '';

        return $state;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Підключення до Claude API')
                    ->description('Особистий ключ API Anthropic (не адміністративний): токени й вартість рахуються на сайті за відповідями API.')
                    ->schema([
                        Text::make(fn () => match (true) {
                            AutoTranslator::hasUnreadableKey() => 'Збережений ключ не вдається розшифрувати (змінився APP_KEY) — введіть ключ знову.',
                            AutoTranslator::apiKey() !== null => 'Ключ збережено: '.AutoTranslator::maskedApiKey(),
                            default => 'Ключ ще не збережено.',
                        }),
                        TextInput::make('api_key')->label('Новий ключ API')->password()->revealable()
                            ->autocomplete('off')->minLength(20)->maxLength(300)->regex('/^\S+$/')
                            ->helperText('Ключ із console.anthropic.com → API Keys (вигляд: sk-ant-…). У базі зберігається зашифрованим і більше не показується. Порожнє поле — збережений ключ не змінюється.'),
                        Select::make('auto_translate_model')->label('Модель')->required()
                            ->options(fn () => AutoTranslator::modelOptions())
                            ->helperText(function (): string {
                                $at = AutoTranslator::modelsFetchedAt();

                                return 'Ціна — USD за 1 млн токенів (вхід / вихід). Sonnet (типово) — якісний переклад, удвічі дешевший за Opus; Haiku — найдешевший. '
                                    .($at
                                        ? 'Список моделей отримано з API '.$at->timezone('Europe/Kyiv')->format('d.m.Y H:i').'; оновлюється раз на тиждень при відкритті сторінки.'
                                        : 'Після збереження ключа список завантажиться з API.');
                            }),
                    ]),
                Section::make('Що й як перекладати')
                    ->schema([
                        Toggle::make('auto_translate_enabled')->label('Перекладати автоматично')
                            ->helperText('Після збереження матеріалу без актуального англійського перекладу він перекладається за кілька хвилин (одразу після збереження або планувальником щохвилини).'),
                        Toggle::make('auto_translate_publish')->label('Одразу публікувати переклад на /en')
                            ->helperText('Вимкнено — переклад зберігається чернеткою (вже опублікований переклад лишається опублікованим).'),
                        Toggle::make('auto_translate_overwrite')->label('Оновлювати переклад після зміни оригіналу')
                            ->helperText('Вимкнено — заповнюються лише порожні англійські поля, наявні (зокрема ручні) переклади не перезаписуються.'),
                        CheckboxList::make('auto_translate_types')->label('Типи матеріалів')
                            ->options(collect(AutoTranslator::TYPES)->map(fn (array $type) => $type[1])->all())
                            ->columns(2)->bulkToggleable(),
                        TextInput::make('auto_translate_budget')->label('Місячний ліміт, $')->numeric()->minValue(0)
                            ->helperText('Після досягнення приблизної вартості за поточний місяць черга зупиняється до наступного місяця. Порожнє — без ліміту.'),
                        Textarea::make('auto_translate_instructions')->label('Додаткові інструкції для перекладача')->rows(4)->maxLength(4000)
                            ->helperText('Англійською: глосарій і стиль, напр. «Translate “циклова комісія” as “subject commission”». Додається до системних інструкцій.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.auto-translate-dashboard')->viewData(fn () => ['stats' => $this->dashboard()]),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make($this->getFormActions())->key('form-actions')]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $newKey = trim((string) ($this->form->getState()['api_key'] ?? ''));
        parent::save();

        if ($newKey !== '') {
            AutoTranslator::storeApiKey($newKey);
            $this->data['api_key'] = null;
            $error = AutoTranslator::refreshModels($newKey);
            $error === null
                ? Notification::make()->title('Ключ API збережено')->body('Ключ працює, список моделей оновлено.')->success()->send()
                : Notification::make()->title('Ключ збережено, але API не відповіло')->body($error)->warning()->send();
        }

        if (($this->data['auto_translate_enabled'] ?? false) && AutoTranslator::apiKey() === null) {
            Notification::make()->title('Автопереклад не працюватиме без ключа API')->warning()->send();
        }
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('enqueueMissing')->label('Перекласти все неперекладене')->icon('heroicon-o-queue-list')
                ->requiresConfirmation()
                ->modalDescription('Усі матеріали вибраних типів без актуального англійського перекладу буде поставлено в чергу. Для сотень сторінок це помітні витрати — перевірте модель і ліміт.')
                ->visible(fn () => AutoTranslator::enabled())
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $count = AutoTranslator::enqueueMissing();
                    Notification::make()->title('Додано в чергу: '.$count)
                        ->body('Черга обробляється планувальником щохвилини або кнопкою «Обробити чергу зараз».')->success()->send();
                }),
            Action::make('processNow')->label('Обробити чергу зараз')->icon('heroicon-o-play')->color('gray')
                ->visible(fn () => AutoTranslator::enabled())
                ->action(function (AutoTranslator $translator): void {
                    abort_unless(static::canAccess(), 403);
                    @set_time_limit(300);
                    $result = $translator->processQueue((int) config('auto_translate.batch_limit', 5), 60);
                    $stopped = [
                        'budget' => 'Досягнуто місячного ліміту.',
                        'auth' => 'Ключ API не прийнято.',
                        'model' => 'Вибрана модель недоступна.',
                        'retry' => 'Тимчасова помилка API — повтор пізніше.',
                    ][$result['stopped']] ?? '';
                    Notification::make()
                        ->title(sprintf('Перекладено: %d, помилок: %d, пропущено: %d', $result['done'], $result['failed'], $result['skipped']))
                        ->body($stopped)
                        ->{$result['failed'] || $result['stopped'] ? 'warning' : 'success'}()->send();
                }),
            Action::make('retryFailed')->label('Повторити невдалі')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn () => TranslationJob::query()->where('status', TranslationJob::FAILED)->exists())
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    Notification::make()->title('Повернуто в чергу: '.AutoTranslator::retryFailed())->success()->send();
                }),
            Action::make('refreshModels')->label('Перевірити ключ')->icon('heroicon-o-key')->color('gray')
                ->visible(fn () => AutoTranslator::apiKey() !== null)
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $error = AutoTranslator::refreshModels();
                    $error === null
                        ? Notification::make()->title('Ключ працює')->body('Список моделей оновлено.')->success()->send()
                        : Notification::make()->title('Помилка API')->body($error)->danger()->send();
                }),
        ];
    }

    /** Дані дашборда: токени й приблизна вартість за поточний і попередній місяць, черга, останні запити. */
    public function dashboard(): array
    {
        $month = AutoTranslator::monthStart();
        $previous = AutoTranslator::monthStart(1);
        $used = fn () => TranslationJob::query()->where('input_tokens', '>', 0);

        $totals = $used()->where('updated_at', '>=', $month)
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(input_tokens), 0) as input, COALESCE(SUM(output_tokens), 0) as output, COALESCE(SUM(cache_read_tokens + cache_write_tokens), 0) as cache, COALESCE(SUM(cost_usd), 0) as cost')
            ->first();

        $byModel = $used()->where('updated_at', '>=', $month)
            ->select('model', DB::raw('COUNT(*) as requests'), DB::raw('SUM(input_tokens) as input'), DB::raw('SUM(output_tokens) as output'), DB::raw('SUM(cost_usd) as cost'))
            ->groupBy('model')->orderByDesc('cost')->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'input' => (int) $row->input,
                'output' => (int) $row->output,
                'cost' => Pricing::rates($row->model) ? (float) $row->cost : null,
            ])->all();

        $status = TranslationJob::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        return [
            'enabled' => AutoTranslator::enabled(),
            'has_key' => AutoTranslator::apiKey() !== null,
            'model' => AutoTranslator::model(),
            'model_price' => Pricing::label(AutoTranslator::model()),
            'month_label' => $month->timezone('Europe/Kyiv')->locale('uk')->translatedFormat('F Y'),
            'requests' => (int) ($totals->requests ?? 0),
            'input' => (int) ($totals->input ?? 0),
            'output' => (int) ($totals->output ?? 0),
            'cache' => (int) ($totals->cache ?? 0),
            'cost' => (float) ($totals->cost ?? 0),
            'previous_cost' => (float) $used()->where('updated_at', '>=', $previous)->where('updated_at', '<', $month)->sum('cost_usd'),
            'budget' => AutoTranslator::monthlyBudget(),
            'budget_exceeded' => AutoTranslator::budgetExceeded(),
            'by_model' => $byModel,
            'pending' => (int) ($status[TranslationJob::PENDING] ?? 0) + (int) ($status[TranslationJob::PROCESSING] ?? 0),
            'failed' => (int) ($status[TranslationJob::FAILED] ?? 0),
            'done' => (int) ($status[TranslationJob::DONE] ?? 0),
            'recent' => TranslationJob::query()->latest('updated_at')->latest('id')->limit(15)->get(),
        ];
    }
}
