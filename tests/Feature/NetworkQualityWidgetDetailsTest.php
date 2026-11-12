<?php

namespace Tests\Feature;

use App\Models\NetworkQualityMeasurement;
use App\Services\NetworkQualitySnapshot;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NetworkQualityWidgetDetailsTest extends TestCase
{
    public function test_widget_and_modal_share_a_recent_per_target_snapshot(): void
    {
        $widget = file_get_contents(app_path('Filament/Widgets/NetworkQualityOverview.php'));
        $table = file_get_contents(app_path('Livewire/NetworkQualityDetailTable.php'));

        $this->assertStringContainsString('NetworkQualitySnapshot::query()', $widget);
        $this->assertStringContainsString('NetworkQualitySnapshot::query($this->quality)', $table);

        $sql = NetworkQualitySnapshot::query('bad')->toSql();
        $this->assertStringContainsString('network_quality_measurements', $sql);
        $this->assertStringContainsString('quality', $sql);
        $this->assertStringContainsString('id', $sql);
        $this->assertStringContainsString('monitor_key', $sql);
    }

    public function test_all_five_cards_dispatch_click_to_native_filament_modal(): void
    {
        $widget = file_get_contents(app_path('Filament/Widgets/NetworkQualityOverview.php'));
        $page = file_get_contents(app_path('Filament/Pages/NetworkQuality.php'));

        foreach (['all', 'good', 'degraded', 'bad', 'unknown'] as $quality) {
            $this->assertStringContainsString("detailAttributes('{$quality}'", $widget);
        }

        $this->assertStringContainsString('wire:click', $widget);
        $this->assertStringContainsString('wire:keydown.enter', $widget);
        $this->assertStringContainsString("#[On('open-network-quality-details')]", $page);
        $this->assertStringContainsString("mountAction('qualityDetails'", $page);
        $this->assertStringContainsString("modalWidth('7xl')", $page);
        $this->assertFileExists(resource_path('views/filament/pages/modals/network-quality-details.blade.php'));
        $this->assertFileExists(resource_path('views/livewire/network-quality-detail-table.blade.php'));
    }

    public function test_drill_down_is_read_only_and_contains_recommendation_and_navigation_actions(): void
    {
        $code = file_get_contents(app_path('Livewire/NetworkQualityDetailTable.php'));

        $this->assertStringContainsString("can('view_any_device')", $code);
        $this->assertStringContainsString("Action::make('recommendation')", $code);
        $this->assertStringContainsString("Action::make('device')", $code);
        $this->assertStringContainsString("Action::make('recheck')", $code);
        $this->assertStringContainsString("can('update_device')", $code);
        $this->assertStringContainsString("onQueue('network-quality')", $code);
        $this->assertStringContainsString("Action::make('target')", $code);
        $this->assertStringNotContainsString("DeleteAction::make(", $code);
        $this->assertStringNotContainsString("EditAction::make(", $code);
    }
}
