<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\AnimalAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_profile_page(): void
    {
        $admin = Admin::factory()->create(['admin_fullname' => 'สมชาย ใจดี']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('สมชาย ใจดี')
            ->assertSee('ผู้ดูแลระบบ')
            ->assertSee('บทบาทนี้แก้ไขไม่ได้');
    }

    public function test_admin_can_update_display_name_and_avatar(): void
    {
        $admin = Admin::factory()->create(['admin_fullname' => 'สมชาย ใจดี']);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.profile.update'), [
                'admin_display_name' => 'ผู้ดูแลระบบส่วนกลาง',
                'admin_avatar' => 'owl',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $admin->refresh();

        $this->assertSame('ผู้ดูแลระบบส่วนกลาง', $admin->admin_display_name);
        $this->assertSame('ผู้ดูแลระบบส่วนกลาง', $admin->displayName());
        $this->assertSame('ผู้ดูแลระบบส่วนกลาง', $admin->actorName());
        $this->assertSame('owl', $admin->avatarKey());
    }

    public function test_display_name_falls_back_to_real_fullname_when_blank(): void
    {
        $admin = Admin::factory()->create([
            'admin_fullname' => 'สมชาย ใจดี',
            'admin_display_name' => '   ',
        ]);

        $this->assertSame('สมชาย ใจดี', $admin->displayName());
    }

    public function test_profile_rejects_unknown_avatar(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.update'), [
                'admin_display_name' => 'ชื่อใหม่',
                'admin_avatar' => 'unicorn',
            ])
            ->assertSessionHasErrors('admin_avatar');
    }

    public function test_profile_cannot_change_identity_or_role(): void
    {
        $admin = Admin::factory()->create([
            'admin_fullname' => 'สมชาย ใจดี',
            'admin_email' => 'original@bs-hybrid.test',
        ]);

        // ส่งฟิลด์อื่นแนบมาด้วย เพื่อยืนยันว่าถูกเมิน ไม่ใช่ถูกบันทึก
        $this->actingAs($admin, 'admin')
            ->put(route('admin.profile.update'), [
                'admin_display_name' => 'ชื่อที่แสดง',
                'admin_avatar' => 'cat',
                'admin_fullname' => 'แฮกเกอร์',
                'admin_email' => 'hacked@evil.test',
                'admin_status' => 'Inactive',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $admin->refresh();

        $this->assertSame('สมชาย ใจดี', $admin->admin_fullname);
        $this->assertSame('original@bs-hybrid.test', $admin->admin_email);
        $this->assertSame(Admin::STATUS_ACTIVE, $admin->admin_status);
        $this->assertTrue($admin->isAdministrator(), 'บทบาทผู้ดูแลระบบแก้ไขไม่ได้');
        $this->assertSame('ผู้ดูแลระบบ', $admin->roleLabel());
    }

    public function test_profile_requires_admin_guard(): void
    {
        $this->get(route('admin.profile.edit'))->assertRedirect(route('login'));
    }

    public function test_avatar_appears_in_admin_header(): void
    {
        $admin = Admin::factory()->create(['admin_display_name' => 'ชื่อที่แสดง']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('แก้ไขโปรไฟล์ผู้ดูแลระบบ')
            ->assertSee(AnimalAvatar::label($admin->avatarKey()), false);
    }
}