<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_probe_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('isp_name')->nullable()->index();
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->boolean('enabled')->default(false)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('network_quality_measurements', function (Blueprint $table): void {
            $table->foreignId('probe_location_id')
                ->nullable()->constrained('network_probe_locations')->nullOnDelete();
            $table->string('probe_name')->nullable();
            $table->string('isp_name')->nullable();
            $table->uuid('external_report_id')->nullable()->unique();
            $table->index(['probe_location_id', 'checked_at'], 'nq_measurements_probe_time');
        });
    }

    public function down(): void
    {
        Schema::table('network_quality_measurements', function (Blueprint $table): void {
            $table->dropIndex('nq_measurements_probe_time');
            $table->dropUnique(['external_report_id']);
            $table->dropConstrainedForeignId('probe_location_id');
            $table->dropColumn(['probe_name', 'isp_name', 'external_report_id']);
        });

        Schema::dropIfExists('network_probe_locations');
    }
};
