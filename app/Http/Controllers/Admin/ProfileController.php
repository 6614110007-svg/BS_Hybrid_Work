<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\AnimalAvatar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * โปรไฟล์ผู้ดูแลระบบ
 *
 * แก้ได้เฉพาะ "ชื่อที่แสดง" กับ avatar เท่านั้น
 * ชื่อ-นามสกุลจริง อีเมล บทบาท และสิทธิ์ไม่ให้แก้จากหน้านี้
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.profile', [
            'admin' => Auth::guard('admin')->user(),
            'animals' => AnimalAvatar::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        abort_unless($admin instanceof Admin, 403);

        $validated = $request->validate([
            'admin_display_name' => ['nullable', 'string', 'max:100'],
            'admin_avatar' => ['required', Rule::in(AnimalAvatar::keys())],
        ], [], [
            'admin_display_name' => 'ชื่อที่แสดง',
            'admin_avatar' => 'avatar',
        ]);

        $admin->forceFill([
            'admin_display_name' => $validated['admin_display_name'] !== null
                ? trim($validated['admin_display_name'])
                : null,
            'admin_avatar' => $validated['admin_avatar'],
        ])->save();

        return redirect()->route('admin.profile.edit')
            ->with('status', 'บันทึกโปรไฟล์เรียบร้อยแล้ว');
    }
}