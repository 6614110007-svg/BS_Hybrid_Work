<?php

namespace Tests\Feature\Employee;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Employee;
use App\Support\AnimalAvatar;
use App\Support\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * หน้าโปรไฟล์พนักงาน (/profile)
 *
 * แสดงชื่อ-นามสกุล แผนก รหัสพนักงาน อีเมลเดิม และอีเมลปัจจุบันที่ผูกไว้
 * พร้อมให้เปลี่ยน avatar สัตว์ 20 แบบได้ตลอดเวลา
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_exposes_the_profile_link(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โปรไฟล์ของฉัน')
            ->assertSee(route('profile'), escape: false);
    }

    public function test_profile_page_shows_employee_details(): void
    {
        $department = Department::factory()->create(['department_name' => 'ฝ่ายพัฒนาระบบ']);

        $employee = Employee::factory()->create([
            'employee_fullname' => 'สมชาย ใจดี',
            'department_id' => $department->department_id,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('โปรไฟล์ของฉัน')
            ->assertSee('สมชาย ใจดี')
            ->assertSee('ฝ่ายพัฒนาระบบ')
            ->assertSee($employee->employee_id)
            ->assertSee($employee->employee_tel);
    }

    public function test_profile_shows_original_and_current_bound_email(): void
    {
        $employee = Employee::factory()->create([
            'employee_email' => 'somchai.personal@example.com',
            'employee_original_email' => 'somchai@bs-hybrid.test',
        ]);

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('อีเมลเดิม (ที่บริษัทลงทะเบียน)')
            ->assertSee('somchai@bs-hybrid.test')
            ->assertSee('อีเมลปัจจุบันที่ผูกไว้')
            ->assertSee('somchai.personal@example.com')
            ->assertSee('ต่างจากอีเมลเดิมของบริษัท');
    }

    public function test_profile_marks_email_as_unchanged_when_it_matches_the_original(): void
    {
        $employee = Employee::factory()->create([
            'employee_email' => 'same@bs-hybrid.test',
            'employee_original_email' => 'same@bs-hybrid.test',
        ]);

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('ตรงกับอีเมลเดิมของบริษัท');
    }

    public function test_profile_handles_missing_original_email(): void
    {
        $employee = Employee::factory()->create(['employee_original_email' => null]);

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('ไม่มีข้อมูลเดิม')
            ->assertSee($employee->employee_email);
    }

    public function test_profile_offers_all_twenty_animal_avatars(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('เปลี่ยน avatar');

        foreach (AnimalAvatar::all() as $key => $meta) {
            $this->assertTrue(AnimalAvatar::isValid($key));
        }

        $this->assertCount(20, AnimalAvatar::all());
    }

    public function test_every_avatar_option_is_reachable_and_announced_for_the_live_preview(): void
    {
        $employee = Employee::factory()->create();

        $html = $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->getContent();

        $options = AnimalAvatar::all();

        // ทุกตัวต้องมี data-avatar-option เพื่อให้เขียน browser test ระบุตัวที่จะกดได้
        foreach (array_keys($options) as $key) {
            $this->assertStringContainsString(
                sprintf('data-avatar-option="%s"', $key),
                $html,
                "avatar [{$key}] ต้องมี data-avatar-option"
            );
        }

        $this->assertSame(20, substr_count($html, 'data-avatar-option="'));

        // ทุกตัวต้องมี SVG ในแผนที่สำหรับพรีวิวขนาดใหญ่ ไม่ใช่แค่ตัวที่เลือกอยู่
        foreach (array_keys($options) as $key) {
            $this->assertStringContainsString(
                sprintf('data-avatar-svg="%s"', $key),
                $html,
                "avatar [{$key}] ต้องมี SVG ใน <template data-avatar-svg-map>"
            );
        }

        // ปุ่มเลือกต้องประกาศค่าใหม่ออกไป เพราะ x-model เขียนลง input hidden ตรง ๆ
        // โดยไม่ยิง event 'input' ตามมา (ภาพตัวอย่างจะไม่อัปเดตถ้าขาดส่วนนี้)
        foreach (array_keys($options) as $key) {
            $this->assertStringContainsString(
                sprintf("\$dispatch('avatar-selected', '%s')", $key),
                $html,
                "avatar [{$key}] ต้องยิง event avatar-selected"
            );
        }
    }

    public function test_avatar_preview_lookup_reads_the_svg_inside_the_template(): void
    {
        $employee = Employee::factory()->create();

        $html = $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->getContent();

        // SVG ทั้งหมดอยู่ใน <template> ซึ่ง document.querySelector มองไม่เห็น
        // ต้องค้นผ่าน .content ของ template ไม่งั้นพรีวิวจะไม่เปลี่ยน
        $this->assertStringContainsString('[data-avatar-svg-map]', $html);
        $this->assertStringContainsString('content.querySelector(', $html);
        $this->assertStringNotContainsString(
            'const source = document.querySelector(`[data-avatar-svg=',
            $html,
            'ต้องไม่ค้น SVG จาก document โดยตรง เพราะมองไม่เห็นเนื้อหาใน <template>'
        );
    }

    public function test_employee_can_switch_avatar_anytime(): void
    {
        $employee = Employee::factory()->create(['employee_avatar' => 'cat']);

        $this->actingAs($employee, 'web')
            ->from('/profile')
            ->put('/profile', ['employee_avatar' => 'penguin'])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->assertSame('penguin', $employee->refresh()->employee_avatar);
    }

    public function test_avatar_update_rejects_unknown_key(): void
    {
        $employee = Employee::factory()->create(['employee_avatar' => 'cat']);

        $this->actingAs($employee, 'web')
            ->from('/profile')
            ->put('/profile', ['employee_avatar' => 'dragon'])
            ->assertSessionHasErrors('employee_avatar');

        $this->assertSame('cat', $employee->refresh()->employee_avatar);
    }

    public function test_avatar_update_requires_a_value(): void
    {
        $employee = Employee::factory()->create(['employee_avatar' => 'cat']);

        $this->actingAs($employee, 'web')
            ->from('/profile')
            ->put('/profile', [])
            ->assertSessionHasErrors('employee_avatar');
    }

    public function test_avatar_update_does_not_touch_other_fields(): void
    {
        $employee = Employee::factory()->create([
            'employee_avatar' => 'cat',
            'employee_fullname' => 'สมชาย ใจดี',
            'employee_email' => 'keep@bs-hybrid.test',
        ]);

        $this->actingAs($employee, 'web')
            ->put('/profile', [
                'employee_avatar' => 'fox',
                'employee_fullname' => 'ถูกแก้ไขไม่ได้',
                'employee_email' => 'hacked@evil.test',
            ])
            ->assertRedirect(route('profile'));

        $employee->refresh();

        $this->assertSame('fox', $employee->employee_avatar);
        $this->assertSame('สมชาย ใจดี', $employee->employee_fullname);
        $this->assertSame('keep@bs-hybrid.test', $employee->employee_email);
    }

    public function test_profile_shows_booking_statistics(): void
    {
        $employee = Employee::factory()->create();
        $slot = TimeSlot::find('Full Day');

        Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'booking_status' => Booking::STATUS_RESERVED,
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        Booking::factory()->completed()->create([
            'employee_id' => $employee->employee_id,
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/profile')
            ->assertOk()
            ->assertSee('จองทั้งหมด')
            ->assertSee('รอเช็คอิน')
            ->assertSee('ใช้งานเสร็จ');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_profile_is_employee_only(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/profile')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_administrator_role_employee_is_sent_to_admin_area(): void
    {
        $manager = Employee::factory()->administrator()->create();

        $this->actingAs($manager, 'web')
            ->get('/profile')
            ->assertRedirect(route('admin.dashboard'));
    }
}
