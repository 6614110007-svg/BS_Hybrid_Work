<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\AnimalAvatar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * ตั้งค่าบัญชีครั้งแรกของพนักงานใหม่
 *
 * พนักงานที่ผู้ดูแลสร้างเข้ามาใหม่จะได้รหัสผ่านชั่วคราวและ first_login = true
 * ต้องผูกอีเมล ตั้งรหัสผ่านใหม่ และเลือก avatar ก่อนจึงจะใช้งานระบบได้
 * ปุ่ม "ออกจากระบบ" ใน modal จะไม่บังคับให้กรอก เพื่อไม่ให้ผู้ใช้ติดค้าง
 */
class AccountSetupController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $employee = $this->employee($request);

        // ตั้งค่าเสร็จแล้ว ไม่ต้องเปิด modal ซ้ำ
        if (! $employee->needsAccountSetup()) {
            return redirect()->route('dashboard');
        }

        return view('account.setup', [
            'employee' => $employee,
            'animals' => AnimalAvatar::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $employee = $this->employee($request);

        // ปุ่ม "ออกจากระบบ" — ออกจากระบบทันทีโดยไม่ต้องบังคับกรอกข้อมูล
        if ($request->boolean('logout')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'ออกจากระบบแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านเดิม แล้วตั้งค่าบัญชีให้เรียบร้อย');
        }

        if (! $employee->needsAccountSetup()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'employee_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employee', 'employee_email')->ignore($employee),
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
            'employee_avatar' => ['required', Rule::in(AnimalAvatar::keys())],
        ], [], [
            'employee_email' => 'อีเมล',
            'password' => 'รหัสผ่านใหม่',
            'employee_avatar' => 'avatar',
        ]);

        $employee->forceFill([
            'employee_email' => $validated['employee_email'],
            'employee_password' => $validated['password'],
            'employee_avatar' => $validated['employee_avatar'],
            'first_login' => false,
        ])->save();

        // พนักงานใหม่เข้ามาใช้งานแล้ว ให้ session ใหม่เพื่อไม่ให้ใช้ session เดิม
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'ตั้งค่าบัญชีเรียบร้อยแล้ว ยินดีต้อนรับสู่ระบบ');
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user('web');

        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }
}