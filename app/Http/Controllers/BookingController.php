<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Services\CurrentActor;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function mine(CurrentActor $current): View
    {
        return view('bookings.mine', [
            'bookings' => Booking::with(['desk.zone', 'employee.department'])
                ->where('employee_id', $current->employee()->employee_id)
                ->orderByDesc('booking_date')
                ->orderByDesc('start_time')
                ->paginate(15),
        ]);
    }

    /**
     * กฎการจอง
     *   - โต๊ะ 1 ตัว จองได้ 1 คน/ช่วงเวลา
     *   - พนักงาน 1 คน จองซ้ำในวัน-ช่วงเวลาเดียวกันไม่ได้
     */
    public function store(Request $request, CurrentActor $current): RedirectResponse
    {
        $data = $request->validate([
            'desk_id' => ['required', 'string', Rule::exists('desk', 'desk_id')],
            'booking_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.Carbon::today()->addDays((int) config('booking.lead_days', 14))->toDateString(),
            ],
            'time_slot' => ['required', 'string', Rule::in(TimeSlot::names())],
        ]);

        $employee = $current->employee();
        $slot = TimeSlot::find($data['time_slot']);
        $error = null;
        $booking = null;

        DB::transaction(function () use (&$booking, &$error, $data, $employee, $slot) {
            $desk = Desk::whereKey($data['desk_id'])->lockForUpdate()->first();

            if ($desk->isMaintenance()) {
                $error = 'โต๊ะนี้อยู่ระหว่างปิดซ่อมบำรุง ไม่สามารถจองได้';

                return;
            }

            $taken = Booking::active()
                ->forDate($data['booking_date'])
                ->forSlot($slot->name)
                ->where(function ($query) use ($desk, $employee) {
                    $query->where('desk_id', $desk->desk_id)
                        ->orWhere('employee_id', $employee->employee_id);
                })
                ->first();

            if ($taken?->desk_id === $desk->desk_id) {
                $error = 'โต๊ะนี้ถูกจองในช่วงเวลานี้แล้ว';

                return;
            }

            if ($taken !== null) {
                $error = 'คุณมีการจองซ้อนอยู่ในช่วงเวลาเดียวกันแล้ว (1 คนจองได้ครั้งละ 1 โต๊ะ)';

                return;
            }

            $booking = Booking::create([
                'employee_id' => $employee->employee_id,
                'desk_id' => $desk->desk_id,
                'booking_date' => $data['booking_date'],
                'time_slot' => $slot->name,
                'start_time' => $slot->start,
                'end_time' => $slot->end,
                'booking_status' => Booking::STATUS_RESERVED,
            ]);
        });

        if ($error) {
            throw ValidationException::withMessages(['desk_id' => $error]);
        }

        return back()->with('success', "จองโต๊ะ {$booking->desk->desk_number} ช่วง {$booking->time_slot} เรียบร้อยแล้ว");
    }

    public function destroy(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        abort_unless($booking->employee_id === $current->employee()?->employee_id, 403, 'ไม่ใช่การจองของคุณ');

        if (! $booking->isCancelable()) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ เนื่องจากเช็คอินแล้วหรือใบจองถูกปิดใช้งาน');
        }

        $booking->forceFill(['booking_status' => Booking::STATUS_EXPIRED])->save();

        return back()->with('success', 'ยกเลิกการจองเรียบร้อยแล้ว');
    }
}
