<?php

namespace Tests\Feature;

use App\Exports\FingerprintUsersExport;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\TypeDevice;
use App\Support\FingerprintManagement;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
        $this->assertStringContainsString('$action->halt();', $resource);
        $this->assertStringNotContainsString("getUrl('fingerprint'", $resource);
        $this->assertFileExists(resource_path('views/filament/resources/device-resource/modals/manage-fingerprint.blade.php'));
    }

    public function test_modal_has_all_previous_read_only_sections_and_both_exports(): void
    {
        $view = file_get_contents(resource_path('views/filament/resources/device-resource/modals/manage-fingerprint.blade.php'));

        foreach (['Ringkasan', 'Pengguna', 'Absensi', 'Riwayat Operasi', 'exportFingerprintAttendance', 'exportFingerprintUsers', 'Unduh seluruh XLSX'] as $label) {
            $this->assertStringContainsString($label, $view);
        }

        $this->assertStringContainsString('wire:click="exportFingerprintAttendance(', $view);
        $this->assertStringContainsString('wire:click="exportFingerprintUsers(', $view);
        $this->assertStringContainsString('wire:poll.10s', $view);
    }

    public function test_fingerprint_xlsx_export_queries_entire_device_and_escapes_formula_cells(): void
    {
        $export = new FingerprintUsersExport(123);
        $query = $export->query();

        $this->assertSame([123], $query->getBindings());
        $this->assertStringContainsString('fingerprint_device_users', $query->toSql());
        $this->assertStringNotContainsString('limit', strtolower($query->toSql()));
        $this->assertSame(['ID Pengguna', 'UID', 'Nama', 'Role', 'Terakhir Terbaca'], $export->headings());
        $this->assertSame(['0007', 4, '=2+2', 0, '2026-11-04 10:00:00'], $export->map((object) [
            'user_id' => '0007',
            'uid' => 4,
            'name' => '=2+2',
            'role' => 0,
            'last_seen_at' => '2026-11-04 10:00:00',
        ]));

        $spreadsheet = new Spreadsheet();
        $cell = $spreadsheet->getActiveSheet()->getCell('C2');
        $this->assertTrue($export->bindValue($cell, '=2+2'));
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame('=2+2', $cell->getValue());
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

    public function test_unauthenticated_users_cannot_export_fingerprint_xlsx(): void
    {
        $device = new Device();
        $device->setRelation('type', (new TypeDevice())->forceFill(['nama' => 'Finger Print']));

        $this->expectException(HttpException::class);
        FingerprintManagement::exportUsers($device, null);
    }
}
