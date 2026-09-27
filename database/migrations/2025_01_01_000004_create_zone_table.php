<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zone', function (Blueprint $table) {
            $table->char('zone_id', 11)->primary();
            $table->string('zone_name', 50);
            $table->integer('zone_total_desks')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone');
    }
};
