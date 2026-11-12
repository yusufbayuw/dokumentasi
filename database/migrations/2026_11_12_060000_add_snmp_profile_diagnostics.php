<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->timestamp('last_polled_at')->nullable();
            $table->string('last_poll_error', 80)->nullable();
            $table->unsignedInteger('last_sample_count')->default(0);
            $table->unsignedInteger('last_discovered_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('network_snmp_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'last_polled_at',
                'last_poll_error',
                'last_sample_count',
                'last_discovered_count',
            ]);
        });
    }
};
