<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Services\CurrentActor;
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
        [$date, $slotName, $zoneId] = $this->criteria($request);

        $employee = $current->employee();
        $slot = TimeSlot::find($slotName);

        $zones = Zone::query()
            ->when($zoneId, fn ($query) => $query->where('zone_id', $zoneId))
            ->with(['desks' => fn ($query) => $query->orderBy('desk_number')])
            ->orderBy('zone_name')
            ->get();

        return view('seatmap.index', [
            'zones' => $zones,
            'allZones' => Zone::orderBy('zone_name')->get(),
            'slots' => TimeSlot::all(),
            'slot' => $slot,
            'date' => $date,
            'zoneId' => $zoneId,
            'deskStates' => $this->deskStates($date, $slotName, $employee->employee_id),
            'myBookings' => $this->myBookings($employee, $date, $slotName),
            'minDate' => Carbon::today()->toDateString(),
            'maxDate' => Carbon::today()->addDays((int) config('booking.lead_days', 14))->toDateString(),
        ]);
    }

    /**
     * อัปเดตสถานะโต๊ะแบบ real-time (polling จากหน้า seat map)
     */
    public function status(Request $request, CurrentActor $current): JsonResponse
    {
        [$date, $slotName, $zoneId] = $this->criteria($request);

        $states = $this->deskStates($date, $slotName, $current->employee()->employee_id);

        if ($zoneId) {
            $states = array_filter($states, fn (array $state) => $state['zone_id'] === $zoneId);
        }

        return response()->json([
            'date' => $date,
            'time_slot' => $slotName,
            'desks' => array_values($states),
        ]);
    }

    /**
     * ตรวจสอบเงื่อนไขการค้นหาจาก query string
     *
     * @return array{0:Carbon, 1:string, 2:?string}
     */
    private function criteria(Request $request): array
    {
        $today = Carbon::today();
        $maxDate = $today->copy()->addDays((int) config('booking.lead_days', 14));

        $date = Carbon::parse($request->query('date', $today->toDateString()))->startOfDay();

        abort_if($date->lt($today) || $date->gt($maxDate), 422, 'วันที่ที่เลือกอยู่นอกช่วงที่เปิดให้จอง');

        $slotName = (string) $request->query('time_slot', (TimeSlot::current() ?? TimeSlot::all()[0])->name);

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
                $mine = $booking !== null && $booking->employee_id === $employeeId;

                $state = match (true) {
                    $desk->isMaintenance() => 'maintenance',
                    $booking === null => 'available',
                    $booking->isCheckedIn() => $mine ? 'my_in_use' : 'in_use',
                    default => $mine ? 'my_booked' : 'booked',
                };

                return [$desk->desk_id => [
                    'desk_id' => $desk->desk_id,
                    'zone_id' => $desk->zone_id,
                    'state' => $state,
                    'booking_id' => $booking?->booking_id,
                    'mine' => $mine,
                ]];
            })
            ->all();
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
