<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_interfaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('ip_address_id')->nullable()->constrained('ip_addresses')->nullOnDelete();
            $table->string('name');
            $table->string('mac_address', 32)->nullable();
            $table->string('interface_type')->default('ethernet');
            $table->unsignedInteger('speed_mbps')->nullable();
            $table->string('vlan')->nullable();
            $table->string('admin_status')->default('up');
            $table->string('oper_status')->default('unknown');
            $table->boolean('is_management')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'name']);
            $table->index(['device_id', 'oper_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_interfaces');
    }
};
