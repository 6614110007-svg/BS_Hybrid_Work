<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        $user->password = $request->string('password');
        $user->must_change_password = false;
        $user->save();

        $request->session()->regenerate();
        $request->session()->remove('password_hash_web');

        return redirect()
            ->route($user->isAdmin() ? 'admin.dashboard' : 'dashboard')
            ->with('status', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}