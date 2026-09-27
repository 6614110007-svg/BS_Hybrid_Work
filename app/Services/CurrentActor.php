<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

/**
 * จุดรวมศูนย์กลางการ resolve ผู้ใช้งานปัจจุบันจาก 2 guard (admin / employees)
 * ทั้ง Controller, Middleware และ View ใช้ตัวนี้ตัวเดียวกัน
 */
class CurrentActor
{
    public function admin(): ?Admin
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof Admin ? $user : null;
    }

    public function employee(): ?Employee
    {
        $user = Auth::guard('web')->user();

        return $user instanceof Employee ? $user : null;
    }

    public function actor(): Admin|Employee|null
    {
        return $this->admin() ?? $this->employee();
    }

    public function check(): bool
    {
        return $this->actor() instanceof Admin || $this->actor() instanceof Employee;
    }

    /**
     * ผู้ดูแลระบบ ได้แก่ บัญชีในตาราง admin หรือ employee ที่มี employee_role = 'Administrator'
     */
    public function isAdministrator(): bool
    {
        return $this->actor()?->isAdministrator() ?? false;
    }

    /**
     * พนักงานทั่วไป (employee_role = 'Employee')
     */
    public function isEmployee(): bool
    {
        return $this->employee() !== null && ! $this->employee()->isAdministrator();
    }

    public function homeRoute(): string
    {
        return $this->actor()?->homeRoute() ?? 'login';
    }

    public function forget(): void
    {
        Auth::guard('admin')->logout();
        Auth::guard('web')->logout();
    }
}
