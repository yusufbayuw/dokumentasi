<?php

namespace App\Policies;

use App\Models\DeviceInterface;
use App\Models\User;

class DeviceInterfacePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_device');
    }

    public function view(User $user, DeviceInterface $interface): bool
    {
        return $user->can('view_device');
    }

    public function create(User $user): bool
    {
        return $user->can('update_device');
    }

    public function update(User $user, DeviceInterface $interface): bool
    {
        return $user->can('update_device');
    }

    public function delete(User $user, DeviceInterface $interface): bool
    {
        return $user->can('update_device');
    }
}
