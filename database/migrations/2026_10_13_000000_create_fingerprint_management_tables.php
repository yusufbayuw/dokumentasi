<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_device_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->json('information')->nullable();
            $table->timestamp('information_synced_at')->nullable();
            $table->timestamp('users_synced_at')->nullable();
            $table->timestamp('attendance_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fingerprint_device_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('user_id', 64);
            $table->unsignedInteger('uid')->default(0);
            $table->string('name')->default('');
            $table->unsignedSmallInteger('role')->default(0);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['device_id', 'user_id']);
        });

        Schema::create('fingerprint_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('user_id', 64);
            $table->integer('uid')->default(0);
            $table->dateTime('event_at');
            $table->unsignedSmallInteger('state')->default(0);
            $table->unsignedSmallInteger('type')->default(255);
            $table->char('event_hash', 64);
            $table->timestamps();
            $table->unique(['device_id', 'event_hash']);
            $table->index(['device_id', 'event_at']);
        });

        Schema::create('fingerprint_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 32);
            $table->string('status', 16);
            $table->unsignedInteger('records')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->index(['device_id', 'created_at']);
        });

        // Permission is intentionally not granted to any role by default.
        if (Schema::hasTable('permissions')) {
            Permission::findOrCreate('view_fingerprint_data', 'web');
            Permission::findOrCreate('manage_fingerprint_clock', 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_sync_runs');
        Schema::dropIfExists('fingerprint_attendance_logs');
        Schema::dropIfExists('fingerprint_device_users');
        Schema::dropIfExists('fingerprint_device_snapshots');
    }
};
