<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect([
            ['code' => 'IT', 'name' => 'แผนกเทคโนโลยีสารสนเทศ', 'description' => 'ฝ่ายดูแลระบบและพัฒนา'],
            ['code' => 'HR', 'name' => 'แผนกทรัพยากรบุคคล', 'description' => 'ฝ่ายบุคคล'],
            ['code' => 'MKT', 'name' => 'แผนกการตลาด', 'description' => 'ฝ่ายการตลาดและประชาสัมพันธ์'],
            ['code' => 'FIN', 'name' => 'แผนกการเงินและบัญชี', 'description' => 'ฝ่ายการเงิน'],
        ])->mapWithKeys(fn ($d) => [$d['code'] => Department::create($d)])->all();

        $zones = collect([
            ['code' => 'Z-A', 'name' => 'โซน A - ฝั่งหน้าต่าง', 'department_id' => $departments['IT']->id, 'floor' => 2, 'sort_order' => 1],
            ['code' => 'Z-B', 'name' => 'โซน B - กลางห้อง', 'department_id' => $departments['MKT']->id, 'floor' => 2, 'sort_order' => 2],
            ['code' => 'Z-C', 'name' => 'โซน C - ฝั่งทางเข้า', 'department_id' => $departments['FIN']->id, 'floor' => 2, 'sort_order' => 3],
        ])->mapWithKeys(fn ($z) => [$z['code'] => Zone::create($z)])->all();

        $deskSpecs = [
            ['Z-A', 'A-01', 'โต๊ะ A1', 1, 1],
            ['Z-A', 'A-02', 'โต๊ะ A2', 3, 1],
            ['Z-A', 'A-03', 'โต๊ะ A3', 5, 1],
            ['Z-A', 'A-04', 'โต๊ะ A4', 1, 3],
            ['Z-A', 'A-05', 'โต๊ะ A5', 3, 3],
            ['Z-A', 'A-06', 'โต๊ะ A6', 5, 3],
            ['Z-B', 'B-01', 'โต๊ะ B1', 1, 1],
            ['Z-B', 'B-02', 'โต๊ะ B2', 3, 1],
            ['Z-B', 'B-03', 'โต๊ะ B3', 5, 1],
            ['Z-B', 'B-04', 'โต๊ะ B4', 1, 3],
            ['Z-B', 'B-05', 'โต๊ะ B5', 3, 3],
            ['Z-B', 'B-06', 'โต๊ะ B6', 5, 3],
            ['Z-C', 'C-01', 'โต๊ะ C1', 1, 1],
            ['Z-C', 'C-02', 'โต๊ะ C2', 3, 1],
            ['Z-C', 'C-03', 'โต๊ะ C3', 5, 1],
            ['Z-C', 'C-04', 'โต๊ะ C4', 1, 3],
            ['Z-C', 'C-05', 'โต๊ะ C5', 3, 3],
            ['Z-C', 'C-06', 'โต๊ะ C6', 5, 3],
        ];

        foreach ($deskSpecs as [$zoneCode, $code, $label, $x, $y]) {
            Desk::create([
                'zone_id' => $zones[$zoneCode]->id,
                'code' => $code,
                'label' => $label,
                'x' => $x,
                'y' => $y,
            ]);
        }

        $timeSlots = collect([
            ['code' => 'AM', 'name' => 'ช่วงเช้า 09:00-13:00', 'start_time' => '09:00', 'end_time' => '13:00', 'sort_order' => 1],
            ['code' => 'PM', 'name' => 'ช่วงบ่าย 13:00-18:00', 'start_time' => '13:00', 'end_time' => '18:00', 'sort_order' => 2],
        ]);

        foreach ($timeSlots as $slot) {
            TimeSlot::create($slot);
        }

        $createdAt = now();

        User::create([
            'name' => 'ผู้ดูแลระบบ',
            'email' => 'admin@deskbooking.local',
            'password' => 'Password123!',
            'employee_code' => 'ADM-001',
            'role' => User::ROLE_ADMIN,
            'employee_status' => User::STATUS_ACTIVE,
            'must_change_password' => false,
            'department_id' => $departments['IT']->id,
            'email_verified_at' => $createdAt,
            'remember_token' => Str::random(10),
        ]);

        User::create([
            'name' => 'สมชาย ใจดี',
            'email' => 'somchai@deskbooking.local',
            'password' => 'TempPass123!',
            'employee_code' => 'EMP-001',
            'role' => User::ROLE_EMPLOYEE,
            'employee_status' => User::STATUS_ACTIVE,
            'must_change_password' => true,
            'department_id' => $departments['IT']->id,
            'email_verified_at' => $createdAt,
            'remember_token' => Str::random(10),
        ]);
    }
}