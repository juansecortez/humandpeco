<?php

namespace App\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class UserPolicy
{
    use HandlesAuthorization;

    private function userIsAdmin(Authenticatable $user): bool
    {
        return method_exists($user, 'isAdmin') ? (bool) $user->isAdmin() : false;
    }

    private function userIsCreator(Authenticatable $user): bool
    {
        return method_exists($user, 'isCreator') ? (bool) $user->isCreator() : false;
    }

    private function userId($user)
    {
        return $user->id ?? null;
    }

    /** Puede ver listado de usuarios */
    public function viewAny(Authenticatable $user)
    {
        return $this->userIsAdmin($user);
    }

    /** Puede crear usuarios */
    public function create(Authenticatable $user)
    {
        return $this->userIsAdmin($user);
    }

    /** Puede actualizar un usuario local */
    public function update(Authenticatable $user, User $model)
    {
        if (config('app.is_demo')) {
            $uid = $this->userId($user);
            return ($this->userIsAdmin($user) || $model->id == $uid)
                && ($model->id !== 1 && $model->id !== 2 && $model->id !== 3);
        }

        return $this->userIsAdmin($user);
    }

    /** Puede eliminar un usuario local */
    public function delete(Authenticatable $user, User $model)
    {
        $uid = $this->userId($user);

        if (config('app.is_demo')) {
            return $this->userIsAdmin($user)
                && $uid != $model->id
                && $model->id !== 1 && $model->id !== 2 && $model->id !== 3;
        }

        return $this->userIsAdmin($user) && $uid != $model->id;
    }

    /** Puede administrar usuarios */
    public function manageUsers(Authenticatable $user)
    {
        return $this->userIsAdmin($user);
    }

    /** Puede administrar items/categorías/tags */
    public function manageItems(Authenticatable $user)
    {
        return $this->userIsAdmin($user) || $this->userIsCreator($user);
    }
}
