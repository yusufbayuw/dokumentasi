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
        Schema::table('lantais', function (Blueprint $table) {
            $table->string('image_path')->nullable();
        });

        //Schema::table('devices', function (Blueprint $table) {
           // $table->json('location')->nullable();
        //});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lantais', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
