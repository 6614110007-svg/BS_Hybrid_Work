<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee', function (Blueprint $table) {
            $table->char('employee_id', 11)->primary();
            $table->string('employee_fullname', 100);
            $table->string('employee_tel', 10);
            $table->string('employee_email', 50)->unique();
            $table->string('employee_password', 255);
            $table->string('employee_role', 20);
            $table->string('employee_status', 10);
            $table->string('department_id', 11);

            $table->foreign('department_id')
                ->references('department_id')
                ->on('department')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee');
    }
};
