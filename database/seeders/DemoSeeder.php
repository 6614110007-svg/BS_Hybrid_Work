<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * ข้อมูลตัวอย่างสำหรับทดลองใช้งานระบบในเครื่อง (php artisan db:seed)
 *
 * บัญชีเริ่มต้น
 *   - admin@bs-hybrid.test / password
 *   - employee@bs-hybrid.test / password  (พนักงานทั่วไป)
 *   - manager@bs-hybrid.test / password  (พนักงานที่มีสิทธิ์ผู้ดูแลระบบ)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::updateOrCreate(
            ['admin_email' => 'admin@bs-hybrid.test'],
            [
                'admin_fullname' => 'ผู้ดูแลระบบ',
                'admin_password' => 'password',
                'admin_status' => Admin::STATUS_ACTIVE,
            ]
        );

        $departments = collect(['พัฒนาธุรกิจ', 'เทคโนโลยี', 'บัญชี', 'ทรัพยากรบุคคล'])
            ->map(fn (string $name) => Department::updateOrCreate(
                ['department_name' => $name],
                []
            ));

        $zones = collect([
            ['name' => 'โซนติดหน้าต่าง', 'desks' => 8],
            ['name' => 'โซนชั้น 2', 'desks' => 6],
            ['name' => 'โซนเงียบ', 'desks' => 4],
        ])->map(fn (array $zone) => [
            'zone' => Zone::updateOrCreate(['zone_name' => $zone['name']], []),
            'count' => $zone['desks'],
        ]);

        $desks = collect();

        foreach ($zones as $index => $row) {
            for ($i = 1; $i <= $row['count']; $i++) {
                $desks->push(Desk::updateOrCreate(
                    ['desk_number' => 'A'.($index + 1).str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                    [
                        'zone_id' => $row['zone']->zone_id,
                        'map_position' => (($i - 1) % 4 + 1).','.(intdiv($i - 1, 4) + 1),
                        'desk_status' => Desk::STATUS_AVAILABLE,
                    ]
                ));
            }
        }

        $employees = collect([
            ['email' => 'employee@bs-hybrid.test', 'name' => 'สมชาย ใจดี', 'role' => Employee::ROLE_EMPLOYEE, 'dept' => 0],
            ['email' => 'manager@bs-hybrid.test', 'name' => 'สุดา บริหาร', 'role' => Employee::ROLE_ADMIN, 'dept' => 0],
            ['email' => 'wichai@bs-hybrid.test', 'name' => 'วิชัย แก้วใส', 'role' => Employee::ROLE_EMPLOYEE, 'dept' => 1],
            ['email' => 'ploy@bs-hybrid.test', 'name' => 'พลอย ทองดี', 'role' => Employee::ROLE_EMPLOYEE, 'dept' => 2],
        ])->values()->map(fn (array $row, int $index) => Employee::updateOrCreate(
            ['employee_email' => $row['email']],
            [
                'employee_fullname' => $row['name'],
                'employee_tel' => '080'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                'employee_password' => 'password',
                'employee_role' => $row['role'],
                'employee_status' => Employee::STATUS_ACTIVE,
                'department_id' => $departments[$row['dept']]->department_id,
            ]
        ));

        $slots = TimeSlot::all();
        $today = Carbon::today()->toDateString();

        Booking::updateOrCreate(
            [
                'employee_id' => $employees[0]->employee_id,
                'booking_date' => $today,
                'time_slot' => $slots[0]->name,
            ],
            [
                'desk_id' => $desks[0]->desk_id,
                'start_time' => $slots[0]->start,
                'end_time' => $slots[0]->end,
                'booking_status' => Booking::STATUS_RESERVED,
            ]
        );

        Booking::updateOrCreate(
            [
                'employee_id' => $employees[1]->employee_id,
                'booking_date' => $today,
                'time_slot' => $slots[0]->name,
            ],
            [
                'desk_id' => $desks[1]->desk_id,
                'start_time' => $slots[0]->start,
                'end_time' => $slots[0]->end,
                'booking_status' => Booking::STATUS_CHECKED_IN,
                'actual_checkin_time' => Carbon::now()->subMinutes(10),
                'checkin_photo' => 'checkins/demo/demo-selfie.jpg',
            ]
        );

        Booking::updateOrCreate(
            [
                'employee_id' => $employees[2]->employee_id,
                'booking_date' => $today,
                'time_slot' => $slots[1]->name,
            ],
            [
                'desk_id' => $desks[2]->desk_id,
                'start_time' => $slots[1]->start,
                'end_time' => $slots[1]->end,
                'booking_status' => Booking::STATUS_EXPIRED,
            ]
        );

        $this->command?->info('สร้างข้อมูลตัวอย่างเรียบร้อยแล้ว');
        $this->command?->newLine();
        $this->command?->table(
            ['บทบาท', 'อีเมล', 'รหัสผ่าน'],
            [
                ['ผู้ดูแลระบบ (admin)', $admin->admin_email, 'password'],
                ['พนักงานทั่วไป', $employees[0]->employee_email, 'password'],
                ['พนักงานสิทธิ์ผู้ดูแล', $employees[1]->employee_email, 'password'],
            ]
        );
    }
}
