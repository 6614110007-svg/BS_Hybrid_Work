<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เก็บ "อีเมลเดิม" ที่ผู้ดูแลกระทยาไว้ตอนสร้างพนักงาน แยกจาก "อีเมลปัจจุบัน" ที่พนักงานผูกเองตอน First-Login
 *
 * ต้องแยกสองค่านี้ออกจากกัน เพราะหน้าโปรไฟล์พนักงานต้องแสดงทั้งคู่
 * (อีเมลที่บริษัทลงทะเบียนไว้ เทียบกับอีเมลที่ใช้เข้าสู่ระบบจริง)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->string('employee_original_email', 255)->nullable()->after('employee_email');
        });

        // พนักงานเดิมที่ยังไม่เคยผูกอีเมลใหม่ ให้ถือว่าอีเมลเดิม = อีเมลปัจจุบัน
        DB::table('employee')
            ->whereNull('employee_original_email')
            ->whereNotNull('employee_email')
            ->update(['employee_original_email' => DB::raw('employee_email')]);
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->dropColumn('employee_original_email');
        });
    }
};
