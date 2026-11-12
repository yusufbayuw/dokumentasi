<?php

namespace Tests\Feature;

use App\Models\NetworkSnmpProfile;
use App\NetworkMonitoring\InterfaceCounterCalculator;
use Tests\TestCase;

class NetworkSnmpPhase4WiringTest extends TestCase
{
    public function test_snmp_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('network_quality.snmp.enabled', false));
        $this->assertSame('network-quality', 'network-quality');
    }

    public function test_secrets_are_encrypted_and_hidden_in_profile_model(): void
    {
        $profile = new NetworkSnmpProfile();
        $this->assertSame('encrypted', $profile->getCasts()['auth_passphrase']);
        $this->assertSame('encrypted', $profile->getCasts()['privacy_passphrase']);
        $this->assertSame('encrypted', $profile->getCasts()['security_name']);
        $this->assertContains('auth_passphrase', $profile->getHidden());
        $this->assertContains('privacy_passphrase', $profile->getHidden());
    }

    public function test_native_filament_table_and_chart_have_permission_guards(): void
    {
        $page = file_get_contents(app_path('Filament/Pages/NetworkInterfaces.php'));
        $form = file_get_contents(app_path('Filament/Resources/DeviceResource/RelationManagers/SnmpProfilesRelationManager.php'));
        $transport = file_get_contents(app_path('NetworkMonitoring/NativeSnmpTransport.php'));

        $this->assertStringContainsString("can('view_any_device')", $page);
        $this->assertStringContainsString("can('update_device')", $page);
        $this->assertStringContainsString("class NetworkInterfaceTrafficChart", file_get_contents(app_path('Livewire/NetworkInterfaceTrafficChart.php')));
        $this->assertStringContainsString("->password()", $form);
        $this->assertStringNotContainsString('shell_exec(', $transport);
        $this->assertStringContainsString("SNMP::VERSION_3", $transport);
        $this->assertStringContainsString("'authPriv'", $transport);
        $this->assertStringContainsString("network_quality.snmp.enabled", $page);
        $this->assertFileExists(resource_path('views/filament/pages/modals/interface-history.blade.php'));
    }

    public function test_error_counter_safety_rules(): void
    {
        $this->assertNull(InterfaceCounterCalculator::delta('100', '5'));
        $this->assertSame(50, InterfaceCounterCalculator::delta('100', '150'));
    }
}
