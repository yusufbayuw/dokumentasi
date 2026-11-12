<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_snmp_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('network_snmp_profile_id')->constrained('network_snmp_profiles')->cascadeOnDelete();
            $table->string('snmp_version', 4);
            $table->string('status', 20)->default('running')->index();
            $table->json('roots');
            $table->unsignedSmallInteger('root_index')->default(0);
            $table->string('cursor_oid', 768)->nullable();
            $table->unsignedInteger('oid_count')->default(0);
            $table->unsignedBigInteger('collected_bytes')->default(0);
            $table->unsignedInteger('limited_values')->default(0);
            $table->unsignedInteger('snapshot_max_oids');
            $table->unsignedInteger('snapshot_max_value_bytes');
            $table->string('error_code', 80)->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'started_at'], 'snmp_snapshots_device_time');
            $table->index(['network_snmp_profile_id', 'status'], 'snmp_snapshots_profile_status');
        });

        Schema::create('network_snmp_oids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_snmp_snapshot_id')->constrained('network_snmp_snapshots')->cascadeOnDelete();
            $table->string('oid', 768);
            $table->char('oid_hash', 64);
            $table->integer('value_type')->nullable();
            $table->string('encoding', 12)->default('utf8');
            $table->longText('value'); // Encrypted by Eloquent; may contain sensitive details.
            $table->unsignedInteger('raw_bytes');
            $table->boolean('truncated')->default(false);
            $table->timestamps();

            $table->unique(['network_snmp_snapshot_id', 'oid_hash'], 'snmp_snapshot_oid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_snmp_oids');
        Schema::dropIfExists('network_snmp_snapshots');
    }
};
