<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('from_ruangan_id')->nullable()->constrained('ruangans')->nullOnDelete();
            $table->foreignId('to_ruangan_id')->nullable()->constrained('ruangans')->nullOnDelete();
            $table->dateTime('moved_at');
            $table->string('movement_type')->default('relocation');
            $table->text('reason')->nullable();
            $table->boolean('automatic')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_movements');
    }
};
