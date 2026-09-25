<?php

use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeatMapController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DeskController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ZoneController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->isAdmin() ? 'admin.dashboard' : 'dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'show'])
        ->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'store'])
        ->name('password.change.store');

    Route::middleware('must.change')->group(function () {
        Route::get('/dashboard', [SeatMapController::class, 'index'])->name('dashboard');
        Route::get('/seatmap/status', [SeatMapController::class, 'status'])->name('seatmap.status');

        Route::get('/bookings', [BookingController::class, 'mine'])->name('bookings.mine');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

        Route::get('/bookings/{booking}/checkin', [CheckInController::class, 'create'])->name('bookings.checkin');
        Route::post('/bookings/{booking}/checkin', [CheckInController::class, 'store'])->name('bookings.checkin.store');
        Route::post('/bookings/{booking}/checkout', [CheckInController::class, 'checkout'])->name('bookings.checkout');
        Route::get('/bookings/{booking}/photo', [CheckInController::class, 'photo'])->name('bookings.photo');

        Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                ->name('dashboard');
            Route::get('/dashboard/realtime', [AdminDashboardController::class, 'realtime'])
                ->name('dashboard.realtime');

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

            Route::resource('departments', DepartmentController::class)->except(['show']);
            Route::resource('zones', ZoneController::class)->except(['show']);
            Route::resource('desks', DeskController::class)->except(['show']);
            Route::resource('employees', EmployeeController::class)->except(['show']);

            Route::patch('employees/{user}/toggle-status', [EmployeeController::class, 'toggleStatus'])
                ->name('employees.toggle-status');
        });
    });
});

require __DIR__.'/auth.php';