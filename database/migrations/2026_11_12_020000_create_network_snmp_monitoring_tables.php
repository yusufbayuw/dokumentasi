<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_snmp_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->text('security_name'); // Encrypted by the Eloquent model.
            $table->string('auth_protocol', 12)->default('SHA256');
            $table->text('auth_passphrase'); // Encrypted; never expose in tables/API.
            $table->text('privacy_passphrase'); // Encrypted; AES only.
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::table('device_interfaces', function (Blueprint $table): void {
            $table->unsignedInteger('snmp_if_index')->nullable()->after('name');
            $table->unique(['device_id', 'snmp_if_index'], 'device_interfaces_snmp_index_unique');
        });

        Schema::create('network_interface_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('device_interface_id')->nullable()->constrained('device_interfaces')->nullOnDelete();
            $table->string('interface_name');
            $table->unsignedInteger('snmp_if_index');
            $table->string('status', 16)->default('unknown');
            $table->unsignedBigInteger('in_octets')->nullable();
            $table->unsignedBigInteger('out_octets')->nullable();
            $table->unsignedBigInteger('in_errors')->nullable();
            $table->unsignedBigInteger('out_errors')->nullable();
            $table->unsignedBigInteger('in_errors_delta')->nullable();
            $table->unsignedBigInteger('out_errors_delta')->nullable();
            $table->decimal('rx_mbps', 14, 3)->nullable();
            $table->decimal('tx_mbps', 14, 3)->nullable();
            $table->unsignedInteger('speed_mbps')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['device_interface_id', 'checked_at'], 'nq_interface_sample_iface_time');
            $table->index(['device_id', 'checked_at'], 'nq_interface_sample_device_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_interface_samples');

        Schema::table('device_interfaces', function (Blueprint $table): void {
            $table->dropUnique('device_interfaces_snmp_index_unique');
            $table->dropColumn('snmp_if_index');
        });

        Schema::dropIfExists('network_snmp_profiles');
    }
};
