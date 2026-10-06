<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropForeign(['type_device_id']);
            $table->foreign('type_device_id')
                ->references('id')
                ->on('type_devices')
                ->restrictOnDelete();
        });

        Schema::table('lantais', function (Blueprint $table): void {
            $table->dropForeign(['gedung_id']);
            $table->foreign('gedung_id')
                ->references('id')
                ->on('gedungs')
                ->restrictOnDelete();
        });

        Schema::table('ruangans', function (Blueprint $table): void {
            $table->dropForeign(['lantai_id']);
            $table->foreign('lantai_id')
                ->references('id')
                ->on('lantais')
                ->restrictOnDelete();
        });

        Schema::table('ip_addresses', function (Blueprint $table): void {
            $table->dropForeign(['network_id']);
            $table->foreign('network_id')
                ->references('id')
                ->on('networks')
                ->restrictOnDelete();
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE device_maintenances MODIFY status VARCHAR(255) NOT NULL DEFAULT 'planned'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE device_maintenances ALTER COLUMN status SET DEFAULT 'planned'");
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropForeign(['type_device_id']);
            $table->foreign('type_device_id')
                ->references('id')
                ->on('type_devices')
                ->cascadeOnDelete();
        });

        Schema::table('lantais', function (Blueprint $table): void {
            $table->dropForeign(['gedung_id']);
            $table->foreign('gedung_id')
                ->references('id')
                ->on('gedungs')
                ->cascadeOnDelete();
        });

        Schema::table('ruangans', function (Blueprint $table): void {
            $table->dropForeign(['lantai_id']);
            $table->foreign('lantai_id')
                ->references('id')
                ->on('lantais')
                ->cascadeOnDelete();
        });

        Schema::table('ip_addresses', function (Blueprint $table): void {
            $table->dropForeign(['network_id']);
            $table->foreign('network_id')
                ->references('id')
                ->on('networks')
                ->cascadeOnDelete();
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE device_maintenances MODIFY status VARCHAR(255) NOT NULL DEFAULT 'completed'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE device_maintenances ALTER COLUMN status SET DEFAULT 'completed'");
        }
    }
};
