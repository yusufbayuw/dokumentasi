<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\TypeDevice;
use App\Support\FingerprintManagement;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FingerprintModalTest extends TestCase
{
    public function test_fingerprint_management_is_a_modal_instead_of_a_page_route(): void
    {
        $this->assertArrayNotHasKey('fingerprint', DeviceResource::getPages());

        $resource = file_get_contents(app_path('Filament/Resources/DeviceResource.php'));
        $this->assertStringContainsString("Action::make('manageFingerprint')", $resource);
        $this->assertStringContainsString("->modalContent(", $resource);
        $this->assertStringContainsString("->modalSubmitAction(", $resource);
        $this->assertStringNotContainsString("getUrl('fingerprint'", $resource);
        $this->assertFileExists(resource_path('views/filament/resources/device-resource/modals/manage-fingerprint.blade.php'));
    }

    public function test_modal_has_all_previous_read_only_sections_and_csv_export(): void
    {
        $view = file_get_contents(resource_path('views/filament/resources/device-resource/modals/manage-fingerprint.blade.php'));

        foreach (['Ringkasan', 'Pengguna', 'Absensi', 'Riwayat Operasi', 'exportFingerprintAttendance'] as $label) {
            $this->assertStringContainsString($label, $view);
        }

        $this->assertStringContainsString('wire:click="exportFingerprintAttendance(', $view);
    }

    public function test_unauthenticated_users_cannot_enqueue_or_export(): void
    {
        $device = new Device();
        $device->setRelation('type', (new TypeDevice())->forceFill(['nama' => 'Finger Print']));

        $this->assertSame([], FingerprintManagement::operationOptions($device, null));

        try {
            FingerprintManagement::enqueue($device, null, 'reboot', true);
            $this->fail('Unauthenticated command must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(HttpException::class);
        FingerprintManagement::exportAttendance($device, null);
    }
}
