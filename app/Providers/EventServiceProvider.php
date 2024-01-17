<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Device;
use App\Models\Network;
use App\Models\Topology;
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
        Topology::observe(TopologyObserver::class);
        Network::observe(NetworkObserver::class);
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
