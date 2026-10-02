<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\SeatMapController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DeskController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ZoneController;
use App\Services\CurrentActor;
use Illuminate\Support\Facades\Route;

Route::get('/', function (CurrentActor $current) {
    if ($current->check()) {
        return redirect()->route($current->homeRoute());
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('actor')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // เจ้าของใบจองหรือผู้ดูแลระบบเท่านั้นที่ดูรูปเช็คอินได้
    Route::middleware('actor.active')->group(function () {
        Route::get('bookings/{booking}/photo', [CheckInController::class, 'photo'])->name('bookings.photo');
    });
});

/*
|--------------------------------------------------------------------------
| Employee — ค้นหาและจองโต๊ะ
|--------------------------------------------------------------------------
*/

Route::middleware(['employee', 'actor.active'])->group(function () {
    Route::get('/dashboard', [SeatMapController::class, 'index'])->name('dashboard');
    Route::get('/seatmap/status', [SeatMapController::class, 'status'])->name('seatmap.status');
    // โหลดเฉพาะผังโต๊ะเป็น HTML ผ่าน fetch ตอนเปลี่ยนวันที่/ช่วงเวลา/โซน
    Route::get('/seatmap/partial', [SeatMapController::class, 'partial'])->name('seatmap.partial');

    Route::get('/bookings', [BookingController::class, 'mine'])->name('bookings.mine');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::get('/bookings/{booking}/checkin', [CheckInController::class, 'create'])->name('bookings.checkin');
    Route::post('/bookings/{booking}/checkin', [CheckInController::class, 'store'])->name('bookings.checkin.store');
    Route::post('/bookings/{booking}/checkout', [CheckInController::class, 'checkout'])->name('bookings.checkout');
});

/*
|--------------------------------------------------------------------------
| Admin — จัดการข้อมูลหลักและรายงาน
|--------------------------------------------------------------------------
*/

Route::middleware(['administrator', 'actor.active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/realtime', [AdminDashboardController::class, 'realtime'])->name('dashboard.realtime');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy'])
        ->name('bookings.destroy');

    Route::resource('departments', DepartmentController::class)->except(['show']);
    Route::resource('zones', ZoneController::class)->except(['show']);
    Route::resource('desks', DeskController::class)->except(['show']);
    Route::resource('employees', EmployeeController::class)->except(['show']);

    Route::patch('employees/{employee}/status', [EmployeeController::class, 'toggleStatus'])
        ->name('employees.status');
    Route::patch('desks/{desk}/status', [DeskController::class, 'toggleStatus'])
        ->name('desks.status');
});
