<?php

namespace App\Http\Middleware;

use App\Services\CurrentActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * บัญชีถูกระงับการใช้งาน (admin_status / employee_status = 'Inactive') -> ออกจากระบบทันที
 */
class EnsureActorActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $current = app(CurrentActor::class);
        $actor = $current->actor();

        if ($actor && ! $actor->isActive()) {
            $current->forget();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
            ]);
        }

        return $next($request);
    }
}
