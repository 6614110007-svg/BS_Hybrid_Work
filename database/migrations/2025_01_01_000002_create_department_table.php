<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department', function (Blueprint $table) {
            $table->char('department_id', 11)->primary();
            $table->string('department_name', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department');
    }
};
