<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeSeatMapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TimeSlot $slot;
    private Zone $zone;
    private Desk $desk;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->slot = TimeSlot::create([
            'code' => 'AM',
            'name' => 'ช่วงเช้า 09:00-13:00',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->zone = Zone::create(['code' => 'Z-A', 'name' => 'โซน A']);
        $this->desk = Desk::create([
            'zone_id' => $this->zone->id,
            'code' => 'A-01',
            'label' => 'โต๊ะ A1',
            'x' => 1,
            'y' => 1,
        ]);
    }

    private function booking(string $status = Booking::STATUS_CONFIRMED, ?string $date = null, ?array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->user->id,
            'desk_id' => $this->desk->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $date ?? today()->toDateString(),
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(3),
            'status' => $status,
        ], $overrides));
    }

    public function test_employee_can_view_seat_map_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('แผนผังที่นั่ง');
        $response->assertSee('A-01');
    }

    public function test_employee_can_view_status_api(): void
    {
        $this->booking();

        $response = $this->actingAs($this->user)
            ->getJson('/seatmap/status?date='.today()->toDateString().'&slot='.$this->slot->id);

        $response->assertOk()
            ->assertJsonPath('desks.0.state', 'my_booked')
            ->assertJsonPath('desks.0.booking_id', Booking::first()->id);
    }

    public function test_desk_shows_available_without_booking(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/seatmap/status?date='.today()->toDateString().'&slot='.$this->slot->id);

        $response->assertOk()->assertJsonPath('desks.0.state', 'available');
    }

    public function test_desk_in_maintenance_is_not_bookable(): void
    {
        $this->desk->update(['is_maintenance' => true]);

        $response = $this->actingAs($this->user)
            ->getJson('/seatmap/status?date='.today()->toDateString().'&slot='.$this->slot->id);

        $response->assertOk()->assertJsonPath('desks.0.state', 'maintenance');
    }

    public function test_employee_can_book_a_desk(): void
    {
        $response = $this->actingAs($this->user)->post('/bookings', [
            'desk_id' => $this->desk->id,
            'booking_date' => today()->toDateString(),
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->user->id,
            'desk_id' => $this->desk->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $this->assertSame(1, Booking::count());
    }

    public function test_desk_cannot_be_booked_twice_in_same_slot(): void
    {
        $this->booking();
        $second = User::factory()->create();

        $response = $this->actingAs($second)->post('/bookings', [
            'desk_id' => $this->desk->id,
            'booking_date' => today()->toDateString(),
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertSessionHasErrors('desk_id');
        $this->assertSame(1, Booking::count());
    }

    public function test_user_cannot_book_second_desk_in_same_slot(): void
    {
        $this->booking();
        $otherDesk = Desk::create(['zone_id' => $this->zone->id, 'code' => 'A-02', 'x' => 2, 'y' => 1]);

        $response = $this->actingAs($this->user)->post('/bookings', [
            'desk_id' => $otherDesk->id,
            'booking_date' => today()->toDateString(),
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertSessionHasErrors('desk_id');
        $this->assertSame(1, Booking::count());
    }

    public function test_past_date_cannot_be_booked(): void
    {
        $response = $this->actingAs($this->user)->post('/bookings', [
            'desk_id' => $this->desk->id,
            'booking_date' => today()->subDay()->toDateString(),
            'time_slot_id' => $this->slot->id,
        ]);

        $response->assertSessionHasErrors('booking_date');
        $this->assertSame(0, Booking::count());
    }

    public function test_user_can_cancel_own_confirmed_booking(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->user)->delete("/bookings/{$booking->id}");

        $response->assertRedirect();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(Booking::CANCEL_BY_USER, $booking->fresh()->cancel_reason);
    }

    public function test_user_cannot_cancel_other_peoples_booking(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create();

        $response = $this->actingAs($other)->delete("/bookings/{$booking->id}");

        $response->assertForbidden();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_checkin_screen_renders_inside_window(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->user)->get("/bookings/{$booking->id}/checkin");

        $response->assertOk();
        $response->assertSee('โจทย์ยืนยันตัวตนประจำวันนี้');
    }

    public function test_checkin_rejected_too_early(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, null, [
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(6),
        ]);

        $response = $this->actingAs($this->user)->get("/bookings/{$booking->id}/checkin");

        $response->assertRedirect(route('dashboard'));
    }

    public function test_checkin_store_uploads_photo_and_marks_checked_in(): void
    {
        Http::fake([
            '*/storage/v1/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        $booking = $this->booking();
        $file = UploadedFile::fake()->createWithContent('selfie.png', base64_decode(self::PNG), 'image/png');

        $response = $this->actingAs($this->user)->post("/bookings/{$booking->id}/checkin", [
            'photo' => $file,
        ]);

        $response->assertRedirect(route('dashboard'));
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->status);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertNotNull($booking->checkin_photo_path);
        $this->assertStringContainsString('checkins/u'.$booking->user_id, $booking->checkin_photo_path);
        $this->assertNotEmpty($booking->checkin_challenge);
    }

    public function test_checkin_store_expires_late_booking(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, null, [
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)->post("/bookings/{$booking->id}/checkin", [
            'photo' => UploadedFile::fake()->createWithContent('selfie.png', base64_decode(self::PNG), 'image/png'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->status);
    }

    public function test_employee_cannot_checkin_someone_elses_booking(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create();

        $response = $this->actingAs($other)->get("/bookings/{$booking->id}/checkin");

        $response->assertForbidden();
    }

    public function test_checkout_marks_booking_checked_out(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_IN);

        $response = $this->actingAs($this->user)->post("/bookings/{$booking->id}/checkout");

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CHECKED_OUT, $booking->status);
        $this->assertNotNull($booking->checked_out_at);
    }

    public function test_status_api_reflects_checkin(): void
    {
        $this->booking(Booking::STATUS_CHECKED_IN);

        $response = $this->actingAs($this->user)
            ->getJson('/seatmap/status?date='.today()->toDateString().'&slot='.$this->slot->id);

        $response->assertOk()->assertJsonPath('desks.0.state', 'my_in_use');
    }

    public function test_auto_cancel_command_expires_late_bookings_and_notifies(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, null, [
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->status);
        $this->assertSame(Booking::CANCEL_AUTO_LATE, $booking->fresh()->cancel_reason);
        $this->assertSame(1, $this->user->notifications()->count());
    }

    public function test_auto_cancel_command_auto_checkout_stale_checked_in(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_IN, null, [
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHours(2),
            'checked_in_at' => now()->subHours(4),
        ]);

        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CHECKED_OUT, $booking->status);
        $this->assertNotNull($booking->checked_out_at);
    }
}