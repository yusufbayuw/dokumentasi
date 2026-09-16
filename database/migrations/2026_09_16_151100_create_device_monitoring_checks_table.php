<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_monitoring_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')
                ->nullable()
                ->constrained('devices')
                ->nullOnDelete();
            $table->string('device_name');
            $table->string('ip_address')->nullable();
            $table->string('check_type')->default('icmp');
            $table->string('status')->index();
            $table->decimal('latency_ms', 10, 2)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['device_id', 'checked_at']);
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_monitoring_checks');
    }
};
