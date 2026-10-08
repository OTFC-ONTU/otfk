<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page as FilamentPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * База для «людських» сторінок налаштувань: замість сирого key-value списку
 * адміністратор бачить звичайну форму з підписами й підказками.
 *
 * Нащадок оголошує keys() — ключі таблиці settings і їхній тип (text,
 * textarea, url, image). Для перекладних ключів (Setting::TRANSLATABLE_KEYS)
 * форма має поле `<key>_en`: заповнений переклад публікується, очищений —
 * знімається з публікації; зміна лише оригіналу не чіпає publication/hash
 * (як і в «Розширених налаштуваннях»). Наявні group/type не перетираються,
 * нові ключі створюються з групою сторінки. Кеші settings.* скидає
 * Setting::booted().
 */
abstract class SettingsFormPage extends FilamentPage implements HasForms
{
    use InteractsWithForms;

    protected static string | \UnitEnum | null $navigationGroup = 'Налаштування';


    /** Група в таблиці settings для новостворених ключів. */
    protected static string $settingsGroup = 'general';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Сторінки налаштувань — лише адміністратору; редактор не бачить групу і отримує 403. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** @return array<string, string> ключ settings => тип для нового запису */
    abstract protected static function keys(): array;

    /** Ключі сторінки, що мають англійський варіант. */
    protected static function translatableKeys(): array
    {
        return array_values(array_filter(
            array_keys(static::keys()),
            fn (string $key) => Setting::supportsTranslation($key, static::keys()[$key]),
        ));
    }

    public function mount(): void
    {
        $records = Setting::query()->whereIn('key', array_keys(static::keys()))->get()->keyBy('key');

        $state = [];
        foreach (array_keys(static::keys()) as $key) {
            $state[$key] = $records->get($key)?->value;
        }
        foreach (static::translatableKeys() as $key) {
            $state[$key.'_en'] = $records->get($key)?->value_en;
        }

        $this->form->fill($this->fromSettings($state));
    }

    /** Перетворення «рядок з БД → стан форми» (напр., '1' → true для Toggle). */
    protected function fromSettings(array $state): array
    {
        return $state;
    }

    /** Зворотне перетворення «стан форми → рядок для БД». */
    protected function toSettings(array $state): array
    {
        return $state;
    }

    /** Поле англійського варіанта для перекладного ключа. */
    protected static function englishField(string $key, string $label, bool $multiline = false): Field
    {
        $field = $multiline
            ? Textarea::make($key.'_en')->rows(3)
            : TextInput::make($key.'_en');

        return $field->label($label.' (англійською)')
            ->helperText('Для англійської версії сайту (/en). Порожнє — використовується стандартний англійський підпис або український текст.');
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('save')->label('Зберегти')->submit('save')->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        // Повторна перевірка на Livewire-виклик: роль могла змінитися після відкриття сторінки.
        abort_unless(static::canAccess(), 403);

        $state = $this->toSettings($this->form->getState());
        $translatable = static::translatableKeys();

        DB::transaction(function () use ($state, $translatable): void {
            foreach (static::keys() as $key => $type) {
                $setting = Setting::firstOrNew(['key' => $key]);

                if (! $setting->exists) {
                    $setting->group = static::$settingsGroup;
                    $setting->type = $type;
                }

                $setting->value = (string) ($state[$key] ?? '');

                if (in_array($key, $translatable, true) && array_key_exists($key.'_en', $state)) {
                    $english = trim((string) $state[$key.'_en']);
                    $setting->value_en = $english !== '' ? $english : null;
                    if ($setting->isDirty('value_en')) {
                        $setting->translation_published = $english !== '';
                    }
                }

                $setting->save();
            }
        });

        Notification::make()->title('Налаштування збережено')->success()->send();
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
