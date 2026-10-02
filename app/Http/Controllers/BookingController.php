<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Services\CurrentActor;
use App\Services\SupabaseStorage;
use App\Support\Holiday;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BookingController extends Controller
{
    /** ข้อความเดียวกับที่ปฏิทินบอกผู้ใช้ */
    public const NON_BOOKABLE_DATE_MESSAGE = 'ไม่สามารถจองโต๊ะในวันเสาร์-อาทิตย์ หรือวันหยุดนักขัตฤกษ์ได้';

    public function mine(CurrentActor $current): View
    {
        $bookings = Booking::with(['desk.zone', 'employee.department'])
            ->where('employee_id', $current->employee()->employee_id)
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->paginate(15);

        return view('bookings.mine', [
            'bookings' => $bookings,
            'photoViews' => $this->photoViews($bookings),
        ]);
    }

    /**
     * ข้อมูลสำหรับ lightbox ดูรูปเช็คอิน
     *
     * สร้าง URL ลายเซ็นให้เฉพาะใบจองที่มีรูปจริง เพื่อไม่ให้เปิดดูรูปที่ไม่มี
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Booking>  $bookings
     * @return array<int, array<string, string|null>>
     */
    private function photoViews($bookings): array
    {
        $storage = new SupabaseStorage;

        return $bookings->getCollection()
            ->filter(fn (Booking $booking) => filled($booking->checkin_photo))
            ->map(function (Booking $booking) use ($storage) {
                $url = null;

                try {
                    $url = $storage->signedUrl($booking->checkin_photo, 3600);
                } catch (Throwable) {
                    // Storage สร้างลายเซ็นไม่ได้ชั่วคราว — ถอยไปใช้ public URL แทน
                    $url = $storage->publicUrl($booking->checkin_photo);
                }

                return [
                    'id' => $booking->booking_id,
                    'url' => $url,
                    'employee_name' => $booking->employee?->employee_fullname ?? '-',
                    'department' => $booking->employee?->department?->department_name ?? '-',
                    'checked_in_at' => $booking->actual_checkin_time?->format('d/m/Y H:i น.') ?? '-',
                    'slot_label' => $booking->booking_date->format('d/m/Y').' · '.$booking->time_slot,
                    'desk_label' => 'โต๊ะ '.$booking->desk->desk_number.' · '.$booking->desk->zone->zone_name,
                    'prompt' => $booking->selfie_prompt,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * กฎการจอง
     *   - โต๊ะ 1 ตัว จองได้ 1 คน/ช่วงเวลา
     *   - พนักงาน 1 คน จองซ้ำในวัน-ช่วงเวลาเดียวกันไม่ได้
     *   - เสาร์-อาทิตย์และวันหยุดนักขัตฤกษ์ จองไม่ได้
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
                // ชั้นป้องกันฝั่ง Server — รายการวันหยุดอยู่ที่ config/booking.php คีย์ 'holidays'
                fn (string $attribute, mixed $value, Closure $fail) => is_string($value) && ! Holiday::isBookable($value)
                    ? $fail(self::NON_BOOKABLE_DATE_MESSAGE)
                    : null,
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

        // Same-Day Walk-in (จองหลังเวลาเริ่มสล็อต) ให้นับเวลาเช็คอิน 60 นาที
        // จากเวลาที่กดจอง แทนที่จะนับจากเวลาเริ่มสล็อต (เช่น จองรอบบ่าย 15:00 น.)
        $expiresAt = $booking->rememberCheckinAnchor();

        return back()->with('success', sprintf(
            'จองโต๊ะ %s ช่วง %s เรียบร้อยแล้ว · เช็คอินได้ถึง %s น.',
            $booking->desk->desk_number,
            $booking->time_slot,
            $expiresAt->format('H:i'),
        ));
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
