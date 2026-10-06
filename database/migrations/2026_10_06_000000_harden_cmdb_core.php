<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('credential_type')->default('management');
            $table->text('username_encrypted')->nullable();
            $table->text('secret_encrypted')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'credential_type']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable()->index();
            $table->timestamp('password_changed_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->string('monitoring_status')->default('unknown')->index();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->boolean('auto_reboot_enabled')->default(false)->index();
            $table->timestamp('last_auto_reboot_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropIndex(['monitoring_status']);
            $table->dropIndex(['auto_reboot_enabled']);
            $table->dropColumn([
                'monitoring_status',
                'consecutive_failures',
                'auto_reboot_enabled',
                'last_auto_reboot_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_login_at']);
            $table->dropColumn([
                'is_active',
                'last_login_at',
                'password_changed_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });

        Schema::dropIfExists('device_credentials');
    }
};
