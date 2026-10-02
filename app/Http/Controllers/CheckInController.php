<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\CurrentActor;
use App\Services\SupabaseStorage;
use App\Support\SelfieChallenge;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CheckInController extends Controller
{
    /**
     * ข้อความเดียวกันทั้ง GET/POST และหน้ารายการจอง
     */
    public const EXPIRED_MESSAGE = 'รายการจองนี้หมดเวลาเช็คอินแล้ว';

    /**
     * หน้าถ่ายรูปเซลฟี่เพื่อเช็คอิน
     */
    public function create(Request $request, Booking $booking, CurrentActor $current): View|RedirectResponse
    {
        $this->authorizeBooking($booking, $current);

        if (! $booking->isReserved()) {
            return redirect()->route('bookings.mine')->with('error', 'สถานะไม่ใช่ "จองแล้ว (รอเช็คอิน)" จึงเช็คอินไม่ได้');
        }

        $challenge = SelfieChallenge::random();

        // เลยเวลาเช็คอินแล้ว = ปิดวงจรทันที ไม่ต้องรอคิวตั้งเวลา
        if ($booking->expireIfOverdue()) {
            return redirect()->route('bookings.mine')->with('error', self::EXPIRED_MESSAGE);
        }

        if (Carbon::now()->lt($booking->checkinOpensAt())) {
            return redirect()->route('bookings.mine')
                ->with('error', 'ยังไม่ถึงเวลาเช็คอิน เปิดให้เช็คอินก่อนเวลาเริ่มได้สูงสุด '.config('booking.early_checkin_minutes').' นาที');
        }

        return view('bookings.checkin', [
            'booking' => $booking->load(['desk.zone', 'employee.department']),
            'deadline' => $booking->checkinDeadline(),
            // Same-Day Walk-in (จองหลังเวลาเริ่มสล็อต) ให้นับเวลาเช็คอินจากเวลาที่กดจอง
            'isWalkIn' => $booking->checkinAnchor()->gt($booking->startsAt()),
            'challenge' => $challenge,
        ]);
    }

    /**
     * อัปโหลดรูปเซลฟี่ -> booking_status = 'C' พร้อม actual_checkin_time
     */
    public function store(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        $this->authorizeBooking($booking, $current);

        if (! $booking->isReserved()) {
            return redirect()->route('bookings.mine')->with('error', 'สถานะไม่สามารถเช็คอินได้');
        }

        if ($booking->expireIfOverdue()) {
            return redirect()->route('bookings.mine')->with('error', self::EXPIRED_MESSAGE);
        }

        $data = $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:'.implode(',', (array) config('booking.checkin_photo_mimes', ['jpeg', 'png', 'webp'])),
                'max:'.config('booking.checkin_photo_max_kb', 5120),
            ],
            // โจทย์ที่แสดงบนหน้าเว็บส่งกลับมาให้บันทึกไว้ เพื่อแสดงย้อนหลังใน lightbox
            'selfie_prompt' => ['nullable', 'string', Rule::in(SelfieChallenge::texts())],
        ]);

        $photo = $request->file('photo');
        $path = 'checkins/'.$booking->employee_id.'/'.$booking->booking_id.'/'.Carbon::now()->format('YmdHis').'.'.$photo->extension();

        try {
            (new SupabaseStorage)->upload($path, (string) $photo->get(), $photo->getMimeType());
        } catch (RuntimeException|ConnectionException) {
            return back()->with('error', 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }

        $booking->forceFill([
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => Carbon::now(),
            'checkin_photo' => $path,
            'selfie_prompt' => $data['selfie_prompt'] ?? null,
        ])->save();

        return redirect()->route('bookings.mine')->with('success', 'เช็คอินสำเร็จ ขอให้ทำงานอย่างมีความสุข');
    }

    /**
     * เช็คเอาต์ -> booking_status = 'COMP' พร้อม actual_checkout_time
     */
    public function checkout(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        $this->authorizeBooking($booking, $current);

        abort_unless($booking->isCheckedIn(), 422, 'สถานะไม่สามารถเช็คเอาต์ได้');

        $booking->forceFill([
            'booking_status' => Booking::STATUS_COMPLETED,
            'actual_checkout_time' => Carbon::now(),
        ])->save();

        return redirect()->route('bookings.mine')->with('success', 'เช็คเอาต์เรียบร้อยแล้ว เจอกันใหม่ครั้งหน้า');
    }

    /**
     * ดูรูปเช็คอิน (เจ้าของใบจองหรือผู้ดูแลระบบ)
     */
    public function photo(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        $isOwner = $booking->employee_id === $current->employee()?->employee_id;

        abort_unless($isOwner || $current->isAdministrator(), 403, 'ไม่มีสิทธิ์ดูรูปนี้');
        abort_unless($booking->checkin_photo, 404, 'ยังไม่มีรูปเช็คอิน');

        $storage = new SupabaseStorage;

        try {
            return redirect()->away($storage->signedUrl($booking->checkin_photo, 3600));
        } catch (Throwable) {
            // Storage ใช้งานไม่ได้ชั่วคราว — ถอยไปใช้ public URL แทน
            return redirect()->away($storage->publicUrl($booking->checkin_photo));
        }
    }

    private function authorizeBooking(Booking $booking, CurrentActor $current): void
    {
        abort_unless($booking->employee_id === $current->employee()?->employee_id, 403, 'ไม่ใช่การจองของคุณ');
    }
}
