<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('target_device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('source')->default('manual')->index();
            $table->string('connection_type')->default('ethernet')->index();
            $table->string('source_port')->nullable();
            $table->string('target_port')->nullable();
            $table->string('speed')->nullable();
            $table->string('vlan')->nullable();
            $table->boolean('is_primary')->default(true)->index();
            $table->string('status')->default('active')->index();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['source_device_id', 'target_device_id']);
            $table->index(['target_device_id', 'source_device_id']);
        });

        $now = now();

        DB::table('topologies as child')
            ->join('topologies as parent', 'child.parent_id', '=', 'parent.id')
            ->whereNotNull('child.device_id')
            ->whereNotNull('parent.device_id')
            ->whereColumn('child.device_id', '!=', 'parent.device_id')
            ->select([
                'parent.device_id as source_device_id',
                'child.device_id as target_device_id',
                'child.keterangan as description',
            ])
            ->orderBy('child.id')
            ->chunk(500, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    $exists = DB::table('device_connections')
                        ->where('source_device_id', $row->source_device_id)
                        ->where('target_device_id', $row->target_device_id)
                        ->where('source', 'topology')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('device_connections')->insert([
                        'source_device_id' => $row->source_device_id,
                        'target_device_id' => $row->target_device_id,
                        'source' => 'topology',
                        'connection_type' => 'ethernet',
                        'is_primary' => true,
                        'status' => 'active',
                        'description' => $row->description,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_connections');
    }
};
