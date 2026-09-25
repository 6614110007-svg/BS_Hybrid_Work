<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\Zone;
use App\Services\Analytics;
use Carbon\Carbon;

$analytics = new Analytics();
$today = Carbon::today();
$passes = [];
$fails = [];

function check(string $label, bool $ok, array $extra = []): void
{
    global $passes, $fails;
    ($ok ? $passes : $fails)[] = $label;
    echo ($ok ? '  [OK]   ' : '  [FAIL] ').$label
        .(count($extra) ? '  '.json_encode($extra, JSON_UNESCAPED_UNICODE) : '').PHP_EOL;
}

echo "Analytics smoke against real seeded DB (read-only)".PHP_EOL;

// 1) liveCounts
try {
    $live = $analytics->liveCounts();
    check('liveCounts()/is_array', is_array($live), ['keys' => array_keys($live)]);
} catch (\Throwable $e) {
    check('liveCounts()/is_array', false, ['err' => $e->getMessage()]);
}

// 2) zoneOccupancy
try {
    $zones = $analytics->zoneOccupancy($today->toDateString());
    check('zoneOccupancy()>=1 zone', is_array($zones) && count($zones) > 0, ['count' => is_countable($zones) ? count($zones) : '?']);
    $first = is_array($zones) && count($zones) ? $zones[0] : null;
    check('zoneOccupancy row shape slots[]', is_array($first) && isset($first['slots']) && is_array($first['slots']), ['keys' => is_array($first) ? array_keys($first) : null]);
} catch (\Throwable $e) {
    check('zoneOccupancy()', false, ['err' => $e->getMessage()]);
}

// 3) dailyStats zero-filled over 7 days
try {
    $daily = $analytics->dailyStats($today->copy()->subDays(6)->toDateString(), $today->toDateString());
    check('dailyStats() has 7 rows', $daily->count() === 7, ['count' => $daily->count()]);
    check('dailyStats rows have dates', $daily->count() && isset($daily->first()['date']), ['sample' => optional(collect($daily)->first())]);
} catch (\Throwable $e) {
    check('dailyStats()', false, ['err' => $e->getMessage()]);
}

// 4) monthlyStats
try {
    $monthly = $analytics->monthlyStats($today->copy()->subDays(70)->toDateString(), $today->toDateString());
    check('monthlyStats() non-empty', $monthly->count() > 0, ['count' => $monthly->count()]);
} catch (\Throwable $e) {
    check('monthlyStats()', false, ['err' => $e->getMessage()]);
}

// 5) recentActivity
try {
    $activity = $analytics->recentActivity(5);
    check('recentActivity() <=5', $activity->count() <= 5, ['count' => $activity->count()]);
} catch (\Throwable $e) {
    check('recentActivity()', false, ['err' => $e->getMessage()]);
}

echo PHP_EOL.'RESULT: '.count($passes).' passed, '.count($fails).' FAILED'.PHP_EOL;
exit(count($fails) === 0 ? 0 : 1);
