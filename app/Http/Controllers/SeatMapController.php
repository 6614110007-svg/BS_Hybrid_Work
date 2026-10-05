<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Services\CurrentActor;
use App\Support\DeskState;
use App\Support\Holiday;
use App\Support\OptionCache;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeatMapController extends Controller
{
    /**
     * หน้า /dashboard ของพนักงาน — ค้นหาและจองโต๊ะ
     */
    public function index(Request $request, CurrentActor $current): View
    {
        return view('seatmap.index', $this->payload($request, $current));
    }

    /**
     * โหลดเฉพาะผังโต๊ะเป็น HTML ผ่าน fetch (ไม่โหลดทั้งหน้าใหม่)
     *
     * ใช้ตอนเปลี่ยนวันที่ / ช่วงเวลา / โซน เพื่อให้เห็นผลลัพธ์เร็วขึ้น
     */
    public function partial(Request $request, CurrentActor $current): JsonResponse
    {
        $data = $this->payload($request, $current);

        return response()->json([
            'html' => view('seatmap.partial', $data)->render(),
            'date' => $data['date']->toDateString(),
            'time_slot' => $data['slot']->name,
            'zone_id' => $data['zoneId'],
        ]);
    }

    /**
     * ข้อมูลชุดเดียวกันสำหรับทั้งหน้าเต็มและ partial
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request, CurrentActor $current): array
    {
        [$date, $slotName, $zoneId] = $this->criteria($request);

        $employee = $current->employee();
        $slot = TimeSlot::find($slotName);

        $zones = Zone::query()
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->with(['desks' => fn ($query) => $query->orderBy('desk_number')])
            ->orderBy('zone_name')
            ->get();

        $minDate = Carbon::today()->toDateString();
        $maxDate = Carbon::today()->addDays((int) config('booking.lead_days', 14))->toDateString();

        $deskStates = $this->deskStates($date, $slotName, $employee->employee_id);

        return [
            'zones' => $zones,
            'zoneSummaries' => $this->zoneSummaries($deskStates),
            'allZones' => $this->zoneOptions(),
            'slots' => TimeSlot::all(),
            'slot' => $slot,
            'date' => $date,
            'zoneId' => $zoneId,
            'deskStates' => $deskStates,
            'myBookings' => $this->myBookings($employee, $date, $slotName),
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            'stateMeta' => DeskState::meta(),
            'bookingPicker' => [
                'date' => $date->toDateString(),
                'slot' => $slot->name,
                'minDate' => $minDate,
                'maxDate' => $maxDate,
                'zoneId' => $zoneId,
                'switchAfter' => TimeSlot::SAME_DAY_SWITCH_AFTER,
                'graceMinutes' => (int) config('booking.late_grace_minutes', 60),
                'leadDays' => (int) config('booking.lead_days', 14),
                'slots' => array_map(fn (TimeSlot $item) => [
                    'name' => $item->name,
                    'start' => $item->start,
                    'end' => $item->end,
                ], TimeSlot::all()),
                // รายการวันหยุดชุดเดียวกับที่ BookingController ใช้ตรวจฝั่ง Server
                'holidays' => Holiday::names(),
            ],
        ];
    }

    /**
     * อัปเดตสถานะโต๊ะแบบ real-time (polling จากหน้า seat map)
     */
    public function status(Request $request, CurrentActor $current): JsonResponse
    {
        [$date, $slotName, $zoneId] = $this->criteria($request);

        $allStates = $this->deskStates($date, $slotName, $current->employee()->employee_id);

        $states = $allStates;

        if ($zoneId) {
            $states = array_filter($states, fn (array $state) => $state['zone_id'] === $zoneId);
        }

        return response()->json([
            'date' => $date,
            'time_slot' => $slotName,
            'desks' => array_values($states),
            // จำนวนโต๊ะว่างรายโซน ใช้อัปเดต Banner บนหน้าเลือกจองโต๊ะแบบ real-time
            // นับจากทุกโซนเสมอ ไม่ต้องกรองตาม zone_id ที่เลือกไว้
            'zones' => array_values($this->zoneSummaries($allStates)),
        ]);
    }

    /**
     * ข้อมูลประจำโซนสำหรับ Banner บนหน้า /dashboard
     *
     * รวมรูปปก คำบรรยายบรรยากาศ และจำนวนโต๊ะที่ "ว่างจริง ณ ขณะนี้" (ไม่ใช่แค่จำนวนโต๊ะที่ไม่ปิดซ่อม)
     * เพื่อให้พนักงานเห็นสถานะความจริงก่อนตัดสินใจเลือกโต๊ะ
     *
     * @param  array<string, array{zone_id:string, state:string}>  $deskStates
     * @return array<string, array{zone_id:string, zone_name:string, description:?string, image:?string, total:int, available:int}>
     */
    private function zoneSummaries(array $deskStates): array
    {
        $summaries = [];

        Zone::query()
            ->orderBy('zone_name')
            ->get(['zone_id', 'zone_name', 'zone_description', 'zone_image'])
            ->each(function (Zone $zone) use ($deskStates, &$summaries) {
                $summaries[$zone->zone_id] = [
                    'zone_id' => $zone->zone_id,
                    'zone_name' => $zone->zone_name,
                    'description' => $zone->zone_description,
                    'image' => $zone->imageUrl(),
                    'total' => 0,
                    'available' => 0,
                ];
            });

        foreach ($deskStates as $state) {
            if (! isset($summaries[$state['zone_id']])) {
                continue;
            }

            $summaries[$state['zone_id']]['total']++;

            if ($state['state'] === DeskState::AVAILABLE) {
                $summaries[$state['zone_id']]['available']++;
            }
        }

        return $summaries;
    }

    /**
     * ตรวจสอบเงื่อนไขการค้นหาจาก query string
     *
     * @return array{0:Carbon, 1:string, 2:?string}
     */
    private function criteria(Request $request): array
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $maxDate = $today->copy()->addDays((int) config('booking.lead_days', 14));

        $date = Carbon::parse($request->query('date', $today->toDateString()))->startOfDay();

        abort_if($date->lt($today) || $date->gt($maxDate), 422, 'วันที่ที่เลือกอยู่นอกช่วงที่เปิดให้จอง');

        // ไม่มีพารามิเตอร์ time_slot -> เลือกสล็อตที่ยังเลือกได้ของวันนั้น
        // (เช่น วันนี้เกินเวลาแล้วจะได้รอบบ่ายแทนรอบเช้า)
        $defaultSlot = $request->query('time_slot')
            ? (string) $request->query('time_slot')
            : TimeSlot::firstSelectableOn($date, $now)->name;

        $slotName = $defaultSlot;

        abort_unless(TimeSlot::exists($slotName), 422, 'ไม่พบช่วงเวลาที่เลือก');

        $zoneId = $request->query('zone_id');
        $zoneId = $zoneId && Zone::whereKey($zoneId)->exists() ? (string) $zoneId : null;

        return [$date, $slotName, $zoneId];
    }

    /**
     * สถานะของทุกโต๊ะ สำหรับวันที่ + ช่วงเวลาที่เลือก
     *
     * @return array<string, array{desk_id:string, zone_id:string, state:string, booking_id:?string, mine:bool}>
     */
    private function deskStates(Carbon $date, string $slotName, string $employeeId): array
    {
        $bookings = Booking::active()
            ->forDate($date->toDateString())
            ->forSlot($slotName)
            ->get(['booking_id', 'desk_id', 'employee_id', 'booking_status'])
            ->keyBy('desk_id');

        return Desk::query()
            ->orderBy('desk_number')
            ->get()
            ->mapWithKeys(function (Desk $desk) use ($bookings, $employeeId) {
                $booking = $bookings->get($desk->desk_id);
                $state = DeskState::resolve($desk, $booking, $employeeId);

                return [$desk->desk_id => [
                    'desk_id' => $desk->desk_id,
                    'zone_id' => $desk->zone_id,
                    'state' => $state,
                    'booking_id' => $booking?->booking_id,
                    'mine' => $booking !== null && $booking->employee_id === $employeeId,
                ]];
            })
            ->all();
    }

    /**
     * ตัวเลือกโซนทั้งหมด — cache ไว้ เพราะเป็นข้อมูลโครงสร้างที่แทบไม่เปลี่ยน
     * และถูกเรียกทุกครั้งที่เปิดหน้า dashboard
     *
     * @return \Illuminate\Support\Collection<int, Zone>
     */
    private function zoneOptions()
    {
        return OptionCache::zones();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function myBookings(Employee $employee, Carbon $date, string $slotName)
    {
        return Booking::with(['desk.zone'])
            ->where('employee_id', $employee->employee_id)
            ->forDate($date->toDateString())
            ->forSlot($slotName)
            ->active()
            ->get();
    }
}
