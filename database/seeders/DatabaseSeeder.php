<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ข้อมูลเริ่มต้นของระบบ: ผู้ดูแล 1 บัญชี, แผนก, โซน และโต๊ะทำงาน
     */
    public function run(): void
    {
        $this->call([
            DemoSeeder::class,
        ]);
    }
}
