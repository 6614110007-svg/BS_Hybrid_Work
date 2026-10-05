<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\AnimalAvatar;
use App\Support\DeskGrid;
use App\Support\IdSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ฟีเจอร์ชุดใหม่: โซนมีรูปปก/คำบรรยาย, โต๊ะมีเลขและพิกัดแนะนำอัตโนมัติ,
 * พนักงานได้รหัส EMP+ปีเดือน+ลำดับ และตั้งค่าบัญชีครั้งแรกได้
 */
class SolutionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Zone

    /**
     * สร้างไฟล์ PNG จริงโดยไม่พึ่งส่วนขยาย GD
     * (เครื่องที่รันเทสต์บางเครื่องไม่ได้ติดตั้ง GD ทำให้ UploadedFile::fake()->image() ใช้ไม่ได้)
     */
    private function pngUpload(string $name = 'cover.png'): UploadedFile
    {
        // PNG 1x1 pixel ที่ถูกต้องตามมาตรฐาน
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'zoneimg').'.png';
        file_put_contents($path, $binary);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_zone_can_be_created_with_description_and_image(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.zones.store'), [
                'zone_name' => 'โซนทดสอบรูป',
                'zone_description' => 'อยู่ชั้น 2 ติดลิฟต์',
                'zone_image' => $this->pngUpload(),
            ])
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHasNoErrors();

        $zone = Zone::where('zone_name', 'โซนทดสอบรูป')->sole();

        $this->assertSame('อยู่ชั้น 2 ติดลิฟต์', $zone->zone_description);
        $this->assertNotNull($zone->zone_image);

        Storage::disk('public')->assertExists($zone->zone_image);
    }

    public function test_zone_without_image_is_allowed(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.zones.store'), ['zone_name' => 'โซนไม่มีรูป'])
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHasNoErrors();

        $zone = Zone::where('zone_name', 'โซนไม่มีรูป')->sole();

        $this->assertNull($zone->zone_image);
        $this->assertNull($zone->imageUrl());
    }

    public function test_zone_rejects_non_image_upload(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.zones.create'))
            ->post(route('admin.zones.store'), [
                'zone_name' => 'โซนไฟล์ผิด',
                'zone_image' => UploadedFile::fake()->create('note.pdf', 64, 'application/pdf'),
            ])
            ->assertSessionHasErrors('zone_image');
    }

    public function test_updating_zone_can_remove_existing_image(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $path = $this->pngUpload('old.png')->store('zones', 'public');
        $zone = Zone::factory()->create(['zone_image' => $path]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.zones.update', $zone), [
                'zone_name' => $zone->zone_name,
                'remove_zone_image' => 1,
            ])
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHasNoErrors();

        $this->assertNull($zone->fresh()->zone_image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_zone_realtime_returns_current_desk_count(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();
        Desk::factory()->count(3)->create(['zone_id' => $zone->zone_id]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(route('admin.zones.realtime'))
            ->assertOk();

        $response->assertJsonFragment(['zone_id' => $zone->zone_id, 'desks_count' => 3]);
    }

    public function test_zone_realtime_requires_admin(): void
    {
        $this->actingAs(Employee::factory()->create(), 'web')
            ->getJson(route('admin.zones.realtime'))
            ->assertRedirect();
    }

    // ---------------------------------------------------------------- Desk

    public function test_desk_suggestion_uses_last_number_and_first_free_grid_slot(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        // ช่องที่ 1,1 และ 1,2 ถูกใช้แล้ว → ไล่ทีละแถวจากซ้ายไปขวา จึงได้ 2,1
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A01', 'map_position' => '1,1']);
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A02', 'map_position' => '1,2']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.desks.suggest', ['zone_id' => $zone->zone_id]))
            ->assertOk()
            ->assertJson([
                'desk_number' => 'A03',
                'map_position' => '2,1',
            ]);
    }

    public function test_desk_suggestion_starts_at_a01_for_an_empty_zone(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.desks.suggest', ['zone_id' => $zone->zone_id]))
            ->assertOk()
            ->assertJson(['desk_number' => 'A01', 'map_position' => '1,1']);
    }

    public function test_desk_suggestion_compares_numbers_numerically_not_as_text(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        // "A9" เรียงหลัง "A10" ในการเรียงข้อความ แต่ตัวเลข 9 มีค่าน้อยกว่า 10
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A9', 'map_position' => '1,1']);
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A10', 'map_position' => '1,2']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.desks.suggest', ['zone_id' => $zone->zone_id]))
            ->assertOk()
            ->assertJson(['desk_number' => 'A11']);
    }

    public function test_desk_suggestion_keeps_number_width_when_crossing_a_decade_boundary(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A09', 'map_position' => '1,1']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.desks.suggest', ['zone_id' => $zone->zone_id]))
            ->assertOk()
            ->assertJson(['desk_number' => 'A10']);
    }

    public function test_desk_grid_position_out_of_range_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.desks.create'))
            ->post(route('admin.desks.store'), [
                'zone_id' => $zone->zone_id,
                'desk_number' => 'X01',
                'map_position' => (DeskGrid::MAX_COLUMN + 1).',1',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertSessionHasErrors('map_position');
    }

    public function test_desk_grid_position_cannot_clash_inside_the_same_zone(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'map_position' => '2,2']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.desks.create'))
            ->post(route('admin.desks.store'), [
                'zone_id' => $zone->zone_id,
                'desk_number' => 'X02',
                'map_position' => '2,2',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertSessionHasErrors('map_position');
    }

    public function test_same_grid_position_is_allowed_in_different_zones(): void
    {
        $admin = Admin::factory()->create();
        $zoneA = Zone::factory()->create();
        $zoneB = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zoneA->zone_id, 'map_position' => '2,2']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.desks.store'), [
                'zone_id' => $zoneB->zone_id,
                'desk_number' => 'B01',
                'map_position' => '2,2',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertRedirect(route('admin.desks.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Desk::where('map_position', '2,2')->count());
    }

    public function test_desk_can_keep_its_own_grid_position_when_updating(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id, 'map_position' => '3,4']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.desks.update', $desk), [
                'zone_id' => $zone->zone_id,
                'desk_number' => $desk->desk_number,
                'map_position' => '3,4',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertRedirect(route('admin.desks.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('3,4', $desk->fresh()->map_position);
    }

    public function test_desk_suggestion_requires_admin(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs(Employee::factory()->create(), 'web')
            ->getJson(route('admin.desks.suggest', ['zone_id' => $zone->zone_id]))
            ->assertRedirect();
    }

    // ------------------------------------------------------------- Employee

    public function test_new_employee_id_uses_year_month_and_sequence_format(): void
    {
        $first = Employee::factory()->create();
        $second = Employee::factory()->create();

        $this->assertSame(11, strlen($first->employee_id));
        $this->assertMatchesRegularExpression(
            '/^EMP\d{4}\d{4}$/',
            $first->employee_id,
            'รหัสต้องเป็น EMP + ปี 2 หลัก + เดือน 2 หลัก + ลำดับ 4 หลัก'
        );
        $this->assertStringStartsWith('EMP'.now()->format('ym'), $first->employee_id);
        $this->assertSame(1, (int) substr($first->employee_id, -4));
        $this->assertSame(2, (int) substr($second->employee_id, -4));
    }

    public function test_next_id_preview_does_not_consume_the_sequence(): void
    {
        $created = Employee::factory()->create();

        $preview = Employee::nextIdPreview();

        $this->assertSame(
            (int) substr($created->employee_id, -4) + 1,
            (int) substr($preview, -4),
            'ตัวอย่างรหัสต้องเป็นลำดับถัดไป'
        );

        $next = Employee::factory()->create();

        $this->assertSame($preview, $next->employee_id);
    }

    public function test_created_employee_must_complete_account_setup(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.employees.store'), [
                'employee_fullname' => 'พนักงานใหม่',
                'employee_tel' => '0812345678',
                'employee_email' => 'new.employee@bs-hybrid.test',
                'employee_role' => Employee::ROLE_EMPLOYEE,
                'employee_status' => Employee::STATUS_ACTIVE,
                'department_id' => $department->department_id,
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHasNoErrors();

        $employee = Employee::where('employee_email', 'new.employee@bs-hybrid.test')->sole();

        $this->assertTrue($employee->first_login, 'พนักงานใหม่ต้องถูกบังคับตั้งค่าบัญชี');
        $this->assertTrue($employee->needsAccountSetup());
    }

    public function test_employee_phone_must_be_exactly_ten_digits_starting_with_zero(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->create();

        $payload = [
            'employee_fullname' => 'ทดสอบเบอร์',
            'employee_email' => 'phone.test@bs-hybrid.test',
            'employee_role' => Employee::ROLE_EMPLOYEE,
            'employee_status' => Employee::STATUS_ACTIVE,
            'department_id' => $department->department_id,
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ];

        // ตัวเลข 10 หลักขึ้นต้นด้วย 0 = ถูกต้อง
        $this->actingAs($admin, 'admin')
            ->post(route('admin.employees.store'), $payload + ['employee_tel' => '0812345678'])
            ->assertSessionHasNoErrors();

        foreach (['812345678', '081234567', '08123456789', '081234567a', '1812345678'] as $invalid) {
            $this->actingAs($admin, 'admin')
                ->from(route('admin.employees.create'))
                ->post(route('admin.employees.store'), $payload + [
                    'employee_email' => 'invalid.'.md5($invalid).'@bs-hybrid.test',
                    'employee_tel' => $invalid,
                ])
                ->assertSessionHasErrors('employee_tel');
        }
    }

    // ------------------------------------------------------- Account setup

    public function test_employee_completing_setup_can_use_the_system_afterwards(): void
    {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create([
            'first_login' => true,
            'employee_password' => 'secret1234',
            'department_id' => $department->department_id,
        ]);

        $this->actingAs($employee, 'web')
            ->put(route('account.setup.update'), [
                'employee_email' => 'ready@bs-hybrid.test',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'employee_avatar' => 'fox',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertFalse($employee->first_login);
        $this->assertFalse($employee->needsAccountSetup());
        $this->assertSame('ready@bs-hybrid.test', $employee->employee_email);
        $this->assertSame('fox', $employee->employee_avatar);
        $this->assertTrue(Hash::check('newpassword123', $employee->employee_password));
    }

    public function test_setup_rejects_unknown_avatar(): void
    {
        $employee = Employee::factory()->create(['first_login' => true]);

        $this->actingAs($employee, 'web')
            ->from(route('account.setup'))
            ->put(route('account.setup.update'), [
                'employee_email' => $employee->employee_email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'employee_avatar' => 'dragon',
            ])
            ->assertSessionHasErrors('employee_avatar');
    }

    public function test_setup_rejects_email_already_used_by_another_employee(): void
    {
        Employee::factory()->email('taken@bs-hybrid.test')->create();
        $employee = Employee::factory()->create(['first_login' => true]);

        $this->actingAs($employee, 'web')
            ->from(route('account.setup'))
            ->put(route('account.setup.update'), [
                'employee_email' => 'taken@bs-hybrid.test',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'employee_avatar' => 'cat',
            ])
            ->assertSessionHasErrors('employee_email');
    }

    public function test_logout_button_leaves_setup_untouched_and_logs_out(): void
    {
        $employee = Employee::factory()->create([
            'first_login' => true,
            'employee_email' => 'still.temporary@bs-hybrid.test',
        ]);

        $this->actingAs($employee, 'web')
            ->put(route('account.setup.update'), ['logout' => 1])
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
        $employee->refresh();
        $this->assertTrue($employee->first_login, 'กดออกจากระบบต้องไม่บังคับตั้งค่าให้เสร็จ');
        $this->assertSame('still.temporary@bs-hybrid.test', $employee->employee_email);
    }

    public function test_setup_page_redirects_when_already_configured(): void
    {
        $employee = Employee::factory()->create(['first_login' => false]);

        $this->actingAs($employee, 'web')
            ->get(route('account.setup'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_administrator_role_employee_gets_setup_modal_on_admin_pages(): void
    {
        $employee = Employee::factory()->create([
            'first_login' => true,
            'employee_role' => 'Administrator',
        ]);

        $this->actingAs($employee, 'web')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('account-setup-title');
    }

    public function test_administrator_role_employee_has_no_setup_modal_after_setup(): void
    {
        $employee = Employee::factory()->create([
            'first_login' => false,
            'employee_role' => 'Administrator',
        ]);

        $this->actingAs($employee, 'web')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('account-setup-title');
    }

    public function test_administrator_role_employee_can_complete_setup(): void
    {
        $employee = Employee::factory()->create([
            'first_login' => true,
            'employee_role' => 'Administrator',
            'employee_password' => 'secret1234',
        ]);

        $this->actingAs($employee, 'web')
            ->put(route('account.setup.update'), [
                'employee_email' => 'admin.employee@bs-hybrid.test',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'employee_avatar' => 'fox',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertFalse($employee->needsAccountSetup());
        $this->assertSame('admin.employee@bs-hybrid.test', $employee->employee_email);
    }

    public function test_avatar_library_has_twenty_animals_with_unique_labels(): void
    {
        $animals = AnimalAvatar::all();

        $this->assertCount(20, $animals);
        $this->assertSame($animals, AnimalAvatar::all(), 'ลำดับต้องคงที่');
        $this->assertCount(20, array_unique(array_column($animals, 'label')));

        foreach ($animals as $key => $animal) {
            $this->assertNotEmpty($animal['label']);
            $this->assertStringContainsString('<svg', AnimalAvatar::svg($key));
        }
    }

    public function test_unknown_avatar_key_falls_back_to_the_default(): void
    {
        $this->assertFalse(AnimalAvatar::isValid('dragon'));
        $this->assertSame(AnimalAvatar::fallback(), (new Employee)->avatarKey());
        $this->assertStringContainsString('<svg', AnimalAvatar::svg('dragon'));
    }

    public function test_id_sequence_stays_eleven_characters(): void
    {
        $this->assertSame(11, IdSequence::WIDTH);
        $this->assertSame(11, strlen(Employee::factory()->create()->employee_id));
    }

    public function test_zone_grid_and_avatar_do_not_break_booking_flow(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create([
            'zone_id' => $zone->zone_id,
            'map_position' => '4,5',
        ]);

        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
        ]);

        $this->assertSame('4,5', $booking->desk->fresh()->map_position);
    }
}
