<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_interfaces') || ! Schema::hasTable('devices')) {
            return;
        }

        DB::table('devices')
            ->select(['id', 'ip_address_id', 'mac_address'])
            ->where(function ($query): void {
                $query->whereNotNull('ip_address_id')
                    ->orWhereNotNull('mac_address');
            })
            ->orderBy('id')
            ->chunkById(200, function ($devices): void {
                foreach ($devices as $device) {
                    $hasInterface = DB::table('device_interfaces')
                        ->where('device_id', $device->id)
                        ->exists();

                    if ($hasInterface) {
                        continue;
                    }

                    DB::table('device_interfaces')->insert([
                        'device_id' => $device->id,
                        'ip_address_id' => $device->ip_address_id,
                        'name' => 'primary',
                        'mac_address' => $device->mac_address,
                        'interface_type' => 'ethernet',
                        'speed_mbps' => null,
                        'vlan' => null,
                        'admin_status' => 'up',
                        'oper_status' => 'unknown',
                        'is_management' => true,
                        'description' => 'Dibuat otomatis dari data jaringan perangkat lama saat migrasi CMDB.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_interfaces')) {
            return;
        }

        DB::table('device_interfaces')
            ->where('name', 'primary')
            ->where('description', 'Dibuat otomatis dari data jaringan perangkat lama saat migrasi CMDB.')
            ->delete();
    }
};
