<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\CurrentActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * ล็อกอิน แล้ว redirect ตามสิทธิ์
     *   - ผู้ดูแลระบบ -> /admin/dashboard
     *   - พนักงาน      -> /dashboard (ค้นหาและจองโต๊ะ)
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $actor = $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route($actor->homeRoute(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, CurrentActor $current): RedirectResponse
    {
        $current->forget();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
