<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Services\Analytics;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Analytics $analytics): View
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('employee_status', Employee::STATUS_ACTIVE)->count();

        return view('admin.dashboard', [
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'inactiveEmployees' => $totalEmployees - $activeEmployees,
            'totalAdmins' => \App\Models\Admin::count(),
            'totalDepartments' => Department::count(),
            'totalZones' => Zone::count(),
            'totalDesks' => Desk::count(),
            'maintenanceDesks' => Desk::maintenance()->count(),
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
}
