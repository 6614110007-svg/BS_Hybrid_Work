<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\SupabaseStorage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CheckInController extends Controller
{
    /**
     * Show the selfie capture page for a booking.
     */
    public function create(Request $request, Booking $booking): View|RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'ไม่ใช่การจองของคุณ');

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            return redirect()->route('dashboard')->with('info', 'ไม่สามารถเช็คอินได้เนื่องจากสถานะไม่ใช่ "รอเช็คอิน"');
        }

        $window = $this->window($booking);

        if ($window['now']->lte($window['open'])) {
            return redirect()->route('dashboard')->with('error', 'ยังไม่ถึงเวลาเช็คอิน ให้เช็คอินก่อนเวลาเริ่มได้สูงสุด '.config('booking.early_checkin_minutes', 60).' นาที');
        }

        if ($window['now']->gt($window['deadline'])) {
            $booking->update(['status' => Booking::STATUS_EXPIRED, 'cancel_reason' => Booking::CANCEL_AUTO_LATE]);

            return redirect()->route('dashboard')->with('error', 'เกินเวลาเช็คอินที่กำหนดแล้ว ('.config('booking.late_grace_minutes', 60).' นาที) ระบบยกเลิกใบจองให้อัตโนมัติ');
        }

        $challenges = config('selfie_challenges.list', []);

        return view('bookings.checkin', [
            'booking' => $booking->load(['desk.zone', 'timeSlot']),
            'challenge' => $challenges[max(0, Carbon::today()->copy()->tz('Asia/Bangkok')->dayOfYear % count($challenges))],
            'deadline' => $window['deadline'],
            'earlyMinutes' => config('booking.early_checkin_minutes', 60),
            'graceMinutes' => config('booking.late_grace_minutes', 60),
        ]);
    }

    /**
     * Upload the selfie and mark the booking as checked in.
     */
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'ไม่ใช่การจองของคุณ');
        abort_if($booking->status !== Booking::STATUS_CONFIRMED, 422, 'สถานะไม่สามารถเช็คอินได้');
        abort_if($booking->checked_in_at, 422, 'เช็คอินแล้ว');

        $window = $this->window($booking);

        if ($window['now']->gt($window['deadline'])) {
            $booking->update(['status' => Booking::STATUS_EXPIRED, 'cancel_reason' => Booking::CANCEL_AUTO_LATE]);

            return redirect()->route('dashboard')->with('error', 'เกินเวลาเช็คอินที่กำหนดแล้ว ระบบยกเลิกใบจองให้อัตโนมัติ.');
        }

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:'.config('booking.checkin_photo_max_kb', 5120)],
        ]);

        $challenges = config('selfie_challenges.list', []);
        $challenge = $challenges[max(0, Carbon::today()->copy()->tz('Asia/Bangkok')->dayOfYear % count($challenges))];

        $photo = $request->file('photo');
        $ext = match ($photo->extension()) {
            'webp' => 'webp',
            'png' => 'png',
            default => 'jpg',
        };

        $path = 'checkins/u'.$booking->user_id.'/b'.$booking->id.'/'.Carbon::now()->format('YmdHis').'.'.$ext;

        try {
            (new SupabaseStorage)->upload($path, (string) $photo->get(), $photo->getMimeType());
        } catch (RuntimeException) {
            return back()->with('error', 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }

        $booking->update([
            'status' => Booking::STATUS_CHECKED_IN,
            'checked_in_at' => Carbon::now(),
            'checkin_photo_path' => $path,
            'checkin_challenge' => $challenge,
        ]);

        return redirect()->route('dashboard')->with('success', 'เช็คอินสำเร็จ ขอให้ทำงานอย่างมีความสุข! 🤙');
    }

    /**
     * Check out a currently checked-in booking.
     */
    public function checkout(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'ไม่ใช่การจองของคุณ');

        abort_if($booking->status !== Booking::STATUS_CHECKED_IN, 422, 'สถานะไม่สามารถเช็คเอาต์ได้');

        $booking->update([
            'status' => Booking::STATUS_CHECKED_OUT,
            'checked_out_at' => Carbon::now(),
        ]);

        return back()->with('success', 'เช็คเอาต์เรียบร้อยแล้ว เจอกันใหม่ครั้งหน้า!');
    }

    /**
     * Resolve the check-in window: [open, deadline].
     *
     * @return array{now:Carbon, open:Carbon, deadline:Carbon}
     */
    private function window(Booking $booking): array
    {
        $now = Carbon::now();

        return [
            'now' => $now,
            'open' => $booking->starts_at->copy()->subMinutes((int) config('booking.early_checkin_minutes', 60)),
            'deadline' => $booking->starts_at->copy()->addMinutes((int) config('booking.late_grace_minutes', 60)),
        ];
    }

    /**
     * Redirect to the selfie image (owner or admin only).
     */
    public function photo(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $booking->user_id === $request->user()->id, 403, 'ไม่มีสิทธิ์ดูรูปนี้');
        abort_unless($booking->checkin_photo_path, 404, 'ยังไม่มีรูปเช็คอิน');

        try {
            return redirect()->away((new SupabaseStorage)->signedUrl($booking->checkin_photo_path, 3600));
        } catch (RuntimeException) {
            return redirect()->away((new SupabaseStorage)->publicUrl($booking->checkin_photo_path));
        }
    }
}