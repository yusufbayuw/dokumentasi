<?php

namespace Tests\Feature;

use Tests\TestCase;

class NacRadiusAuthenticationTest extends TestCase
{
    public function test_missing_radius_api_key_denies_access(): void
    {
        config(['nac.radius_api_key' => null]);

        $this->postJson('/api/nac/radius/authorize', [])
            ->assertUnauthorized();
    }

    public function test_wrong_radius_api_key_denies_access(): void
    {
        config(['nac.radius_api_key' => str_repeat('a', 40)]);

        $this->withHeader('X-NAC-Radius-Key', str_repeat('b', 40))
            ->postJson('/api/nac/radius/authorize', [])
            ->assertUnauthorized();
    }

    public function test_untrusted_source_is_denied(): void
    {
        config([
            'nac.radius_api_key' => str_repeat('a', 40),
            'nac.radius_allowed_ips' => ['10.0.0.1'],
        ]);

        $this->withHeader('X-NAC-Radius-Key', str_repeat('a', 40))
            ->postJson('/api/nac/radius/authorize', [])
            ->assertUnauthorized();
    }

    public function test_authenticated_but_invalid_payload_is_rejected(): void
    {
        config([
            'nac.radius_api_key' => str_repeat('a', 40),
            'nac.radius_allowed_ips' => [],
        ]);

        $this->withHeader('X-NAC-Radius-Key', str_repeat('a', 40))
            ->postJson('/api/nac/radius/authorize', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ssid', 'certificate_serial', 'tls_verified']);
    }
}
