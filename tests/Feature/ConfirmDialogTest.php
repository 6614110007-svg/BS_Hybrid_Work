<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * ยืนยันว่าทุกจุดที่เคยใช้ window.confirm ของเบราว์เซอร์
 * ถูกเปลี่ยนเป็น data-confirm (Alpine modal) แล้วทั้งหมด
 *
 * ถ้ามีจุดใดยังใช้ onsubmit="return confirm(...)" อยู่
 * เทสต์นี้จะ fail เพื่อกันไม่ให้หน้าต่างใช้ modal ของเบราว์เซอร์ผสมกับ modal ของระบบ
 */
class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * หน้าที่ต้องมี modal ยืนยันร่วมกันทุกหน้า
     *
     * @var array<int,string>
     */
    private const ADMIN_PAGES = [
        'admin.zones.index',
        'admin.zones.create',
        'admin.departments.index',
        'admin.departments.create',
        'admin.desks.index',
        'admin.desks.create',
        'admin.employees.index',
        'admin.employees.create',
        'admin.bookings.index',
        'admin.profile.edit',
        'admin.dashboard',
    ];

    public function test_no_view_still_uses_native_browser_confirm(): void
    {
        $offenders = [];
        $viewPath = resource_path('views');

        // glob() ไม่ recursive ต้องใช้ iterator ไม่งั้นจะตรวจเฉพาะโฟลเดอร์ชั้นเดียว
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            // ข้ามคอมเมนต์ในไฟล์
            $code = preg_replace('/\{\{--.*?--\}\}/s', '', $code) ?? $code;

            if (preg_match('/return\s+confirm\s*\(|onsubmit="return/', $code) === 1) {
                $offenders[] = str_replace($viewPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "ยังมีหน้าที่ใช้ confirm() ของเบราว์เซอร์: ".implode(', ', $offenders)
        );
    }

    public function test_every_admin_page_includes_the_shared_confirm_modal(): void
    {
        $admin = Admin::factory()->create();

        foreach (self::ADMIN_PAGES as $routeName) {
            $this->actingAs($admin, 'admin')
                ->get(route($routeName))
                ->assertOk("หน้า {$routeName} เปิดไม่ได้")
                // partial ของ modal ต้องถูก include ไว้ใน layout
                ->assertSee('x-data="confirmDialog"', false, "หน้า {$routeName} ไม่ได้ include modal ยืนยัน")
                ->assertSee('approve', false)
                ->assertSee('cancel', false);
        }
    }

    public function test_admin_list_pages_use_data_confirm_on_destructive_forms(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();
        $department = Department::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id]);
        Department::factory()->create();
        Employee::factory()->create(['department_id' => $department->department_id]);

        foreach ([
            'admin.zones.index',
            'admin.departments.index',
            'admin.desks.index',
            'admin.employees.index',
        ] as $routeName) {
            $this->actingAs($admin, 'admin')
                ->get(route($routeName))
                ->assertOk("หน้า {$routeName} เปิดไม่ได้")
                // ปุ่มลบต้องใช้ data-confirm แทน confirm() ของเบราว์เซอร์
                ->assertSee('data-confirm="', false, "หน้า {$routeName} ยังไม่มี data-confirm");
        }
    }

    public function test_booking_mine_page_uses_data_confirm_for_cancellation(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $this->bookableDate(),
            'booking_status' => Booking::STATUS_RESERVED,
        ]);

        $this->actingAs($employee, 'web')
            ->get(route('bookings.mine'))
            ->assertOk()
// ปุ่มยกเลิกการจองต้องใช้ data-confirm แทน confirm() ของเบราว์เซอร์
                ->assertSee('data-confirm="', false)
                ->assertSee('x-data="confirmDialog"', false);
    }

    public function test_booking_confirmation_modal_still_uses_its_own_alpine_flow(): void
    {
        $employee = Employee::factory()->create();

        // ยืนยันว่าหน้าผังโต๊ะยังใช้ bookingConfirm ของตัวเอง ไม่ถูกแทนที่
        $this->actingAs($employee, 'web')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('bookingConfirm', false);
    }
}