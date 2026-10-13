<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\TypeDevice;
use App\Models\User;
use App\Support\FingerprintDevice;
use PHPUnit\Framework\TestCase;

class FingerprintRestartVisibilityTest extends TestCase
{
    private function device(string $typeName): Device
    {
        $device = new Device();
        $type = new TypeDevice();
        $type->nama = $typeName;
        $device->setRelation('type', $type);

        return $device;
    }

    private function user(bool $superAdmin, bool $canUpdate): User
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->with('super_admin')->willReturn($superAdmin);
        $user->method('can')->with('update_device')->willReturn($canUpdate);

        return $user;
    }

    public function test_super_admin_can_see_restart_without_explicit_update_permission(): void
    {
        $this->assertTrue(FingerprintDevice::canRestart(
            $this->device('Finger Print'),
            $this->user(true, false),
        ));
    }

    public function test_explicit_device_editor_can_see_restart(): void
    {
        $this->assertTrue(FingerprintDevice::canRestart(
            $this->device('Finger Print'),
            $this->user(false, true),
        ));
    }

    public function test_read_only_user_cannot_see_restart(): void
    {
        $this->assertFalse(FingerprintDevice::canRestart(
            $this->device('Finger Print'),
            $this->user(false, false),
        ));
    }

    public function test_non_fingerprint_never_exposes_restart(): void
    {
        $this->assertFalse(FingerprintDevice::canRestart(
            $this->device('Switch'),
            $this->user(true, true),
        ));
    }

    public function test_anonymous_user_cannot_restart(): void
    {
        $this->assertFalse(FingerprintDevice::canRestart(
            $this->device('Finger Print'),
            null,
        ));
    }
}
