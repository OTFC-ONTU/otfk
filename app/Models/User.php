<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Повний доступ: облікові записи, налаштування, структура меню і весь контент. */
    public const ROLE_ADMIN = 'admin';

    /** Лише контент: новини, сторінки, документи, персонал тощо. */
    public const ROLE_EDITOR = 'editor';

    /** @var array<string, string> роль => підпис в адмінці */
    public const ROLES = [
        self::ROLE_ADMIN => 'Адміністратор',
        self::ROLE_EDITOR => 'Редактор',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /** Нові записи без явної ролі — редактори (як і DB-default; потрібно для create()/tinker). */
    protected $attributes = [
        'role' => self::ROLE_EDITOR,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Другий фактор підключено й підтверджено першим кодом. */
    public function hasTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Запобіжники цілісності: не можна видалити себе чи останнього
     * адміністратора і не можна зняти роль з останнього адміністратора —
     * інакше панель лишиться без власника облікових записів.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! array_key_exists($user->role, self::ROLES)) {
                throw ValidationException::withMessages(['role' => 'Невідома роль користувача.']);
            }

            if ($user->exists && $user->isDirty('role') && $user->getOriginal('role') === self::ROLE_ADMIN
                && ! self::query()->whereKeyNot($user->getKey())->where('role', self::ROLE_ADMIN)->exists()) {
                throw ValidationException::withMessages(['role' => 'Це останній адміністратор: спочатку призначте іншого.']);
            }
        });

        static::deleting(function (self $user): void {
            if (auth()->id() === $user->getKey()) {
                throw ValidationException::withMessages(['email' => 'Не можна видалити власний обліковий запис.']);
            }

            // Роль беремо збережену, а не змінену в пам'яті (напр. після невдалого update).
            if ($user->getOriginal('role') === self::ROLE_ADMIN
                && ! self::query()->whereKeyNot($user->getKey())->where('role', self::ROLE_ADMIN)->exists()) {
                throw ValidationException::withMessages(['email' => 'Не можна видалити останнього адміністратора.']);
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? (string) $this->role;
    }

    /**
     * Доступ до адмінпанелі Filament мають обидві ролі; що саме доступно
     * всередині — вирішують політики та canAccess() ресурсів і сторінок.
     * Метод потрібен явно, інакше у production Filament віддає 403.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return array_key_exists($this->role, self::ROLES);
    }
}
