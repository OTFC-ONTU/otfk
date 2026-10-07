<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Спільна політика для сутностей, якими керує лише адміністратор:
 * облікові записи, сирі налаштування, структура меню. Редактор не має
 * жодної дії — ані перегляду списку, ані Livewire-викликів збереження.
 * Конкретні політики лише вказують модель (див. AppServiceProvider).
 */
abstract class AdminOnlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }
}
