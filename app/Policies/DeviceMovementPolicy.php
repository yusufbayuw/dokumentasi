<?php

namespace App\Policies;

use App\Models\DeviceMovement;
use App\Models\User;

class DeviceMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_device');
    }

    public function view(User $user, DeviceMovement $movement): bool
    {
        return $user->can('view_device');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DeviceMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, DeviceMovement $movement): bool
    {
        return false;
    }
}
