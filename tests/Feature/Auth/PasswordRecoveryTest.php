<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ลืมรหัสผ่าน (Password Recovery)
 *
 * ระบบไม่มีช่องทางส่งอีเมล (ไม่มี SMTP) จึงตรวจความถูกต้องของอีเมลที่ผูกไว้
 * แล้วแนะนำให้ติดต่อผู้ดูแลระบบเพื่อรีเซ็ตรหัสผ่านชั่วคราว
 */
class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_a_visible_forgot_password_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('ลืมรหัสผ่าน?')
            ->assertSee('data-recovery-open', escape: false)
            ->assertSee('passwordRecovery()', escape: false);
    }

    public function test_recovery_page_renders(): void
    {
        $this->get('/password/recovery')
            ->assertOk()
            ->assertSee('ลืมรหัสผ่าน')
            ->assertSee('อีเมลที่ใช้เข้าสู่ระบบ');
    }

    public function test_known_employee_email_gets_instructions(): void
    {
        $employee = Employee::factory()->email('known@bs-hybrid.test')->create();

        $this->post('/password/recovery', ['recovery_email' => 'known@bs-hybrid.test'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice');

        $this->assertSame('known@bs-hybrid.test', $employee->employee_email);
    }

    public function test_bound_email_from_first_login_is_accepted(): void
    {
        // พนักงานผูกอีเมลใหม่ตอน First-Login ต้องใช้อีเมลปัจจุบันได้
        Employee::factory()->create([
            'employee_email' => 'bound@example.com',
            'employee_original_email' => 'work@bs-hybrid.test',
        ]);

        $this->post('/password/recovery', ['recovery_email' => 'bound@example.com'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice');
    }

    public function test_known_admin_email_is_accepted(): void
    {
        Admin::factory()->create(['admin_email' => 'boss@bs-hybrid.test']);

        $this->post('/password/recovery', ['recovery_email' => 'boss@bs-hybrid.test'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice');
    }

    public function test_unknown_email_does_not_reveal_that_it_is_missing(): void
    {
        $this->post('/password/recovery', ['recovery_email' => 'nobody@bs-hybrid.test'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice')
            ->assertSessionMissing('errors');
    }

    public function test_email_matching_is_case_insensitive(): void
    {
        Employee::factory()->email('Mixed.Case@bs-hybrid.test')->create();

        $this->post('/password/recovery', ['recovery_email' => 'mixed.case@bs-hybrid.test'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice');
    }

    public function test_email_is_required(): void
    {
        $this->post('/password/recovery', ['recovery_email' => ''])
            ->assertSessionHasErrors('recovery_email');
    }

    public function test_email_format_is_validated(): void
    {
        $this->post('/password/recovery', ['recovery_email' => 'not-an-email'])
            ->assertSessionHasErrors('recovery_email');
    }

    public function test_recovery_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/password/recovery', ['recovery_email' => 'nobody@bs-hybrid.test']);
        }

        $this->post('/password/recovery', ['recovery_email' => 'nobody@bs-hybrid.test'])
            ->assertSessionHasErrors('recovery_email');

        $this->assertTrue(
            RateLimiter::tooManyAttempts(
                'password-recovery:'.Str::transliterate('nobody@bs-hybrid.test').'|127.0.0.1',
                5,
            ),
        );
    }

    public function test_success_clears_the_rate_limit(): void
    {
        $employee = Employee::factory()->email('retry@bs-hybrid.test')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/password/recovery', ['recovery_email' => 'retry@bs-hybrid.test']);
        }

        $this->post('/password/recovery', ['recovery_email' => 'retry@bs-hybrid.test'])
            ->assertRedirect(route('password.recovery'))
            ->assertSessionHas('recovery_notice');

        $this->assertSame('retry@bs-hybrid.test', $employee->employee_email);
    }

    public function test_recovery_never_changes_the_password(): void
    {
        $before = Employee::factory()->email('safe@bs-hybrid.test')->create();

        $passwordBefore = $before->employee_password;

        $this->post('/password/recovery', ['recovery_email' => 'safe@bs-hybrid.test'])
            ->assertSessionHas('recovery_notice');

        $this->assertSame($passwordBefore, $before->refresh()->employee_password);
    }

    public function test_authenticated_users_are_redirected_away_from_recovery(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/password/recovery')
            ->assertRedirect(route('dashboard'));
    }

    public function test_recovery_instructions_appear_in_the_modal(): void
    {
        $this->post('/password/recovery', ['recovery_email' => 'anyone@bs-hybrid.test'])
            ->assertSessionHas('recovery_notice');

        $this->followingRedirects()
            ->post('/password/recovery', ['recovery_email' => 'anyone@bs-hybrid.test'])
            ->assertOk()
            ->assertSee('ติดต่อผู้ดูแลระบบของหน่วยงาน เพื่อขอรีเซ็ตรหัสผ่านชั่วคราว');
    }

    public function test_failed_login_does_not_open_the_recovery_modal(): void
    {
        // error ของฟอร์มเข้าสู่ระบบ (key 'email') ต้องไม่ทำให้ modal ลืมรหัสผ่านเปิดเอง
        $this->from('/login')
            ->post('/login', ['email' => '', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('recovery_notice');

        $html = $this->get('/login')->getContent();

        // ฟอร์มเข้าสู่ระบบยังคงแสดง error ของตัวเอง แต่ modal ลืมรหัสผ่านต้องไม่เปิด
        $this->assertStringContainsString('ลืมรหัสผ่าน?', $html);
        $this->assertStringContainsString('open: false', $html);
        $this->assertStringNotContainsString('open: true', $html);
    }

    public function test_login_and_recovery_errors_are_reported_independently(): void
    {
        $html = $this->from('/login')
            ->followingRedirects()
            ->post('/password/recovery', ['recovery_email' => 'not-an-email'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('open: true', $html);
        $this->assertStringContainsString('รูปแบบอีเมลไม่ถูกต้อง', $html);
    }
}
