<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use Filament\Actions\Action;
use ReflectionMethod;
use Tests\TestCase;

class FingerprintRebootAllHeaderTest extends TestCase
{
    public function test_reboot_all_is_a_header_action_of_list_devices(): void
    {
        $page = new ListDevices();
        $method = new ReflectionMethod(ListDevices::class, 'getHeaderActions');
        $actions = $method->invoke($page);

        $matches = array_values(array_filter($actions, fn ($action) =>
            $action instanceof Action && $action->getName() === 'rebootAllFingerprint',
        ));

        $this->assertCount(1, $matches);
        $this->assertSame('Reboot All', $matches[0]->getLabel());
    }
}
