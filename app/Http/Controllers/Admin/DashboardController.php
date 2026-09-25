<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Desk;
use App\Models\User;
use App\Models\Zone;
use App\Services\Analytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Analytics $analytics): View
    {
        $totalEmployees = User::where('role', User::ROLE_EMPLOYEE)->count();
        $activeEmployees = User::where('role', User::ROLE_EMPLOYEE)
            ->where('employee_status', User::STATUS_ACTIVE)->count();
        $inactiveEmployees = $totalEmployees - $activeEmployees;

        $totalDesks = Desk::count();
        $activeDesks = Desk::where('is_active', true)->count();
        $maintenanceDesks = Desk::where('is_maintenance', true)->count();

        return view('admin.dashboard', [
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'inactiveEmployees' => $inactiveEmployees,
            'totalDepartments' => Department::count(),
            'activeDepartments' => Department::where('is_active', true)->count(),
            'totalZones' => Zone::active()->count(),
            'totalDesks' => $totalDesks,
            'activeDesks' => $activeDesks,
            'maintenanceDesks' => $maintenanceDesks,
            'disabledDesks' => $totalDesks - $activeDesks,
            'live' => $analytics->liveCounts(),
            'zoneOccupancy' => $analytics->zoneOccupancy(\Carbon\Carbon::today()->toDateString()),
            'recentActivity' => $analytics->recentActivity(8),
            'recentEmployees' => User::where('role', User::ROLE_EMPLOYEE)
                ->with('department')->latest()->limit(5)->get(),
        ]);
    }

    public function realtime(Analytics $analytics): JsonResponse
    {
        return response()->json([
            'live' => $analytics->liveCounts(),
            'zoneOccupancy' => $analytics->zoneOccupancy(\Carbon\Carbon::today()->toDateString()),
            'recentActivity' => $analytics->recentActivity(8),
            'server_time' => now()->format('H:i:s'),
        ]);
    }
}