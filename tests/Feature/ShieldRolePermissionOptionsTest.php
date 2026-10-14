<?php

namespace Tests\Feature;

use App\Filament\Resources\Shield\RoleResource;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use ReflectionProperty;
use Tests\TestCase;

class ShieldRolePermissionOptionsTest extends TestCase
{
    public function test_page_options_use_permission_keys_and_page_class_labels(): void
    {
        FilamentShield::shouldReceive('getPages')->once()->andReturn([
            'page_DataQuality' => [
                'class' => 'App\\Filament\\Pages\\DataQuality',
                'permission' => 'page_DataQuality',
            ],
        ]);
        FilamentShield::shouldReceive('getLocalizedPageLabel')
            ->once()
            ->with('App\\Filament\\Pages\\DataQuality')
            ->andReturn('Kualitas Data');

        $this->assertSame(
            ['page_DataQuality' => 'Kualitas Data'],
            RoleResource::getPageOptions()
        );
    }

    public function test_widget_options_use_permission_keys_and_widget_class_labels(): void
    {
        FilamentShield::shouldReceive('getWidgets')->once()->andReturn([
            'widget_DeviceStats' => [
                'class' => 'App\\Filament\\Widgets\\DeviceStats',
                'permission' => 'widget_DeviceStats',
            ],
        ]);
        FilamentShield::shouldReceive('getLocalizedWidgetLabel')
            ->once()
            ->with('App\\Filament\\Widgets\\DeviceStats')
            ->andReturn('Statistik Perangkat');

        $this->assertSame(
            ['widget_DeviceStats' => 'Statistik Perangkat'],
            RoleResource::getWidgetOptions()
        );
    }

    public function test_custom_permissions_exclude_shield_page_and_widget_permissions(): void
    {
        $property = new ReflectionProperty(RoleResource::class, 'permissionsCollection');
        $property->setAccessible(true);
        $original = $property->getValue();

        $property->setValue(null, collect([
            (object) ['name' => 'page_DataQuality'],
            (object) ['name' => 'widget_DeviceStats'],
            (object) ['name' => 'view_fingerprint_data'],
        ]));

        try {
            FilamentShield::shouldReceive('getResources')->once()->andReturn([]);
            FilamentShield::shouldReceive('getPages')->once()->andReturn([
                'page_DataQuality' => [
                    'class' => 'App\\Filament\\Pages\\DataQuality',
                    'permission' => 'page_DataQuality',
                ],
            ]);
            FilamentShield::shouldReceive('getWidgets')->once()->andReturn([
                'widget_DeviceStats' => [
                    'class' => 'App\\Filament\\Widgets\\DeviceStats',
                    'permission' => 'widget_DeviceStats',
                ],
            ]);

            $this->assertSame(
                ['view_fingerprint_data' => 'View Fingerprint Data'],
                RoleResource::getCustomPermissionOptions()
            );
        } finally {
            $property->setValue(null, $original);
        }
    }
}
