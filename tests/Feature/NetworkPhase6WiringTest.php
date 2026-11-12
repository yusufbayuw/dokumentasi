<?php

namespace Tests\Feature;

use Tests\TestCase;

class NetworkPhase6WiringTest extends TestCase
{
    public function test_incident_detection_and_alerts_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('network_quality.incidents.enabled', false));
        $this->assertFalse((bool) config('network_quality.incidents.alerts_enabled', false));
    }

    public function test_incident_resource_has_no_create_or_delete_actions(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/NetworkIncidentResource.php'));

        $this->assertStringContainsString('class NetworkIncidentResource', $resource);
        $this->assertStringContainsString('canCreate(): bool { return false; }', $resource);
        $this->assertStringContainsString('canDelete(Model $record): bool { return false; }', $resource);
        $this->assertStringContainsString('NetworkIncidentWorkflow', $resource);
        $this->assertStringContainsString("can('update_device')", $resource);
    }

    public function test_scheduler_reuses_existing_worker_and_sla_report_explains_measurements(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $report = file_get_contents(resource_path('views/filament/pages/network-sla-report.blade.php'));

        $this->assertStringContainsString("new AnalyzeNetworkIncidents(), 'network-quality'", $kernel);
        $this->assertStringContainsString("new BuildNetworkSlaDaily(), 'network-quality'", $kernel);
        $this->assertStringContainsString('bukan persentase ketersediaan waktu', $report);
        $this->assertStringContainsString('ExportBulkAction::make()', file_get_contents(app_path('Filament/Pages/NetworkSlaReport.php')));
    }
}
