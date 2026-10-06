<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\DeviceConnection;
use App\Models\DeviceInterface;
use App\Models\DeviceMaintenance;
use App\Models\Gedung;
use App\Models\Lantai;
use App\Models\Network;
use App\Models\Ruangan;
use App\Models\Topology;
use App\Models\TypeDevice;
use App\Models\Unit;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Observers\DeviceConnectionObserver;
use App\Observers\DeviceInterfaceObserver;
use App\Observers\DeviceObserver;
use App\Observers\NetworkObserver;
use App\Observers\TopologyObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot(): void
    {
        Device::observe(DeviceObserver::class);
        Device::observe(ActivityObserver::class);

        DeviceConnection::observe(DeviceConnectionObserver::class);
        DeviceConnection::observe(ActivityObserver::class);

        DeviceInterface::observe(DeviceInterfaceObserver::class);
        DeviceInterface::observe(ActivityObserver::class);

        DeviceMaintenance::observe(ActivityObserver::class);
        Network::observe(NetworkObserver::class);
        Network::observe(ActivityObserver::class);
        Unit::observe(ActivityObserver::class);
        Gedung::observe(ActivityObserver::class);
        Lantai::observe(ActivityObserver::class);
        Ruangan::observe(ActivityObserver::class);
        TypeDevice::observe(ActivityObserver::class);

        Topology::observe(TopologyObserver::class);
        Topology::observe(ActivityObserver::class);

        User::observe(UserObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
