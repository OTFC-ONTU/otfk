<?php

namespace Tests;

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
}
