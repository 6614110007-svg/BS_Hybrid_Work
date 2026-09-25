<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Analytics
{
    /**
     * à¸ªà¸–à¸´à¸•à¸´ "à¸•à¸­à¸™à¸™à¸µà¹‰" à¹à¸šà¸š real-time à¸ªà¸³à¸«à¸£à¸±à¸š Dashboards
     *
     * @return array{using_now:int, available_now:int, total_usable_desks:int, today_bookings:int, today_checked_in:int, today_rate:int, maintenance:int}
     */
    public function liveCounts(): array
    {
        $today = Carbon::today()->toDateString();

        $usingNow = Booking::where('status', Booking::STATUS_CHECKED_IN)
            ->where('booking_date', $today)
            ->count();

        $totalUsable = Desk::where('is_active', true)->where('is_maintenance', false)->count();
        $maintenance = Desk::where('is_maintenance', true)->count();

        $todayBookings = Booking::forDate($today)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->count();

        $todayCheckedIn = Booking::forDate($today)
            ->whereIn('status', [Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->count();

        $rate = $todayBookings > 0 ? round($todayCheckedIn / $todayBookings * 100) : 0;

        return [
            'using_now' => $usingNow,
            'available_now' => max($totalUsable - $usingNow, 0),
            'total_usable_desks' => $totalUsable,
            'today_bookings' => $todayBookings,
            'today_checked_in' => $todayCheckedIn,
            'today_rate' => $rate,
            'maintenance' => $maintenance,
        ];
    }

    /**
     * à¸­à¸±à¸•à¸£à¸²à¸„à¸§à¸²à¸¡à¸«à¸™à¸²à¹à¸™à¹ˆà¸™ (%) à¸£à¸²à¸¢à¹‚à¸‹à¸™ à¸£à¸²à¸¢à¸Šà¹ˆà¸§à¸‡à¹€à¸§à¸¥à¸² à¸ªà¸³à¸«à¸£à¸±à¸šà¸§à¸±à¸™à¸—à¸µà¹ˆà¸à¸³à¸«à¸™à¸”
     *
     * @return array<int, array{zone_id:int, zone_name:string, zone_code:string|null, total:int, maintenance:int, slots: array<int, array{slot_id:int, slot_name:string, occupied:int, pct:int}>}>
     */
    public function zoneOccupancy(string $date): array
    {
        $slots = TimeSlot::active()->ordered()->get();
        $zones = Zone::with('desks')->active()->orderBy('sort_order')->get();

        $activeBookings = Booking::active()
            ->forDate($date)
            ->get(['desk_id', 'time_slot_id'])
            ->groupBy('time_slot_id')
            ->map(fn ($g) => $g->pluck('desk_id')->all());

        return $zones->map(function (Zone $zone) use ($slots, $activeBookings) {
            $desks = $zone->desks;
            $usable = $desks->where('is_active', true)->where('is_maintenance', false);
            $total = $usable->count();
            $maintenance = $desks->where('is_maintenance', true)->count();

            $slotRows = $slots->map(fn (TimeSlot $slot) => [
                'slot_id' => $slot->id,
                'slot_name' => $slot->name,
                'occupied' => $total > 0 ? count(array_intersect($usable->pluck('id')->all(), $activeBookings[$slot->id] ?? [])) : 0,
                'pct' => 0,
            ])->map(function ($row) use ($total) {
                $row['pct'] = $total > 0 ? round($row['occupied'] / $total * 100) : 0;

                return $row;
            })->all();

            return [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'zone_code' => $zone->code,
                'total' => $total,
                'maintenance' => $maintenance,
                'slots' => $slotRows,
            ];
        })->values()->all();
    }

    /**
     * à¸ªà¸–à¸´à¸•à¸´à¸£à¸²à¸¢à¸§à¸±à¸™à¹ƒà¸™à¸Šà¹ˆà¸§à¸‡à¸§à¸±à¸™à¸—à¸µà¹ˆ (à¸—à¸¸à¸à¸§à¸±à¸™ à¸¡à¸µ row à¹à¸¡à¹‰à¹„à¸¡à¹ˆà¸¡à¸µà¸‚à¹‰à¸­à¸¡à¸¹à¸¥)
     *
     * @return Collection<int, array{date:string, bookings:int, confirmed:int, checked_in:int, checked_out:int, cancelled:int, expired:int, arrived:int, no_show:int, rate:int}>
     */
    public function dailyStats(string $from, string $to): Collection
    {
        $rows = Booking::query()
            ->where('booking_date', '>=', $from)
            ->where('booking_date', '<=', $to)
            ->selectRaw('booking_date')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'confirmed' then 1 else 0 end) as confirmed")
            ->selectRaw("sum(case when status = 'checked_in' then 1 else 0 end) as checked_in")
            ->selectRaw("sum(case when status = 'checked_out' then 1 else 0 end) as checked_out")
            ->selectRaw("sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->selectRaw("sum(case when status = 'expired' then 1 else 0 end) as expired")
            ->groupBy('booking_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->booking_date)->toDateString());

        $days = $rows->keys();

        $days = collect();
        $cursor = Carbon::parse($from)->startOfDay();
        $lastDay = Carbon::parse($to)->startOfDay();

        for (; $cursor->lte($lastDay); $cursor->addDay()) {
            $days->push($cursor->copy());
        }

        return $days->map(function (Carbon $day) use ($rows) {
            $key = $day->toDateString();
            $r = $rows[$key] ?? null;

            $bookings = (int) ($r->total ?? 0);
            $arrived = (int) ($r->checked_in ?? 0) + (int) ($r->checked_out ?? 0);

            return [
                'date' => $key,
                'label' => $day->format('d/m'),
                'bookings' => $bookings,
                'confirmed' => (int) ($r->confirmed ?? 0),
                'checked_in' => (int) ($r->checked_in ?? 0),
                'checked_out' => (int) ($r->checked_out ?? 0),
                'cancelled' => (int) ($r->cancelled ?? 0),
                'expired' => (int) ($r->expired ?? 0),
                'arrived' => $arrived,
                'no_show' => $bookings > 0 ? (int) ($r->expired ?? 0) : 0,
                'rate' => $bookings > 0 ? round($arrived / $bookings * 100) : 0,
            ];
        })->values();
    }

    /**
     * à¸ªà¸–à¸´à¸•à¸´à¸£à¸²à¸¢à¹€à¸”à¸·à¸­à¸™ (à¸£à¸§à¸¡à¸ˆà¸²à¸à¸£à¸²à¸¢à¸§à¸±à¸™)
     *
     * @return Collection<int, array{month:string, label:string, bookings:int, arrived:int, cancelled:int, expired:int, rate:int}>
     */
    public function monthlyStats(string $from, string $to): Collection
    {
        return $this->dailyStats($from, $to)
            ->groupBy(fn ($row) => substr($row['date'], 0, 7))
            ->map(function (Collection $rows, string $month) {
                $label = Carbon::parse($month.'-01')->format('M Y');
                $bookings = $rows->sum('bookings');
                $arrived = $rows->sum('arrived');

                return [
                    'month' => $month,
                    'label' => $label,
                    'bookings' => $bookings,
                    'arrived' => $arrived,
                    'cancelled' => $rows->sum('cancelled'),
                    'expired' => $rows->sum('expired'),
                    'rate' => $bookings > 0 ? round($arrived / $bookings * 100) : 0,
                ];
            })
            ->values();
    }

    /**
     * à¸à¸´à¸ˆà¸à¸£à¸£à¸¡à¸¥à¹ˆà¸²à¸ªà¸¸à¸” (booking/check-in/checkout) à¸ªà¸³à¸«à¸£à¸±à¸š feed à¸šà¸™ dashboard
     *
     * @return Collection<int, array{booking_id:int, employee:string, department:string|null, zone:string, desk:string, date:string, slot:string, status:string, action_at:?\Carbon\CarbonInterface, cancel_reason:string|null}>
     */
    public function recentActivity(int $limit = 10): Collection
    {
        return Booking::query()
            ->with(['user.department', 'desk.zone', 'timeSlot'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Booking $b) {
                $actionAt = $b->checked_in_at ?? $b->checked_out_at ?? $b->updated_at;

                return [
                    'booking_id' => $b->id,
                    'employee' => $b->user->name,
                    'department' => $b->user->department?->name,
                    'zone' => $b->desk->zone->name,
                    'desk' => $b->desk->code,
                    'date' => $b->bookingDate->format('d/m/Y'),
                    'slot' => $b->timeSlot->name,
                    'status' => $b->status,
                    'action_at' => $actionAt,
                    'cancel_reason' => $b->cancel_reason,
                ];
            });
    }
}
