<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เก็บโจทย์เซลฟี่ที่สุ่มได้ตอนเช็คอิน เพื่อแสดงย้อนหลังได้ในหน้าดูรูป
        // เป็นคอลัมน์แบบ nullable เพิ่มเติม ข้อมูลเดิมที่ยังไม่มีค่าจะไม่ถูกกระทบ
        Schema::table('booking', function (Blueprint $table) {
            $table->string('selfie_prompt', 120)->nullable()->after('checkin_photo');
        });
    }

    public function down(): void
    {
        Schema::table('booking', function (Blueprint $table) {
            $table->dropColumn('selfie_prompt');
        });
    }
};
