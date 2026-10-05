<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Employee;
use App\Support\AnimalAvatar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * โปรไฟล์พนักงาน ("โปรไฟล์ของฉัน")
 *
 * อ่านอย่างเดียว: ชื่อ-นามสกุล, แผนก, รหัสพนักงาน, อีเมลเดิม และอีเมลปัจจุบันที่ผูกไว้
 * แก้ได้: avatar สัตว์ 20 แบบ (เปลี่ยนได้ตลอดเวลา ไม่ต้องรอครั้งแรก)
 *
 * ไม่ให้แก้อีเมล/แผนก/รหัสพนักงานจากหน้านี้ เพราะข้อมูลเหล่านั้นเป็นข้อมูลที่ฝ่ายบุคคล/ผู้ดูแลระบบกำกับ
 * (เปลี่ยนอีเมลต้องทำตอนตั้งค่าบัญชีครั้งแรก หรือให้ผู้ดูแลแก้ให้)
 */
class EmployeeProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $employee = $this->employee($request);

        return view('profile', [
            'employee' => $employee,
            'animals' => AnimalAvatar::all(),
            'bookingStats' => $this->bookingStats($employee),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);

        $validated = $request->validate([
            'employee_avatar' => ['required', Rule::in(AnimalAvatar::keys())],
        ], [], [
            'employee_avatar' => 'avatar',
        ]);

        $employee->forceFill(['employee_avatar' => $validated['employee_avatar']])->save();

        return redirect()->route('profile')
            ->with('success', 'เปลี่ยน avatar เรียบร้อยแล้ว');
    }

    /**
     * สรุปการจองของพนักงาน ให้เห็นภาพรวมในหน้าโปรไฟล์
     *
     * @return array<string, int>
     */
    private function bookingStats(Employee $employee): array
    {
        $bookings = $employee->bookings();

        return [
            'total' => (clone $bookings)->count(),
            'reserved' => (clone $bookings)->where('booking_status', Booking::STATUS_RESERVED)->count(),
            'checked_in' => (clone $bookings)->where('booking_status', Booking::STATUS_CHECKED_IN)->count(),
            'completed' => (clone $bookings)->where('booking_status', Booking::STATUS_COMPLETED)->count(),
        ];
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user('web');

        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }
}