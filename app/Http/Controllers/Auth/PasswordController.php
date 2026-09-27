<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CurrentActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * เปลี่ยนรหัสผ่านของผู้ใช้ที่ล็อกอินอยู่ (ใช้ได้ทั้งผู้ดูแลระบบและพนักงาน)
 */
class PasswordController extends Controller
{
    public function edit(CurrentActor $current): View
    {
        return view('auth.edit-password', [
            'actor' => $current->actor(),
        ]);
    }

    public function update(Request $request, CurrentActor $current): RedirectResponse
    {
        $actor = $current->actor();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:'.$actor->authGuard()],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $actor->forceFill([$actor->getAuthPasswordName() => $validated['password']])->save();

        return redirect()
            ->route($actor->homeRoute())
            ->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
