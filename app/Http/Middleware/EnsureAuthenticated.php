<?php

namespace App\Http\Middleware;

use App\Services\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ล็อกอินแล้ว (ไม่ว่าจะเป็นบัญชีตาราง admin หรือ employee) — ใช้กับหน้าที่ทั้งสองฝั่งใช้ร่วมกัน
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $current = app(CurrentActor::class);

        if ($current->check()) {
            return $next($request);
        }

        return redirect()->route('login')->with('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }
}
