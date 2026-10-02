<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Services\Analytics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Analytics $analytics): View
    {
        return view('admin.dashboard', [
            // ยอดรวมของข้อมูลหลักเปลี่ยนบ่อยเฉพาะตอนแก้ข้อมูลหลัก จึง cache ไว้ 5 นาที
            'totals' => $this->totals(),
            'live' => $analytics->liveCounts(),
            'zoneOccupancy' => $analytics->zoneOccupancy(Carbon::today()->toDateString()),
            'recentActivity' => $analytics->recentActivity(8),
            'recentEmployees' => Employee::with('department')
                ->orderByDesc('employee_id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function realtime(Analytics $analytics): JsonResponse
    {
        return response()->json([
            'live' => $analytics->liveCounts(),
            'zoneOccupancy' => $analytics->zoneOccupancy(Carbon::today()->toDateString()),
            'recentActivity' => $analytics->recentActivity(8),
            'server_time' => Carbon::now()->format('H:i:s'),
        ]);
    }

    /**
     * ยอดรวมพนักงาน/ผู้ดูแล/แผนก/โซน/โต๊ะ — เดิมทำ 7 คิวรีต่อหนึ่งหน้า
     * รวมเป็นการนับแบบมีเงื่อนไขให้เหลือ 5 คิวรี แล้ว cache ไว้ 5 นาที
     *
     * @return array{total_employees:int, active_employees:int, inactive_employees:int, total_admins:int, total_departments:int, total_zones:int, total_desks:int, maintenance_desks:int}
     */
    private function totals(): array
    {
        $cached = Cache::remember('admin:dashboard:totals', now()->addMinutes(5), function (): array {
            $employees = Employee::query()
                ->reorder()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when employee_status = ? then 1 else 0 end) as active', [Employee::STATUS_ACTIVE])
                ->first();

            $desks = Desk::query()
                ->reorder()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when desk_status = ? then 1 else 0 end) as maintenance', [Desk::STATUS_MAINTENANCE])
                ->first();

            $totalEmployees = (int) ($employees->total ?? 0);
            $activeEmployees = (int) ($employees->active ?? 0);

            return [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'inactive_employees' => $totalEmployees - $activeEmployees,
                'total_admins' => Admin::count(),
                'total_departments' => Department::count(),
                'total_zones' => Zone::count(),
                'total_desks' => (int) ($desks->total ?? 0),
                'maintenance_desks' => (int) ($desks->maintenance ?? 0),
            ];
        });

        return $cached;
    }
}
