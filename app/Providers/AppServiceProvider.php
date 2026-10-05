<?php

namespace App\Providers;

use App\Models\Employee;
use App\Services\CurrentActor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentActor::class);
    }

    public function boot(): void
    {
        // ทุก view ใช้ $actor (ผู้ดูแลระบบหรือพนักงาน) ได้โดยไม่ต้องเช็ค guard เอง
        View::composer('*', function ($view) {
            $current = app(CurrentActor::class);
            $actor = $current->actor();

            $view->with([
                'actor' => $actor,
                'actorIsAdmin' => $current->isAdministrator(),
                // พนักงานที่ยังต้องตั้งค่าบัญชีครั้งแรก ต้องถูกบังคับให้ตั้งค่าก่อนใช้งาน
                'actorNeedsAccountSetup' => $actor instanceof Employee && $actor->needsAccountSetup(),
            ]);
        });
    }
}
