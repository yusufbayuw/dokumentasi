<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_maintenances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('maintenance_type')->default('inspection');
            $table->string('status')->default('completed');
            $table->dateTime('performed_at');
            $table->string('performed_by')->nullable();
            $table->string('vendor')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->dateTime('next_due_at')->nullable();
            $table->text('description')->nullable();
            $table->text('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'performed_at']);
            $table->index(['status', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_maintenances');
    }
};
