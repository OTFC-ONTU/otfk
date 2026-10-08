<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Closure;
use App\Filament\Support\ViewOnSite;
use App\Models\BellPeriod;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;

/**
 * Розклад дзвінків одним екраном: кількість пар у зміні фіксована
 * (по 4 у кожній), тож пари не додаються і не видаляються — редагується
 * лише час початку та кінця, а також два перемикачі:
 * показувати другу зміну і показувати плашку «зараз іде пара» у шапці.
 */
class BellSchedule extends FilamentPage implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string | \UnitEnum | null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Розклад дзвінків';

    protected static ?string $title = 'Розклад дзвінків';


    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $periods = BellPeriod::query()->get()->keyBy(fn (BellPeriod $p) => $p->shift.':'.$p->number);

        $state = [
            'second_shift' => BellPeriod::secondShiftEnabled(),
            'now_chip' => BellPeriod::chipEnabled(),
        ];

        foreach (BellPeriod::SHIFTS as $shift) {
            for ($n = 1; $n <= BellPeriod::PAIRS_PER_SHIFT; $n++) {
                $p = $periods->get($shift.':'.$n);
                $state[static::field($shift, $n, 'starts')] = $p ? substr((string) $p->starts, 0, 5) : null;
                $state[static::field($shift, $n, 'ends')] = $p ? substr((string) $p->ends, 0, 5) : null;
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Що показувати на сайті')
                    ->description('Пари лишаються в базі — перемикачі керують лише тим, що бачить відвідувач.')
                    ->schema([
                        Toggle::make('second_shift')
                            ->label('Показувати другу зміну')
                            ->helperText('Вимкнено — на сайті видно лише розклад першої зміни.'),
                        Toggle::make('now_chip')
                            ->label('Показувати у верхньому меню, яка пара йде зараз')
                            ->helperText('Плашка «1-ша пара · до кінця N хв» у верхній смузі сайту.'),
                    ]),
                $this->shiftSection(1, '1 зміна', 'Чотири пари. Перерва — це проміжок між кінцем однієї пари та початком наступної.'),
                $this->shiftSection(2, '2 зміна', 'Чотири пари. Зміни можуть накладатися — це нормально.'),
            ])
            ->statePath('data');
    }

    /** Секція однієї зміни: фіксовані чотири пари, у кожної лише початок і кінець. */
    protected function shiftSection(int $shift, string $heading, string $description): Section
    {
        $rows = [];

        for ($n = 1; $n <= BellPeriod::PAIRS_PER_SHIFT; $n++) {
            $rows[] = Grid::make(2)->schema([
                TimePicker::make(static::field($shift, $n, 'starts'))
                    ->label($n.' пара — початок')
                    ->seconds(false)
                    ->required(),
                TimePicker::make(static::field($shift, $n, 'ends'))
                    ->label($n.' пара — кінець')
                    ->seconds(false)
                    ->required()
                    ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get, $shift, $n) {
                        $starts = $get(static::field($shift, $n, 'starts'));

                        if ($starts && $value && strtotime((string) $value) <= strtotime((string) $starts)) {
                            $fail('Кінець '.$n.'-ї пари має бути пізніше за її початок.');
                        }
                    }),
            ]);
        }

        return Section::make($heading)->description($description)->schema($rows);
    }

    /** Ім'я поля форми для конкретної пари: `s1_3_starts`. */
    protected static function field(int $shift, int $number, string $suffix): string
    {
        return 's'.$shift.'_'.$number.'_'.$suffix;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            ViewOnSite::header(route('bells')),
        ];
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('save')->label('Зберегти')->submit('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (BellPeriod::SHIFTS as $shift) {
            for ($n = 1; $n <= BellPeriod::PAIRS_PER_SHIFT; $n++) {
                BellPeriod::updateOrCreate(
                    ['shift' => $shift, 'number' => $n],
                    [
                        'starts' => $state[static::field($shift, $n, 'starts')],
                        'ends' => $state[static::field($shift, $n, 'ends')],
                        'is_active' => true,
                    ],
                );
            }
        }

        BellPeriod::setFlag(BellPeriod::SECOND_SHIFT_SETTING, (bool) $state['second_shift']);
        BellPeriod::setFlag(BellPeriod::NOW_CHIP_SETTING, (bool) $state['now_chip']);

        Notification::make()->title('Розклад дзвінків збережено')->success()->send();
    }

    /** Форма зі збереженням — стандартна розмітка сторінки Filament 4 (без власного view). */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make($this->getFormActions())->key('form-actions')]),
        ]);
    }
}
