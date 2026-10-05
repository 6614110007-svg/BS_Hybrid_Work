<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ระบบกู้คืนรหัสผ่าน (ลืมรหัสผ่าน?)
 *
 * ข้อจำกัดตามขอบเขตของระบบ: ยังไม่มีช่องทางส่งอีเมล (ไม่มี SMTP)
 * หน้านี้จึงทำหน้าที่ "ตรวจสอบความถูกต้อง" ของอีเมลที่ผูกไว้ตอน First-Login และ
 * แสดงขั้นตอนที่ต้องทำต่อ (ติดต่อผู้ดูแลระบบเพื่อให้รีเซ็ตรหัสผ่านชั่วคราว)
 *
 * ไม่รีเซ็ตรหัสผ่านให้เอง และไม่บอกว่าอีเมลนั้นมีอยู่จริงหรือไม่ในกรณีที่ไม่พบ
 * เพื่อไม่ให้ใครใช้หน้านี้สำรวจอีเมลของพนักงานในระบบ (ข้อควรระวังสำคัญของหน้ากู้คืนรหัสผ่าน)
 */
class PasswordRecoveryController extends Controller
{
    /** จำกัดจำนวนครั้งที่ขอกู้คืนรหัสผ่านต่อหนึ่งอีเมล */
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recovery_email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'recovery_email.required' => 'กรุณากรอกอีเมลที่ใช้เข้าสู่ระบบ',
            'recovery_email.email' => 'รูปแบบอีเมลไม่ถูกต้อง',
        ]);

        $email = Str::lower(trim($validated['recovery_email']));

        $this->ensureIsNotRateLimited($request, $email);

        $this->findAccountKind($request, $email);

        // ทุกกรณี (พบหรือไม่พบบัญชี) ตอบกลับข้อความเดียวกัน ไม่เปิดเผยว่าอีเมลนี้มีในระบบหรือไม่
        return to_route('password.recovery')
            ->with('recovery_notice', 'หากอีเมลนี้ผูกไว้กับบัญชีในระบบ ทางเราจะแจ้งให้ผู้ดูแลระบบติดต่อกลับไปยังอีเมลนี้');
    }

    /**
     * ตรวจสอบว่าอีเมลผูกกับบัญชีพนักงานหรือผู้ดูแลระบบ แล้วบันทึกความพยายามไว้กับ RateLimiter
     *
     * @return 'employee'|'admin'|null
     */
    private function findAccountKind(Request $request, string $email): ?string
    {
        $employee = Employee::query()
            ->where(function (Builder $query) use ($email) {
                $query->whereRaw('LOWER(employee_email) = ?', [$email])
                    ->orWhereRaw('LOWER(employee_original_email) = ?', [$email]);
            })
            ->exists();

        if ($employee) {
            RateLimiter::clear($this->throttleKey($request, $email));

            return 'employee';
        }

        $admin = Admin::query()->whereRaw('LOWER(admin_email) = ?', [$email])->exists();

        if ($admin) {
            RateLimiter::clear($this->throttleKey($request, $email));

            return 'admin';
        }

        RateLimiter::hit($this->throttleKey($request, $email));

        return null;
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $email), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $email));

        throw ValidationException::withMessages([
            'recovery_email' => "ขอกู้คืนรหัสผ่านบ่อยเกินไป กรุณารอ {$seconds} วินาที แล้วลองใหม่อีกครั้ง",
        ]);
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'password-recovery:'.Str::transliterate($email).'|'.$request->ip();
    }
}