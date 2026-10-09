<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MikroTikHotspotService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MikroTikHotspotTest extends TestCase
{
    private function user(): User
    {
        $user = new User(['name' => 'Test', 'is_active' => true]);
        $user->id = 42;
        $user->exists = true;

        return $user;
    }

    private function enable(): void
    {
        config([
            'mikrotik_hotspot.enabled' => true,
            'mikrotik_hotspot.url' => 'https://router.example.test',
            'mikrotik_hotspot.username' => 'hotspot-service',
            'mikrotik_hotspot.password' => str_repeat('p', 32),
            'mikrotik_hotspot.user_secret' => str_repeat('s', 48),
            'mikrotik_hotspot.profile' => 'default',
            'mikrotik_hotspot.ca_bundle' => '',
        ]);
    }

    public function test_disabled_gateway_fails_without_connecting_to_router(): void
    {
        Http::fake();
        config(['mikrotik_hotspot.enabled' => false]);

        $this->expectException(RuntimeException::class);
        try {
            app(MikroTikHotspotService::class)->connect($this->user(), 'AA:BB:CC:DD:EE:FF', '10.10.1.25');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_unsafe_http_configuration_is_rejected(): void
    {
        $this->enable();
        config(['mikrotik_hotspot.url' => 'http://router.example.test']);
        Http::fake();

        $this->expectException(RuntimeException::class);
        try {
            app(MikroTikHotspotService::class)->connect($this->user(), 'AA:BB:CC:DD:EE:FF', '10.10.1.25');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_unknown_hotspot_host_cannot_be_provisioned(): void
    {
        $this->enable();
        Http::fakeSequence()->push([], 200);

        $this->expectException(RuntimeException::class);
        try {
            app(MikroTikHotspotService::class)->connect($this->user(), 'AA:BB:CC:DD:EE:FF', '10.10.1.25');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_trusted_host_creates_owned_user_and_attempts_login(): void
    {
        $this->enable();
        Http::fakeSequence()
            ->push([['mac-address' => 'AA:BB:CC:DD:EE:FF', 'address' => '10.10.1.25']], 200)
            ->push([], 200)
            ->push(['.id' => '*1'], 201)
            ->push([], 200);

        app(MikroTikHotspotService::class)->connect($this->user(), 'aa:bb:cc:dd:ee:ff', '10.10.1.25');

        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/rest/ip/hotspot/user')
            && $request['name'] === 'dokumentasi-42'
            && $request['password'] !== 'invman'
            && strlen($request['password']) === 64
        );
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/rest/ip/hotspot/active/login')
            && $request['user'] === 'dokumentasi-42'
            && $request['mac-address'] === 'AA:BB:CC:DD:EE:FF'
        );
    }

    public function test_conflicting_hotspot_account_is_never_overwritten(): void
    {
        $this->enable();
        Http::fakeSequence()
            ->push([['mac-address' => 'AA:BB:CC:DD:EE:FF', 'address' => '10.10.1.25']], 200)
            ->push([['.id' => '*1', 'name' => 'dokumentasi-42', 'comment' => 'manual']], 200);

        $this->expectException(RuntimeException::class);
        try {
            app(MikroTikHotspotService::class)->connect($this->user(), 'AA:BB:CC:DD:EE:FF', '10.10.1.25');
        } finally {
            Http::assertSentCount(2);
        }
    }
}
