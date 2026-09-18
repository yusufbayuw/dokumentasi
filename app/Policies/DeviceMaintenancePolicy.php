<?php

namespace App\Policies;

use App\Models\DeviceMaintenance;
use App\Models\User;

class DeviceMaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_device');
    }

    public function view(User $user, DeviceMaintenance $maintenance): bool
    {
        return $user->can('view_device');
    }

    public function create(User $user): bool
    {
        return $user->can('update_device');
    }

    public function update(User $user, DeviceMaintenance $maintenance): bool
    {
        return $user->can('update_device');
    }

    public function delete(User $user, DeviceMaintenance $maintenance): bool
    {
        return $user->can('update_device');
    }
}
