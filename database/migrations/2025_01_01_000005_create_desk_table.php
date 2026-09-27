<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk', function (Blueprint $table) {
            $table->char('desk_id', 11)->primary();
            $table->string('desk_number', 10);
            $table->string('map_position', 255);
            $table->string('desk_status', 20)->default('Available');
            $table->string('zone_id', 11);
            $table->index('zone_id');

            $table->foreign('zone_id')
                ->references('zone_id')
                ->on('zone')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk');
    }
};
