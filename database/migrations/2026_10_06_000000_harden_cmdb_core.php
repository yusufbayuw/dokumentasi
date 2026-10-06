<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_credentials')) {
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
        }

        $this->addUserColumn('is_active', fn (Blueprint $table) => $table->boolean('is_active')->default(true)->index());
        $this->addUserColumn('last_login_at', fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable()->index());
        $this->addUserColumn('password_changed_at', fn (Blueprint $table) => $table->timestamp('password_changed_at')->nullable());
        $this->addUserColumn('two_factor_secret', fn (Blueprint $table) => $table->text('two_factor_secret')->nullable());
        $this->addUserColumn('two_factor_recovery_codes', fn (Blueprint $table) => $table->text('two_factor_recovery_codes')->nullable());
        $this->addUserColumn('two_factor_confirmed_at', fn (Blueprint $table) => $table->timestamp('two_factor_confirmed_at')->nullable());

        $this->addDeviceColumn('monitoring_status', fn (Blueprint $table) => $table->string('monitoring_status')->default('unknown')->index());
        $this->addDeviceColumn('consecutive_failures', fn (Blueprint $table) => $table->unsignedSmallInteger('consecutive_failures')->default(0));
        $this->addDeviceColumn('auto_reboot_enabled', fn (Blueprint $table) => $table->boolean('auto_reboot_enabled')->default(false)->index());
        $this->addDeviceColumn('last_auto_reboot_at', fn (Blueprint $table) => $table->timestamp('last_auto_reboot_at')->nullable());
    }

    public function down(): void
    {
        $userColumns = collect([
            'is_active',
            'last_login_at',
            'password_changed_at',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('users', $column))->all();

        if ($userColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($userColumns): void {
                $table->dropColumn($userColumns);
            });
        }

        $deviceColumns = collect([
            'monitoring_status',
            'consecutive_failures',
            'auto_reboot_enabled',
            'last_auto_reboot_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('devices', $column))->all();

        if ($deviceColumns !== []) {
            Schema::table('devices', function (Blueprint $table) use ($deviceColumns): void {
                $table->dropColumn($deviceColumns);
            });
        }

        Schema::dropIfExists('device_credentials');
    }

    private function addUserColumn(string $column, callable $definition): void
    {
        if (Schema::hasColumn('users', $column)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function addDeviceColumn(string $column, callable $definition): void
    {
        if (Schema::hasColumn('devices', $column)) {
            return;
        }

        Schema::table('devices', function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }
};
