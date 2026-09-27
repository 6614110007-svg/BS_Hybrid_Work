<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin', function (Blueprint $table) {
            $table->char('admin_id', 11)->primary();
            $table->string('admin_fullname', 100);
            $table->string('admin_email', 50)->unique();
            $table->string('admin_password', 255);
            $table->string('admin_status', 10);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin');
    }
};
