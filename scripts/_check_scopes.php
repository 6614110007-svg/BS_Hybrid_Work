<?php

use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Zone;

$required = [
    Department::class => ['active'],
    Zone::class => ['active', 'ordered'],
    Desk::class => ['active', 'maintenance'],
    TimeSlot::class => ['active', 'ordered'],
    Booking::class => ['active', 'forDate'],
];

$models = [
    Department::class => new Department(),
    Zone::class => new Zone(),
    Desk::class => new Desk(),
    TimeSlot::class => new TimeSlot(),
    Booking::class => new Booking(),
];

$allOk = true     ;
foreach ($required as $class => $scopes) {
    $m = $models[$class];
    foreach ($scopes as $scope) {
        $method = 'scope'.ucfirst($scope);
        $ok = method_exists($m, $method);
        if (!$ok) {
            $allOk = false;
            echo "[MISSING] {$class}::{$method}\n";
        } else {
            echo "[OK] {$class}::{$method}\n";
        }
    }
}

echo $allOk ? "\nALL SCOPES PRESENT\n" : "\nSOME SCOPES MISSING\n";
exit($allOk ? 0 : 1);
