<?php

namespace App\Http\Middleware;

use App\Services\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ผ่านได้เฉพาะผู้ดูแลระบบเท่านั้น
 * ครอบคลุมทั้งบัญชีตาราง admin และ employee ที่มี employee_role = 'Administrator'
 */
class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $current = app(CurrentActor::class);

        if ($current->isAdministrator()) {
            return $next($request);
        }

        if ($current->check()) {
            return redirect()
                ->route($current->homeRoute())
                ->with('error', 'หน้านี้สงวนไว้สำหรับผู้ดูแลระบบเท่านั้น');
        }

        return redirect()->route('login')->with('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }
}
