<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nac_networks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ssid')->unique();
            $table->boolean('enabled')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('nac_access_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nac_network_id')->constrained('nac_networks')->restrictOnDelete();
            $table->string('name');
            $table->string('role_name')->nullable()->index();
            $table->unsignedSmallInteger('vlan_id');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->index(['nac_network_id', 'enabled', 'priority'], 'nac_policy_lookup');
        });

        Schema::create('nac_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cmdb_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('label');
            $table->string('platform')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('nac_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nac_client_id')->constrained('nac_clients')->restrictOnDelete();
            $table->string('issuer', 512);
            $table->string('serial_number', 128);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['issuer', 'serial_number'], 'nac_cert_issuer_serial_unique');
        });

        Schema::create('nac_auth_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nac_network_id')->nullable()->constrained('nac_networks')->nullOnDelete();
            $table->foreignId('nac_client_id')->nullable()->constrained('nac_clients')->nullOnDelete();
            $table->foreignId('nac_certificate_id')->nullable()->constrained('nac_certificates')->nullOnDelete();
            $table->string('nas_identifier')->nullable();
            $table->string('calling_station_id')->nullable();
            $table->string('decision', 16);
            $table->string('reason', 64);
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->index(['nac_client_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nac_auth_events');
        Schema::dropIfExists('nac_certificates');
        Schema::dropIfExists('nac_clients');
        Schema::dropIfExists('nac_access_policies');
        Schema::dropIfExists('nac_networks');
    }
};
