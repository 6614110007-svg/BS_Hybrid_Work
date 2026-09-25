<?php

declare(strict_types=1);

/**
 * Employee-side real HTTP E2E against a local `php artisan serve`.
 * Uses Guzzle with a persistent cookie jar to exercise the full flow,
 * including a genuine multipart selfie upload to Supabase Storage.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Artisan;

$base = getenv('E2E_BASE_URL') ?: 'http://127.0.0.1:8010';
$cacert = __DIR__.'/../storage/app/certs/cacert.pem';
$jar = new CookieJar();
$client = new Client([
    'base_uri' => $base,
    'cookies' => $jar,
    'http_errors' => false,
    'verify' => $cacert,
    'allow_redirects' => ['track_redirects' => true, 'max' => 5],
]);

function fail(string $msg): never
{
    fwrite(STDERR, "FAIL $msg\n");
    exit(1);
}

function ok(string $msg): void
{
    echo "OK $msg\n";
}

function redirectPaths($response): array
{
    $paths = [];
    foreach ($response->getHeader('X-Guzzle-Redirect-History') as $u) {
        $path = parse_url($u, PHP_URL_PATH);
        if ($path !== null) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function redirectedTo($response, string $path): bool
{
    return in_array($path, redirectPaths($response), true);
}

function fetchToken(Client $client, string $path): string
{
    $r = $client->get($path);
    if ($r->getStatusCode() !== 200 || ! preg_match('/name="_token"\s+value="([^"]+)"/', (string) $r->getBody(), $m)) {
        fail("no token on $path (status {$r->getStatusCode()})");
    }

    return $m[1];
}

// 1) login (temp password on a fresh DB, or the password set by a previous run)
$token = fetchToken($client, '/login');
$r = $client->post('/login', ['form_params' => ['_token' => $token, 'email' => 'somchai@deskbooking.local', 'password' => 'TempPass123!']]);
$history = $r->getHeader('X-Guzzle-Redirect-History');
$alreadyChanged = false;

if (redirectedTo($r, '/password/change')) {
    // 2) forced password change (first run on a freshly seeded DB)
    $token = fetchToken($client, '/password/change');
    $r = $client->post('/password/change', ['form_params' => ['_token' => $token, 'current_password' => 'TempPass123!', 'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!']]);
    if (! redirectedTo($r, '/dashboard')) {
        fail('password change did not redirect to /dashboard');
    }
    ok('login + forced password change -> /dashboard');
} elseif (redirectedTo($r, '/dashboard')) {
    ok('login (password already set by previous run) -> /dashboard');
} else {
    // try the password set by an earlier run
    $token = fetchToken($client, '/login');
    $r = $client->post('/login', ['form_params' => ['_token' => $token, 'email' => 'somchai@deskbooking.local', 'password' => 'NewPass123!']]);
    if (! redirectedTo($r, '/dashboard')) {
        fail('login failed with both temp and current passwords');
    }
    ok('login -> /dashboard');
}

// 3) seat map renders
$dash = (string) $client->get('/dashboard')->getBody();
if (! str_contains($dash, 'seatMap(') || ! str_contains($dash, 'A-01')) {
    fail('seat map page missing seatMap()/A-01');
}
ok('seat map dashboard renders with desks');

// 4) status API
$today = now()->format('Y-m-d');
$status = json_decode((string) $client->get("/seatmap/status?date=$today&slot=1")->getBody(), true);
if (count($status['desks'] ?? []) !== 18) {
    fail('status API expected 18 desks');
}
ok('status API returns 18 desks (desk0='.($status['desks'][0]['state'] ?? '?').')');

// 5) real booking record with an open check-in window right now
$owner = App\Models\User::where('email', 'somchai@deskbooking.local')->value('id');
App\Models\Booking::where('user_id', $owner)->delete();
$booking = App\Models\Booking::create([
    'user_id' => $owner,
    'desk_id' => 1,
    'time_slot_id' => 1,
    'booking_date' => $today,
    'starts_at' => now()->subMinutes(10),
    'ends_at' => now()->addHours(2),
    'status' => App\Models\Booking::STATUS_CONFIRMED,
]);
ok("real booking created id={$booking->id}");

// 6) check-in page (camera + challenge)
$ci = (string) $client->get("/bookings/{$booking->id}/checkin")->getBody();
if (! str_contains($ci, 'LIVE') || ! str_contains($ci, 'A-01')) {
    fail('checkin page missing LIVE/A-01');
}
ok('checkin page renders camera/challenge');

// 7) real multipart selfie upload -> Supabase
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$tmp = sys_get_temp_dir().'/e2e_selfie.png';
file_put_contents($tmp, $png);
$token = fetchToken($client, "/bookings/{$booking->id}/checkin");
$r = $client->post("/bookings/{$booking->id}/checkin", ['multipart' => [
    ['name' => '_token', 'contents' => $token],
    ['name' => 'photo', 'contents' => fopen($tmp, 'r'), 'filename' => 'selfie.png', 'headers' => ['Content-Type' => 'image/png']],
]]);
if ($r->getStatusCode() >= 400) {
    fail('checkin upload failed (status '.$r->getStatusCode().') '.substr((string) $r->getBody(), 0, 400));
}
$booking->refresh();
if ($booking->status !== App\Models\Booking::STATUS_CHECKED_IN || ! $booking->checkin_photo_path || ! $booking->checkin_challenge) {
    fail('booking not marked checked_in / no photo path');
}
ok('selfie uploaded, checked_in ('.$booking->checkin_photo_path.')');

// 8) signed photo URL resolves (private bucket)
$r = $client->get("/bookings/{$booking->id}/photo", ['allow_redirects' => false]);
if ($r->getStatusCode() !== 302 || ! preg_match('#https://.+#', $r->getHeaderLine('Location'), $m)) {
    fail('photo did not redirect to signed URL ('.$r->getStatusCode().')');
}
$img = $client->get($m[0]);
if ($img->getStatusCode() !== 200 || $img->getBody()->getSize() <= 0) {
    fail('signed URL did not return image');
}
ok('signed photo URL returns '.$img->getBody()->getSize().' bytes');

// 9) checkout
$token = fetchToken($client, '/dashboard');
$r = $client->post("/bookings/{$booking->id}/checkout", ['form_params' => ['_token' => $token]]);
$booking->refresh();
if ($booking->status !== App\Models\Booking::STATUS_CHECKED_OUT) {
    fail('checkout did not mark checked_out');
}
ok('checkout -> checked_out');

// 10) my bookings page lists the desk
$mine = (string) $client->get('/bookings')->getBody();
if (! str_contains($mine, 'A-01')) {
    fail('my bookings page missing A-01');
}
ok('my bookings page renders');

// 11) auto-cancel scheduler command runs against real DB
$exit = Artisan::call('bookings:auto-cancel');
ok('auto-cancel command exit='.$exit);

// 12) cleanup: remove uploaded object + booking
(new App\Services\SupabaseStorage())->delete($booking->checkin_photo_path);
$booking->delete();
ok('cleanup done (photo object + booking deleted)');

echo "ALL EMPLOYEE E2E CHECKS PASSED\n";
exit(0);