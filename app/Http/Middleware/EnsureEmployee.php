<?php

namespace App\Http\Middleware;

use App\Services\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ผ่านได้เฉพาะพนักงานทั่วไป (employee_role = 'Employee')
 *
 * ป้องกันปัญหา "Admin ล็อกอินแล้วเผลอเห็นหน้า Employee dashboard"
 * โดยส่งผู้ดูแลระบบกลับไปที่ /admin/dashboard ทันที
 */
class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $current = app(CurrentActor::class);

        if ($current->isEmployee()) {
            return $next($request);
        }

        if ($current->check()) {
            return redirect()
                ->route($current->homeRoute())
                ->with('error', 'บัญชีของคุณไม่มีสิทธิ์เข้าใช้หน้านี้');
        }

        return redirect()->route('login')->with('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }
}
