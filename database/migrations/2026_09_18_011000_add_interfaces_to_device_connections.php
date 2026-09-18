<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_connections', function (Blueprint $table): void {
            $table->foreignId('source_interface_id')
                ->nullable()
                ->after('source_device_id')
                ->constrained('device_interfaces')
                ->nullOnDelete();

            $table->foreignId('target_interface_id')
                ->nullable()
                ->after('target_device_id')
                ->constrained('device_interfaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_connections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_interface_id');
            $table->dropConstrainedForeignId('target_interface_id');
        });
    }
};
