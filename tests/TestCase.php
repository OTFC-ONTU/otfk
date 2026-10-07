<?php

namespace Tests;

use App\Models\User;
use App\Support\TwoFactor;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Pagination\Paginator;

abstract class TestCase extends BaseTestCase
{
    /**
     * Сидити БД при першій міграції тестового процесу (RefreshDatabase виконує
     * migrate:fresh --seed лише раз за процес — той клас, що бутиться першим,
     * визначає наявність сиду; тому вмикаємо глобально, щоб тести не залежали
     * від порядку запуску).
     */
    protected bool $seed = true;

    /**
     * public/build не комітиться (збирається лише в CI/деплої), тому Vite
     * у тестах вимкнено — інакше рендер будь-якої сторінки падає через
     * відсутній manifest.json.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Livewire::test() компонента з таблицею підміняє статичний вид пагінації
        // (SupportPagination) і не відновлює його, якщо монтування завершилось
        // 403 — наступний публічний тест втратив би посилання ?page=2.
        Paginator::useTailwind();
    }

    /**
     * Фікстура входу: адмінка вимагає підтверджений другий фактор, тож
     * користувач без нього отримує тестовий секрет, а сесія позначається як
     * така, що пройшла код. Самі перевірки фактора (TwoFactorTest) входять
     * через parent-метод і не отримують цієї позначки.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($user instanceof User && ! $user->hasTwoFactor()) {
            $user->forceFill([
                'two_factor_secret' => UserFactory::TEST_TOTP_SECRET,
                'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
                'two_factor_confirmed_at' => now(),
            ])->saveQuietly();
        }

        parent::actingAs($user, $guard);
        session([TwoFactor::SESSION_KEY => $user->getAuthIdentifier()]);

        return $this;
    }

    /** Вхід без позначки другого фактора — для тестів самого фактора. */
    public function actingAsWithoutTwoFactor(UserContract $user, $guard = null): static
    {
        parent::actingAs($user, $guard);
        session()->forget(TwoFactor::SESSION_KEY);

        return $this;
    }
}
