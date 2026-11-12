<?php

namespace Tests\Unit;

use App\Models\NetworkSnmpProfile;
use App\NetworkMonitoring\SnmpProtocolPolicy;
use Tests\TestCase;

class NetworkSnmpV2cTest extends TestCase
{
    public function test_v2c_is_opt_in_and_v3_profiles_keep_existing_behavior(): void
    {
        $this->assertFalse((bool) config('network_quality.snmp.v2c_enabled', false));

        $v3 = new NetworkSnmpProfile();
        $v3->forceFill(['version' => '3', 'enabled' => true]);
        $this->assertTrue(SnmpProtocolPolicy::canPoll($v3));

        $v2 = new NetworkSnmpProfile();
        $v2->forceFill([
            'version' => '2c',
            'enabled' => true,
            'community' => 'readOnly-Monitoring-2026',
        ]);

        $this->assertFalse(SnmpProtocolPolicy::canPoll($v2));

        config(['network_quality.snmp.v2c_enabled' => true]);

        $this->assertTrue(SnmpProtocolPolicy::canPoll($v2));
        $this->assertTrue(SnmpProtocolPolicy::canPoll($v3));

        $v2->enabled = false;
        $this->assertFalse(SnmpProtocolPolicy::canPoll($v2));

        $v2->enabled = true;
        $v2->version = '1';
        $this->assertFalse(SnmpProtocolPolicy::canPoll($v2));
    }

    public function test_community_is_encrypted_at_rest_hidden_and_write_only_in_ui(): void
    {
        $profile = new NetworkSnmpProfile();
        $profile->community = 'readOnly-Monitoring-2026';
        $profile->version = '2c';

        $this->assertSame('encrypted', $profile->getCasts()['community']);
        $this->assertContains('community', $profile->getHidden());
        $this->assertSame('readOnly-Monitoring-2026', $profile->community);
        $this->assertNotSame('readOnly-Monitoring-2026', $profile->getAttributes()['community']);
        $this->assertArrayNotHasKey('community', $profile->toArray());

        $form = file_get_contents(
            app_path('Filament/Resources/DeviceResource/RelationManagers/SnmpProfilesRelationManager.php'),
        );
        $this->assertStringContainsString("TextInput::make('community')", $form);
        $this->assertStringContainsString('->afterStateHydrated(', $form);
        $this->assertStringContainsString('SnmpProtocolPolicy::communityIsSafe', $form);
        $this->assertStringNotContainsString('TextColumn::make(\'community\')', $form);
    }

    public function test_weak_default_or_invalid_community_is_rejected(): void
    {
        foreach (['', 'public', 'PRIVATE', '1234567', "foo\nbarbaz", ' secretpass '] as $unsafe) {
            $this->assertFalse(SnmpProtocolPolicy::communityIsSafe($unsafe));
        }

        $this->assertTrue(SnmpProtocolPolicy::communityIsSafe('ro-community-2026'));
    }

    public function test_collector_uses_native_php_snmp_v2c_without_shell_execution(): void
    {
        $transport = file_get_contents(app_path('NetworkMonitoring/NativeSnmpTransport.php'));
        $poller = file_get_contents(app_path('NetworkMonitoring/SnmpInterfacePoller.php'));
        $dispatcher = file_get_contents(app_path('Jobs/DispatchSnmpPolling.php'));
        $migration = file_get_contents(
            database_path('migrations/2026_11_12_050000_add_snmp_v2c_to_network_snmp_profiles.php'),
        );

        $this->assertStringContainsString('SNMP::VERSION_2C', $transport);
        $this->assertStringContainsString('SNMP::VERSION_3', $transport);
        $this->assertStringContainsString("'authPriv'", $transport);
        $this->assertStringNotContainsString('shell_exec(', $transport);
        $this->assertStringContainsString('SnmpProtocolPolicy::canPoll(', $poller);
        $this->assertStringContainsString("'snmp_version' => \$profile->version", $poller);
        $this->assertStringContainsString("network_quality.snmp.v2c_enabled", $dispatcher);
        $this->assertStringContainsString("->default('3')", $migration);
        $this->assertStringContainsString("where('version', '2c')->exists()", $migration);
    }
}
