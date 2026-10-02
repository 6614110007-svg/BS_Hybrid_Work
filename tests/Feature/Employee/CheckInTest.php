<?php

namespace Tests\Feature\Employee;

use App\Http\Controllers\CheckInController;
use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\SelfieChallenge;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_page_is_available_inside_the_window(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertOk()
            ->assertSee('ถ่ายรูปเซลฟี่');
    }

    public function test_checkin_is_rejected_before_the_window_opens(): void
    {
        [$employee, $booking] = $this->reservation(Carbon::tomorrow());

        $this->travelTo(now());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');
    }

    public function test_checkin_page_expires_booking_past_the_deadline(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertRedirect('/bookings')
            ->assertSessionHas('error', CheckInController::EXPIRED_MESSAGE);

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_AVAILABLE, $booking->desk->fresh()->desk_status);
    }

    public function test_checkin_page_redirects_after_booking_is_expired(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertRedirect('/bookings');

        // เข้าหน้าเช็คอินซ้ำต้องไม่เขียนสถานะทับซ้อนจนพนักงานถูกพาไปหน้าสถานะไม่ใช่ "รอเช็คอิน"
        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');
    }

    public function test_booking_list_marks_overdue_reserved_booking_as_expired(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $response = $this->actingAs($employee, 'web')->get('/bookings');

        $response->assertOk()
            ->assertSee('หมดเวลา (Expired)')
            ->assertSee('เช็คอินไม่ได้แล้ว')
            ->assertDontSee(route('bookings.checkin', $booking));

        // การดูรายการอย่างเดียวไม่เขียนทับสถานะจริง — ให้คิว AutoCancelExpiredBookings เป็นผู้ปิด
        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    public function test_booking_list_still_shows_checkin_button_before_the_deadline(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertSee('เช็คอิน')
            ->assertDontSee('หมดเวลา (Expired)');
    }

    public function test_employee_can_checkin_with_a_photo(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $response = $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
            ]);

        $response->assertRedirect('/bookings')->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->booking_status);
        $this->assertNotNull($booking->actual_checkin_time);
        $this->assertNotNull($booking->checkin_photo);
        $this->assertNull($booking->actual_checkout_time);
        $this->assertSame(Desk::STATUS_CHECKED_IN, $booking->desk->fresh()->desk_status);
    }

    public function test_checkin_requires_a_photo(): void
    {
        Http::fake();

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [])
            ->assertSessionHasErrors('photo');

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    public function test_late_checkin_cancels_the_booking(): void
    {
        Http::fake();

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('error', CheckInController::EXPIRED_MESSAGE);

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_AVAILABLE, $booking->desk->fresh()->desk_status);
    }

    public function test_checkin_store_redirects_instead_of_422_for_unavailable_booking(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $booking->forceFill(['booking_status' => Booking::STATUS_COMPLETED])->save();

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('error', 'สถานะไม่สามารถเช็คอินได้');
    }

    public function test_checkin_page_shows_a_random_selfie_challenge(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $response = $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin');

        $response->assertOk()
            ->assertSee('โจทย์สุ่มของคุณ')
            ->assertSee('สุ่มโจทย์ใหม่')
            ->assertSee('แนบไฟล์รูป หรือถ่ายรูปตอนนี้');
    }

    public function test_employee_cannot_checkin_someone_elses_booking(): void
    {
        [, $booking] = $this->reservation(now());
        $intruder = Employee::factory()->create();

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($intruder, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertForbidden();
    }

    public function test_employee_can_checkout(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $booking->forceFill([
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => now(),
        ])->save();

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkout')
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->booking_status);
        $this->assertNotNull($booking->actual_checkout_time);
    }

    public function test_checkin_stores_the_selfie_prompt_for_the_lightbox(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $prompt = SelfieChallenge::texts()[0];

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
                'selfie_prompt' => $prompt,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $this->assertSame($prompt, $booking->fresh()->selfie_prompt);
    }

    public function test_checkin_rejects_an_unknown_selfie_prompt(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
                'selfie_prompt' => 'ทดสอบโจทย์ที่ไม่มีอยู่จริง',
            ])
            ->assertSessionHasErrors('selfie_prompt');

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    public function test_checkout_is_rejected_for_reserved_booking(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkout')
            ->assertStatus(422);

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    /**
     * สร้างใบจองสถานะ 'จองแล้ว (รอเช็คอิน)' โดยยังไม่เลื่อนเวลา
     *
     * @return array{0: Employee, 1: Booking}
     */
    private function reservation(Carbon $date): array
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create();
        $slot = TimeSlot::find('Full Day');

        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $date->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
            'booking_status' => Booking::STATUS_RESERVED,
        ]);

        return [$employee, $booking];
    }
}
