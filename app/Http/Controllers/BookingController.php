<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function mine(): View
    {
        return view('bookings.mine', [
            'bookings' => Booking::with(['desk.zone', 'timeSlot'])
                ->where('user_id', auth()->id())
                ->orderByDesc('booking_date')
                ->orderBy('starts_at')
                ->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'desk_id' => ['required', 'integer'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.Carbon::today()->addDays(config('booking.lead_days', 14))->format('Y-m-d')],
            'time_slot_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $error = null;
        $booking = null;

        DB::transaction(function () use (&$booking, &$error, $data, $user) {
            $desk = Desk::whereKey($data['desk_id'])->lockForUpdate()->first();

            if (! $desk) {
                $error = 'ไม่พบโต๊ะที่เลือก';

                return;
            }

            if ($desk->isOutOfService()) {
                $error = 'โต๊ะนี้ถูกปิดใช้งานหรืออยู่ระหว่างซ่อมบำรุง';

                return;
            }

            $slot = TimeSlot::whereKey($data['time_slot_id'])->first();

            if (! $slot || ! $slot->is_active) {
                $error = 'ไม่พบช่วงเวลาที่เลือก';

                return;
            }

            $startsAt = SeatMapController::slotDateTime($data['booking_date'], $slot->start_time);
            $endsAt = SeatMapController::slotDateTime($data['booking_date'], $slot->end_time);

            $deskTaken = Booking::active()
                ->where('desk_id', $desk->id)
                ->where('booking_date', $data['booking_date'])
                ->where('time_slot_id', $slot->id)
                ->exists();

            if ($deskTaken) {
                $error = 'โต๊ะนี้ถูกจองในช่วงเวลานี้แล้ว';

                return;
            }

            $userTaken = Booking::active()
                ->where('user_id', $user->id)
                ->where('booking_date', $data['booking_date'])
                ->where('time_slot_id', $slot->id)
                ->exists();

            if ($userTaken) {
                $error = 'คุณมีการจองซ้อนอยู่ในช่วงเวลาเดียวกันแล้ว (1 คนจองได้ครั้งละ 1 โต๊ะต่อช่วงเวลา)';

                return;
            }

            $booking = Booking::create([
                'user_id' => $user->id,
                'desk_id' => $desk->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $data['booking_date'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => Booking::STATUS_CONFIRMED,
            ]);
        });

        if ($error) {
            throw ValidationException::withMessages(['desk_id' => $error]);
        }

        return back()->with('success', "จองโต๊ะ {$booking->desk->code} เรียบร้อยแล้ว");
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'ไม่ใช่การจองของคุณ');

        if (! $booking->isCancelable()) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ เนื่องจากไม่ใช่สถานะรอเช็คอิน');
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancel_reason' => Booking::CANCEL_BY_USER,
        ]);

        return back()->with('success', 'ยกเลิกการจองเรียบร้อยแล้ว');
    }
}