<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่มข้อมูลประจำโซน (คำบรรยายบรรยากาศ + รูปปก)
     * และข้อมูลตั้งค่าบัญชีของพนักงาน/ผู้ดูแลระบบ
     */
    public function up(): void
    {
        Schema::table('zone', function (Blueprint $table) {
            $table->text('zone_description')->nullable()->after('zone_name');
            $table->string('zone_image', 255)->nullable()->after('zone_description');
        });

        Schema::table('employee', function (Blueprint $table) {
            // first_login = true หมายถึงยังต้องตั้งค่าบัญชีครั้งแรก (ผูกอีเมล/เปลี่ยนรหัสผ่าน/เลือก avatar)
            $table->boolean('first_login')->default(false)->after('employee_status');
            $table->string('employee_avatar', 40)->nullable()->after('first_login');
        });

        Schema::table('admin', function (Blueprint $table) {
            $table->string('admin_display_name', 100)->nullable()->after('admin_fullname');
            $table->string('admin_avatar', 40)->nullable()->after('admin_display_name');
        });
    }

    public function down(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->dropColumn(['admin_display_name', 'admin_avatar']);
        });

        Schema::table('employee', function (Blueprint $table) {
            $table->dropColumn(['first_login', 'employee_avatar']);
        });

        Schema::table('zone', function (Blueprint $table) {
            $table->dropColumn(['zone_description', 'zone_image']);
        });
    }
};