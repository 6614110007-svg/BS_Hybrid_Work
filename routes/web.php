<?php

use App\Http\Controllers\AccountSetupController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\EmployeeProfileController;
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

    // ลืมรหัสผ่าน — ตรวจว่าอีเมลมีในระบบ แล้วแนะนำขั้นตอนให้ติดต่อผู้ดูแล
    Route::get('password/recovery', [PasswordRecoveryController::class, 'create'])->name('password.recovery');
    Route::post('password/recovery', [PasswordRecoveryController::class, 'store'])->name('password.recovery.store');
});

Route::middleware('actor')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // ตั้งค่าบัญชีครั้งแรก — เข้าถึงได้แม้ยังไม่ผ่าน actor.active
    // เพราะพนักงานใหม่ต้องผูกอีเมล/ตั้งรหัสผ่าน/เลือก avatar ก่อนจึงจะใช้งานระบบได้
    Route::get('account/setup', [AccountSetupController::class, 'edit'])->name('account.setup');
    Route::put('account/setup', [AccountSetupController::class, 'update'])->name('account.setup.update');

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
    // แก้ไขวันที่ / ช่วงเวลา / โต๊ะ ของใบจองที่ยังรอเช็คอิน
    Route::patch('/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::get('/bookings/{booking}/checkin', [CheckInController::class, 'create'])->name('bookings.checkin');
    Route::post('/bookings/{booking}/checkin', [CheckInController::class, 'store'])->name('bookings.checkin.store');
    Route::post('/bookings/{booking}/checkout', [CheckInController::class, 'checkout'])->name('bookings.checkout');

    // โปรไฟล์พนักงาน — ดูข้อมูลตัวเอง และเปลี่ยน avatar ได้ตลอดเวลา
    Route::get('/profile', [EmployeeProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [EmployeeProfileController::class, 'update'])->name('profile.update');
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

    // โปรไฟล์ผู้ดูแลระบบ — แก้ได้เฉพาะชื่อที่แสดงและ avatar (บทบาท/สิทธิ์แก้ไม่ได้)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // จำนวนโต๊ะสดของแต่ละโซน สำหรับอัปเดตหน้าจัดการโซนแบบ real-time
    Route::get('/zones/realtime', [ZoneController::class, 'realtime'])->name('zones.realtime');

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

    // เลขโต๊ะถัดไป + ช่อง grid ที่ว่าง สำหรับเติมฟอร์มสร้างโต๊ะ
    Route::get('desks/suggest', [DeskController::class, 'suggest'])
        ->name('desks.suggest');
});
