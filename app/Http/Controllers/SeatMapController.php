<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeatMapController extends Controller
{
    public function index(): View
    {
        $date = Carbon::today()->format('Y-m-d');
        $slot = $this->currentSlot();
        $zones = Zone::with(['desks' => fn ($q) => $q->orderBy('code')])->orderBy('sort_order')->get();
        $slots = TimeSlot::active()->ordered()->get();
        $activeBookings = Booking::active()
            ->forDate($date)
            ->where('user_id', auth()->id())
            ->with(['desk', 'timeSlot'])
            ->get();
        $notifications = auth()->user()->notifications()->limit(10)->get();

        $init = [
            'date' => $date,
            'slotId' => $slot?->id,
            'minDate' => $date,
            'maxDate' => Carbon::today()->addDays((int) config('booking.lead_days', 14))->format('Y-m-d'),
            'slots' => $slots->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'start' => $s->start_time, 'end' => $s->end_time])->values(),
            'zones' => $zones->map(fn ($z) => [
                'id' => $z->id,
                'code' => $z->code,
                'name' => $z->name,
                'department' => $z->department->name ?? null,
                'floor' => $z->floor,
                'desks' => $z->desks->map(fn ($d) => ['id' => $d->id, 'code' => $d->code, 'label' => $d->label, 'x' => $d->x, 'y' => $d->y])->values(),
            ])->values(),
            'statuses' => collect($this->statusFor($date, $slot?->id))->mapWithKeys(
                fn ($s) => [$s['id'] => ['state' => $s['state'], 'booking_id' => $s['booking_id'], 'mine' => $s['mine']]]
            ),
            'activeBookings' => $activeBookings->map(fn ($b) => [
                'id' => $b->id,
                'desk_id' => $b->desk_id,
                'desk_code' => $b->desk->code,
                'slot' => $b->timeSlot->name,
                'status' => $b->status,
            ])->values(),
            'notifications' => $notifications->map(fn ($n) => [
                'data' => $n->data,
                'created_at' => $n->created_at->diffForHumans(),
                'read' => $n->read_at !== null,
            ])->values(),
            'unreadCount' => (int) auth()->user()->unreadNotifications()->count(),
            'alert' => [
                'success' => session('success'),
                'error' => session('error'),
                'info' => session('info'),
            ],
        ];

        return view('seatmap.index', compact('init'));
    }

    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'integer', 'exists:time_slots,id'],
        ]);

        return response()->json([
            'desks' => $this->statusFor($request->date, $request->integer('slot')),
            'activeBookings' => Booking::active()
                ->forDate($request->date)
                ->where('user_id', auth()->id())
                ->with(['desk', 'timeSlot'])
                ->get()
                ->map(fn (Booking $b) => $b->only(['id', 'desk_id', 'time_slot_id', 'status'])),
        ]);
    }

    /**
     * Resolve a slot start time (local APAC time) to a Carbon instance.
     */
    public static function slotDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time);
    }

    private function currentSlot(): ?TimeSlot
    {
        $now = Carbon::now();
        $slots = TimeSlot::active()->ordered()->get();

        foreach ($slots as $slot) {
            $start = Carbon::parse($slot->start_time);
            $end = Carbon::parse($slot->end_time);

            if ($now->gte($start) && $now->lt($end)) {
                return $slot;
            }
        }

        $started = $slots->filter(fn ($s) => $now->gte(Carbon::parse($s->start_time)));

        return $started->isEmpty() ? $slots->first() : $started->last();
    }

    /**
     * Build per-desk state for a given date + slot.
     *
     * @return array<int, array{id:int, code:string, label:string, x:int, y:int, zone_id:int, state:string, booking_id:int|null, mine:bool}>
     */
    private function statusFor(string $date, ?int $slotId): array
    {
        $userId = auth()->id();

        $active = Booking::active()
            ->forDate($date)
            ->when($slotId, fn ($q) => $q->where('time_slot_id', $slotId))
            ->get(['id', 'user_id', 'desk_id', 'status'])
            ->keyBy('desk_id');

        return Desk::query()
            ->with('zone')
            ->get()
            ->map(function (Desk $desk) use ($active, $userId) {
                $booking = $active->get($desk->id);

                if ($desk->isOutOfService()) {
                    return $this->row($desk, 'maintenance', null, false);
                }

                if (! $booking) {
                    return $this->row($desk, 'available', null, false);
                }

                $mine = $booking->user_id === $userId;

                $state = $booking->status === Booking::STATUS_CHECKED_IN
                    ? ($mine ? 'my_in_use' : 'in_use')
                    : (in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
                        ? ($mine ? 'my_booked' : 'booked')
                        : 'available');

                return $this->row($desk, $state, $booking->id, $mine);
            })
            ->values()
            ->all();
    }

    /**
     * @return array{id:int, code:string, label:string, x:int, y:int, zone_id:int, state:string, booking_id:int|null, mine:bool}
     */
    private function row(Desk $desk, string $state, ?int $bookingId, bool $mine): array
    {
        return [
            'id' => $desk->id,
            'code' => $desk->code,
            'label' => $desk->label,
            'x' => $desk->x,
            'y' => $desk->y,
            'zone_id' => $desk->zone_id,
            'state' => $state,
            'booking_id' => $bookingId,
            'mine' => $mine,
        ];
    }
}