<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Zone;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * สรุปสถิติการใช้งานสำหรับ Admin Dashboard และหน้ารายงาน
 */
class Analytics
{
    /**
     * สถานะโต๊ะ "ตอนนี้" แบบ real-time สำหรับหน้า Dashboard
     *
     * @return array{using_now:int, available_now:int, total_usable_desks:int, today_bookings:int, today_checked_in:int, today_rate:int, maintenance:int}
     */
    public function liveCounts(): array
    {
        $today = Carbon::today()->toDateString();

        $usingNow = Booking::forDate($today)
            ->where('booking_status', Booking::STATUS_CHECKED_IN)
            ->count();

        $totalUsable = Desk::usable()->count();
        $maintenance = Desk::maintenance()->count();

        $todayBookings = Booking::forDate($today)
            ->whereIn('booking_status', [
                Booking::STATUS_RESERVED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_COMPLETED,
            ])
            ->count();

        $todayCheckedIn = Booking::forDate($today)
            ->whereIn('booking_status', [Booking::STATUS_CHECKED_IN, Booking::STATUS_COMPLETED])
            ->count();

        return [
            'using_now' => $usingNow,
            'available_now' => max($totalUsable - $usingNow, 0),
            'total_usable_desks' => $totalUsable,
            'today_bookings' => $todayBookings,
            'today_checked_in' => $todayCheckedIn,
            'today_rate' => $todayBookings > 0 ? round($todayCheckedIn / $todayBookings * 100) : 0,
            'maintenance' => $maintenance,
        ];
    }

    /**
     * อัตราการใช้งาน (%) รายโซน รายช่วงเวลา
     *
     * @return array<int, array{zone_id:string, zone_name:string, total:int, maintenance:int, occupied:int, pct:int, slots:array<int, array{time_slot:string, occupied:int, pct:int}>}>
     */
    public function zoneOccupancy(string $date): array
    {
        $zones = Zone::with('desks')->orderBy('zone_name')->get();

        $bookings = Booking::active()
            ->forDate($date)
            ->get(['desk_id', 'time_slot'])
            ->groupBy('time_slot')
            ->map(fn ($group) => $group->pluck('desk_id')->all());

        $slots = TimeSlot::all();

        return $zones->map(function (Zone $zone) use ($slots, $bookings) {
            $desks = $zone->desks;
            $usable = $desks->reject(fn (Desk $desk) => $desk->isMaintenance());
            $usableIds = $usable->pluck('desk_id')->all();

            $slotRows = array_map(function (TimeSlot $slot) use ($bookings, $usableIds) {
                $taken = $bookings->get($slot->name, []);
                $occupied = count(array_intersect($usableIds, $taken));

                return [
                    'time_slot' => $slot->name,
                    'occupied' => $occupied,
                    'pct' => $usableIds !== [] ? round($occupied / count($usableIds) * 100) : 0,
                ];
            }, $slots);

            $occupied = max(array_map(fn ($row) => $row['occupied'], $slotRows) ?: [0]);

            return [
                'zone_id' => $zone->zone_id,
                'zone_name' => $zone->zone_name,
                'total' => count($usableIds),
                'maintenance' => $desks->filter(fn (Desk $desk) => $desk->isMaintenance())->count(),
                'occupied' => $occupied,
                'pct' => $usableIds !== [] ? round($occupied / count($usableIds) * 100) : 0,
                'slots' => $slotRows,
            ];
        })->values()->all();
    }

    /**
     * สถิติรายวัน (เติมวันที่ไม่มีข้อมูลด้วย 0)
     *
     * @return Collection<int, array{date:string, label:string, reserved:int, checked_in:int, completed:int, expired:int, bookings:int, arrived:int, no_show:int, rate:int}>
     */
    public function dailyStats(string $from, string $to): Collection
    {
        $rows = Booking::query()
            ->whereDate('booking_date', '>=', $from)
            ->whereDate('booking_date', '<=', $to)
            ->selectRaw('booking_date')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when booking_status = ? then 1 else 0 end) as reserved', [Booking::STATUS_RESERVED])
            ->selectRaw('sum(case when booking_status = ? then 1 else 0 end) as checked_in', [Booking::STATUS_CHECKED_IN])
            ->selectRaw('sum(case when booking_status = ? then 1 else 0 end) as completed', [Booking::STATUS_COMPLETED])
            ->selectRaw('sum(case when booking_status = ? then 1 else 0 end) as expired', [Booking::STATUS_EXPIRED])
            ->groupBy('booking_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->booking_date)->toDateString());

        $days = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $last = Carbon::parse($to)->startOfDay();

        for (; $cursor->lte($last); $cursor->addDay()) {
            $days->push($cursor->copy());
        }

        return $days->map(function (Carbon $day) use ($rows) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $bookings = (int) ($row->total ?? 0);
            $arrived = (int) ($row->checked_in ?? 0) + (int) ($row->completed ?? 0);

            return [
                'date' => $key,
                'label' => $day->format('d/m'),
                'reserved' => (int) ($row->reserved ?? 0),
                'checked_in' => (int) ($row->checked_in ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'expired' => (int) ($row->expired ?? 0),
                'bookings' => $bookings,
                'arrived' => $arrived,
                'no_show' => (int) ($row->expired ?? 0),
                'rate' => $bookings > 0 ? round($arrived / $bookings * 100) : 0,
            ];
        })->values();
    }

    /**
     * สถิติรายเดือน (รวมจากสถิติรายวัน)
     *
     * @return Collection<int, array{month:string, label:string, bookings:int, arrived:int, expired:int, rate:int}>
     */
    public function monthlyStats(string $from, string $to): Collection
    {
        return $this->dailyStats($from, $to)
            ->groupBy(fn (array $row) => substr($row['date'], 0, 7))
            ->map(function (Collection $rows, string $month) {
                $bookings = $rows->sum('bookings');
                $arrived = $rows->sum('arrived');

                return [
                    'month' => $month,
                    'label' => Carbon::parse($month.'-01')->format('M Y'),
                    'bookings' => $bookings,
                    'arrived' => $arrived,
                    'expired' => $rows->sum('expired'),
                    'rate' => $bookings > 0 ? round($arrived / $bookings * 100) : 0,
                ];
            })
            ->values();
    }

    /**
     * กิจกรรมล่าสุด (booking / check-in / check-out) สำหรับ feed บนหน้า Dashboard
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentActivity(int $limit = 10): Collection
    {
        return Booking::query()
            ->with(['employee.department', 'desk.zone'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->orderByDesc('booking_id')
            ->limit($limit)
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'booking_id' => $booking->booking_id,
                    'employee' => $booking->employee->employee_fullname,
                    'department' => $booking->employee->department?->department_name,
                    'zone' => $booking->desk->zone->zone_name,
                    'desk' => $booking->desk->desk_number,
                    'date' => $booking->booking_date->format('d/m/Y'),
                    'time_slot' => $booking->time_slot,
                    'status' => $booking->booking_status,
                    'status_label' => $booking->statusLabel(),
                    'action_at' => $booking->actual_checkout_time ?? $booking->actual_checkin_time,
                ];
            });
    }
}
