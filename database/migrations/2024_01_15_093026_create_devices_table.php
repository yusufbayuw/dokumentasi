<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_address_id')->nullable()->constrained('ip_addresses')->nullOnDelete();
            $table->foreignId('type_device_id')->constrained('type_devices')->cascadeOnDelete();
            $table->foreignId('ruangan_id')->nullable()->constrained('ruangans')->nullOnDelete();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
