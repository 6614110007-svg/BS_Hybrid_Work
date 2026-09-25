<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code')->nullable()->unique();
            $table->foreignId('department_id')->nullable()->after('employee_code')
                ->constrained('departments')->nullOnDelete();
            $table->string('role')->default('employee')->index();
            $table->string('employee_status')->default('active')->index();
            $table->boolean('must_change_password')->default(false);
            $table->string('phone')->nullable();
            $table->string('photo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'employee_code',
                'role',
                'employee_status',
                'must_change_password',
                'phone',
                'photo',
            ]);
        });
    }
};