<?php

namespace App\Providers;

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

            $view->with([
                'actor' => $current->actor(),
                'actorIsAdmin' => $current->isAdministrator(),
            ]);
        });
    }
}
