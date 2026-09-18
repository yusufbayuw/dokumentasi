<?php

namespace App\Providers;

use App\Models\User;
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
use App\Observers\ActivityObserver;
use App\Observers\UserObserver;
use App\Observers\DeviceObserver;
use App\Observers\NetworkObserver;
use App\Observers\TopologyObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Device::observe(DeviceObserver::class);
        Device::observe(ActivityObserver::class);
        DeviceConnection::observe(ActivityObserver::class);
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
        User::observe(UserObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
