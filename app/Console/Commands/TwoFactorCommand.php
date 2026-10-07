<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Console\Command;

/**
 * Аварійне керування другим фактором із консолі (SSH на хостингу), коли
 * жоден адміністратор не може увійти: показати стан або скинути фактор
 * конкретного користувача — він підключить застосунок заново при вході.
 * Аварійний вимикач для всіх одразу — TWO_FACTOR_ENFORCE=false у .env.
 */
class TwoFactorCommand extends Command
{
    protected $signature = 'otfk:two-factor
        {email? : Електронна пошта користувача}
        {--reset : Скинути фактор цього користувача}
        {--status : Показати стан усіх користувачів}';

    protected $description = 'Стан або аварійний скид двофакторного захисту користувача адмінки';

    public function handle(): int
    {
        if ($this->option('status') || ! $this->argument('email')) {
            $this->table(['ID', 'Пошта', 'Роль', '2FA', 'Кодів відновлення', 'Останній вхід'],
                User::query()->orderBy('id')->get()->map(fn (User $u) => [
                    $u->id, $u->email, $u->role,
                    $u->hasTwoFactor() ? 'підключено' : 'ні',
                    count($u->two_factor_recovery_codes ?? []),
                    $u->last_login_at?->timezone('Europe/Kyiv')->format('d.m.Y H:i') ?? '—',
                ])->all());
            $this->line('Примусовість: '.(TwoFactor::enforced() ? 'увімкнено' : 'ВИМКНЕНО (TWO_FACTOR_ENFORCE=false)'));

            return self::SUCCESS;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Користувача не знайдено.');

            return self::FAILURE;
        }

        if (! $this->option('reset')) {
            $this->line("{$user->email}: ".($user->hasTwoFactor() ? 'підключено '.$user->two_factor_confirmed_at : 'не підключено'));

            return self::SUCCESS;
        }

        app(TwoFactor::class)->reset($user, 'console');
        $this->info("Фактор скинуто: {$user->email} підключить застосунок при наступному вході.");

        return self::SUCCESS;
    }
}
