<?php

namespace Tests\Unit;

use Tests\TestCase;

class RemoteProbeAgentSafetyTest extends TestCase
{
    public function test_agent_uses_static_allowlist_and_no_shell(): void
    {
        $content = file_get_contents(base_path('scripts/network_probe_agent.py'));

        $this->assertStringContainsString('approved_target(target, config)', $content);
        $this->assertStringContainsString('ipaddress.ip_network', $content);
        $this->assertStringContainsString('ssl.create_default_context()', $content);
        $this->assertStringContainsString('NoRedirect', $content);
        $this->assertStringContainsString('NQ_PROBE_TOKEN', $content);
        $this->assertStringNotContainsString('shell=True', $content);
        $this->assertStringContainsString('subprocess.run(', $content);
        $this->assertFileExists(base_path('scripts/network_probe_agent.example.json'));
    }
}
