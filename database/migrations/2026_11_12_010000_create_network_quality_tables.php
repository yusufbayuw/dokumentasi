<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_quality_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('probe_type', 16);
            $table->string('address', 512);
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedSmallInteger('expected_status')->nullable();
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedTinyInteger('samples')->default(3);
            $table->unsignedTinyInteger('timeout_seconds')->default(2);
            $table->boolean('enabled')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('network_quality_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('network_quality_targets')->nullOnDelete();
            $table->string('monitor_key', 64);
            $table->string('target_name');
            $table->string('address', 512);
            $table->string('probe_type', 16);
            $table->string('status', 16);
            $table->string('quality', 16);
            $table->unsignedSmallInteger('samples_sent')->nullable();
            $table->unsignedSmallInteger('samples_received')->nullable();
            $table->decimal('latency_ms', 10, 2)->nullable();
            $table->decimal('packet_loss_pct', 6, 2)->nullable();
            $table->decimal('rtt_variation_ms', 10, 2)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['monitor_key', 'checked_at'], 'nq_measurements_key_time');
            $table->index(['device_id', 'checked_at'], 'nq_measurements_device_time');
            $table->index(['target_id', 'checked_at'], 'nq_measurements_target_time');
            $table->index(['quality', 'checked_at'], 'nq_measurements_quality_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_quality_measurements');
        Schema::dropIfExists('network_quality_targets');
    }
};
