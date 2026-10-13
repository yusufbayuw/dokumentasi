<?php

namespace Tests\Feature;

use App\Filament\Resources\DeviceResource\Pages\ManageFingerprint;
use Filament\Actions\Action;
use Tests\TestCase;

class FingerprintPageActionsTest extends TestCase
{
    public function test_operational_actions_are_registered_on_the_page(): void
    {
        $page = new ManageFingerprint();

        foreach (['information', 'users', 'attendance', 'clock', 'reboot'] as $name) {
            $action = $page->getAction($name);

            $this->assertInstanceOf(Action::class, $action);
            $this->assertSame($name, $action->getName());
        }
    }

    public function test_page_view_contains_explicit_actions_toolbar(): void
    {
        $view = file_get_contents(resource_path('views/filament/resources/device-resource/pages/manage-fingerprint.blade.php'));

        $this->assertStringContainsString('Aksi Fingerprint', $view);
        $this->assertStringContainsString("getAction('reboot')", $view);
        $this->assertStringContainsString("getAction('information')", $view);
        $this->assertStringContainsString('Mode hanya baca', $view);
    }
}
