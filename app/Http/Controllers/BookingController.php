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
            ...$this->amendForm(),
            ...$this->amendReopen(),
        ]);
    }

    /**
     * ตัวเลือกสำหรับ Modal แก้ไขการจอง
     *
     * รายการโต๊ะที่เลือกได้คือโต๊ะที่ไม่ได้อยู่ระหว่างปิดซ่อมบำรุง
     * ส่วนการชนกันของวันที่/ช่วงเวลาให้ฝั่ง Server ตรวจตอนกดบันทึก
     *
     * @return array<string, mixed>
     */
    private function amendForm(): array
    {
        return [
            'amendDesks' => Desk::with('zone')
                ->where('desk_status', '!=', Desk::STATUS_MAINTENANCE)
                ->orderBy('zone_id')
                ->orderBy('desk_number')
                ->get(),
            'amendSlots' => TimeSlot::all(),
            // รูปแบบแบนสำหรับฝั่ง Alpine (ชื่อ + เวลาเริ่ม/สิ้นสุด)
            'amendSlotOptions' => array_map(
                fn (TimeSlot $slot): array => [
                    'name' => $slot->name,
                    'start' => $slot->start,
                    'end' => $slot->end,
                ],
                TimeSlot::all(),
            ),
            'amendMinDate' => Carbon::today()->toDateString(),
            'amendMaxDate' => Carbon::today()->addDays((int) config('booking.lead_days', 14))->toDateString(),
            'amendHolidays' => Holiday::names(),
        ];
    }

    /**
     * เปิด modal แก้ไขการจองกลับมาหลังบันทึกไม่สำเร็จ
     *
     * ถ้าผู้ใช้กดบันทึกแล้วค่าไม่ผ่าน (โต๊ะถูกจองไปแล้ว วันที่ไม่ใช่วันที่จองได้ ฯลฯ)
     * หน้า /bookings จะได้รับ amend_booking_id กลับมา ต้องส่ง state เดิมที่ผู้ใช้กรอกไว้
     * ให้ modal เปิดค้างพร้อมข้อความอธิบาย ไม่ใช่กลับมาแล้ว modal ปิดหายไป
     *
     * @return array<string, mixed>
     */
    private function amendReopen(): array
    {
        $bookingId = session('amend_booking_id');

        if ($bookingId === null) {
            return ['amendReopen' => null];
        }

        $booking = Booking::query()->with('desk.zone')->find($bookingId);

        if ($booking === null) {
            return ['amendReopen' => null];
        }

        return [
            'amendReopen' => [
                'id' => $booking->booking_id,
                'desk' => $booking->desk->desk_number,
                'zone' => $booking->desk->zone->zone_name,
                'desk_id' => (int) old('desk_id', $booking->desk_id),
                'date' => old('booking_date', $booking->booking_date->toDateString()),
                'slot' => old('time_slot', $booking->time_slot),
            ],
        ];
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
        $data = $request->validate($this->bookingRules());

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

    /**
     * กฎตรวจสอบข้อมูลการจอง ใช้ร่วมกันทั้งการจองใหม่ (store) และการแก้ไข (update)
     *
     * @return array<string, array<int, mixed>>
     */
    private function bookingRules(): array
    {
        return [
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
        ];
    }

    /**
     * แก้ไขการจอง (Booking Amendment)
     *
     * ให้พนักงานเปลี่ยนวันที่ / ช่วงเวลา / โต๊ะ ได้ตราบใดที่ยังไม่ถึงเวลาเช็คอิน
     * (สถานะ จองแล้ว และยังไม่เลยเวลาตาม checkinDeadline)
     *
     * การชนกันตรวจที่ Server เหมือนการจองใหม่ทุกข้อ แต่ต้อง "ตัดใบจองตัวเองออก"
     * ไม่เช่นนั้นการแก้ไขโดยไม่เปลี่ยนอะไรจะชนกับตัวเองเสมอ
     */
    public function update(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        abort_unless($booking->employee_id === $current->employee()?->employee_id, 403, 'ไม่ใช่การจองของคุณ');

        if (! $booking->isAmendable()) {
            return back()->with('error', 'ไม่สามารถแก้ไขการจองได้ เพราะเลยเวลาเช็คอินแล้ว หรือใบจองถูกปิดใช้งาน');
        }

        try {
            $data = $request->validate($this->bookingRules());
        } catch (ValidationException $exception) {
            return $this->rejectAmendment($request, $booking, $exception->errors());
        }

        $employee = $current->employee();
        $slot = TimeSlot::find($data['time_slot']);
        $error = null;

        DB::transaction(function () use ($booking, &$error, $data, $employee, $slot) {
            $desk = Desk::whereKey($data['desk_id'])->lockForUpdate()->first();

            if ($desk->isMaintenance()) {
                $error = 'โต๊ะนี้อยู่ระหว่างปิดซ่อมบำรุง ไม่สามารถจองได้';

                return;
            }

            $taken = Booking::active()
                ->forDate($data['booking_date'])
                ->forSlot($slot->name)
                // ตัดใบจองที่กำลังแก้ไขออก จะได้เปลี่ยนแค่วันที่โดยไม่ติดกับตัวเอง
                ->whereKeyNot($booking->booking_id)
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

            // เวลาที่กดจองเดิมผูกกับวันที่/สล็อตเดิม ต้องล้างก่อนบันทึกค่าใหม่
            $booking->forgetCheckinAnchor();

            $booking->forceFill([
                'desk_id' => $desk->desk_id,
                'booking_date' => $data['booking_date'],
                'time_slot' => $slot->name,
                'start_time' => $slot->start,
                'end_time' => $slot->end,
            ])->save();
        });

        if ($error) {
            return $this->rejectAmendment($request, $booking, ['desk_id' => $error]);
        }

        // คำนวณเวลาเช็คอินใหม่จากวันที่/ช่วงเวลาที่เพิ่งแก้ (รวมกรณีจองวันเดียวกันหลังเลยเวลาเริ่ม)
        $expiresAt = $booking->rememberCheckinAnchor();

        return back()->with('success', sprintf(
            'แก้ไขการจองเรียบร้อยแล้ว · โต๊ะ %s วันที่ %s ช่วง %s (เช็คอินได้ถึง %s น.)',
            $booking->desk->desk_number,
            $booking->booking_date->format('d/m/Y'),
            $booking->time_slot,
            $expiresAt->format('H:i'),
        ));
    }

    /**
     * ปฏิเสธการแก้ไขการจอง พร้อมเปิด modal กลับมาให้ผู้ใช้แก้ค่าที่ค้างไว้
     *
     * @param  array<string, array<int, string>>  $errors
     */
    private function rejectAmendment(Request $request, Booking $booking, array $errors): RedirectResponse
    {
        return back()
            ->withInput($request->except(['_token', '_method']))
            ->withErrors($errors)
            ->with('amend_booking_id', $booking->booking_id);
    }

    /**
     * ยกเลิกการจอง
     *
     * ถ้าส่ง rebook=1 จะพาไปหน้าเลือกจองโต๊ะทันที เพื่อรองรับปุ่ม "ยกเลิก แล้วจองใหม่"
     */
    public function destroy(Request $request, Booking $booking, CurrentActor $current): RedirectResponse
    {
        abort_unless($booking->employee_id === $current->employee()?->employee_id, 403, 'ไม่ใช่การจองของคุณ');

        if (! $booking->isCancelable()) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ เนื่องจากเช็คอินแล้วหรือใบจองถูกปิดใช้งาน');
        }

        $booking->forceFill(['booking_status' => Booking::STATUS_EXPIRED])->save();

        if ($request->boolean('rebook')) {
            return to_route('dashboard')
                ->with('success', 'ยกเลิกการจองเรียบร้อยแล้ว · เลือกโต๊ะใหม่ได้เลยด้านล่าง');
        }

        return back()->with('success', 'ยกเลิกการจองเรียบร้อยแล้ว');
    }
}
