<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('incident_key', 64)->unique();
            $table->string('monitor_key', 64)->index();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('network_quality_targets')->nullOnDelete();
            $table->string('target_name');
            $table->string('kind', 24);
            $table->string('severity', 16);
            $table->string('status', 16)->default('open')->index();
            $table->text('correlation_hint')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('network_incident_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_incident_id')->constrained('network_incidents')->cascadeOnDelete();
            $table->string('event_type', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 512)->nullable();
            $table->timestamps();
            $table->index(['network_incident_id', 'created_at'], 'nq_incident_event_time');
        });

        Schema::create('network_sla_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date')->index();
            $table->string('monitor_key', 64);
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('network_quality_targets')->nullOnDelete();
            $table->unsignedInteger('total_samples');
            $table->unsignedInteger('up_samples');
            $table->unsignedInteger('down_samples');
            $table->unsignedInteger('error_samples');
            $table->unsignedInteger('good_samples');
            $table->unsignedInteger('degraded_samples');
            $table->unsignedInteger('bad_samples');
            $table->decimal('observed_availability_pct', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['report_date', 'monitor_key'], 'nq_sla_day_monitor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_sla_daily');
        Schema::dropIfExists('network_incident_events');
        Schema::dropIfExists('network_incidents');
    }
};
