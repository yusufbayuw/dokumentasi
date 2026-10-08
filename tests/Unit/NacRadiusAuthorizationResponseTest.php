<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\NacRadiusAuthorizationController;
use App\Services\NacAuthorizationService;
use Illuminate\Http\Request;
use Tests\TestCase;

class NacRadiusAuthorizationResponseTest extends TestCase
{
    public function test_success_returns_only_radius_vlan_reply_attributes(): void
    {
        $service = $this->mock(NacAuthorizationService::class);
        $service->shouldReceive('decide')->once()->andReturn([
            'allowed' => true, 'reason' => 'allow_policy', 'vlan_id' => 30,
        ]);

        $request = Request::create('/api/nac/radius/authorize', 'POST', [
            'ssid' => 'EduOS-Secure',
            'certificate_issuer' => 'CA-YTB',
            'certificate_serial' => 'F1',
            'tls_verified' => true,
        ]);

        $response = app(NacRadiusAuthorizationController::class)($request, $service);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'reply:Tunnel-Type' => 'VLAN',
            'reply:Tunnel-Medium-Type' => 'IEEE-802',
            'reply:Tunnel-Private-Group-Id' => '30',
        ], $response->getData(true));
    }

    public function test_denial_is_http_forbidden(): void
    {
        $service = $this->mock(NacAuthorizationService::class);
        $service->shouldReceive('decide')->once()->andReturn([
            'allowed' => false, 'reason' => 'deny_no_policy', 'vlan_id' => null,
        ]);

        $request = Request::create('/api/nac/radius/authorize', 'POST', [
            'ssid' => 'EduOS-Secure',
            'certificate_issuer' => 'CA-YTB',
            'certificate_serial' => 'F1',
            'tls_verified' => true,
        ]);

        $response = app(NacRadiusAuthorizationController::class)($request, $service);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('access_denied', $response->getData(true)['error']);
    }
}
