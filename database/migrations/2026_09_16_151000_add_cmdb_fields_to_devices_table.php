<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->foreignId('owner_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->string('asset_code')->nullable()->unique();
            $table->string('hostname')->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('pic')->nullable();

            $table->string('lifecycle_status')->default('active')->index();
            $table->string('criticality')->default('normal')->index();

            $table->date('purchase_date')->nullable();
            $table->string('vendor')->nullable();
            $table->date('warranty_until')->nullable()->index();

            $table->boolean('monitoring_enabled')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->decimal('last_latency_ms', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_unit_id');
            $table->dropUnique(['asset_code']);
            $table->dropIndex(['lifecycle_status']);
            $table->dropIndex(['criticality']);
            $table->dropIndex(['warranty_until']);
            $table->dropIndex(['monitoring_enabled']);
            $table->dropIndex(['last_checked_at']);
            $table->dropIndex(['last_seen_at']);

            $table->dropColumn([
                'asset_code',
                'hostname',
                'mac_address',
                'serial_number',
                'manufacturer',
                'model',
                'firmware_version',
                'pic',
                'lifecycle_status',
                'criticality',
                'purchase_date',
                'vendor',
                'warranty_until',
                'monitoring_enabled',
                'last_checked_at',
                'last_seen_at',
                'last_latency_ms',
            ]);
        });
    }
};
