<?php

namespace App\Http\Requests\Auth;

use App\Models\Actor;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ตรวจสอบข้อมูลเข้าสู่ระบบ โดยลอง ตาราง admin ก่อน แล้วจึง ตาราง employee
 */
class LoginRequest extends FormRequest
{
    /**
     * guard => คอลัมน์อีเมลที่ใช้ค้นหา
     *
     * @var array<string, string>
     */
    private const LOOKUP = [
        'admin' => 'admin_email',
        'web' => 'employee_email',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * ล็อกอินแล้วคืนผู้ใช้ที่ authenticate สำเร็จ
     *
     * @throws ValidationException
     */
    public function authenticate(): Actor
    {
        $this->ensureIsNotRateLimited();

        $email = $this->string('email')->toString();
        $password = $this->string('password')->toString();

        foreach (self::LOOKUP as $guard => $column) {
            if (! Auth::guard($guard)->attempt([$column => $email, 'password' => $password])) {
                continue;
            }

            /** @var Actor $actor */
            $actor = Auth::guard($guard)->user();

            if (! $actor->isActive()) {
                Auth::guard($guard)->logout();

                throw ValidationException::withMessages([
                    'email' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                ]);
            }

            RateLimiter::clear($this->throttleKey());

            return $actor;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
