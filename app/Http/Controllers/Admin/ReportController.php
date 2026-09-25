<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Zone;
use App\Services\Analytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Analytics $analytics): View
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->toDateString()
            : Carbon::today()->subDays(29)->toDateString();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->toDateString()
            : Carbon::today()->toDateString();

        $daily = $analytics->dailyStats($from, $to);
        $monthly = $analytics->monthlyStats($from, $to);

        $bookings = $this->bookingQuery($request)
            ->paginate(20)
            ->withQueryString();

        $totals = [
            'bookings' => $daily->sum('bookings'),
            'arrived' => $daily->sum('arrived'),
            'cancelled' => $daily->sum('cancelled'),
            'expired' => $daily->sum('expired'),
            'rate' => $daily->sum('bookings') > 0
                ? round($daily->sum('arrived') / $daily->sum('bookings') * 100)
                : 0,
        ];

        return view('admin.reports.index', [
            'daily' => $daily,
            'monthly' => $monthly,
            'totals' => $totals,
            'bookings' => $bookings,
            'zoneOccupancy' => $analytics->zoneOccupancy($to),
            'departments' => Department::active()->orderBy('name')->get(),
            'zones' => Zone::active()->orderBy('sort_order')->get(),
            'statuses' => $this->statusOptions(),
            'from' => $from,
            'to' => $to,
            'f' => $request->query(),
        ]);
    }

    public function export(Request $request, Analytics $analytics): StreamedResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->toDateString() : '2000-01-01';
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->toDateString() : Carbon::today()->toDateString();

        $rows = $this->bookingQuery($request->merge(['from' => $from, 'to' => $to]))->get();

        $filename = 'bookings-report-'.$from.'_to_'.$to.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($rows, $from, $to, $analytics) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel (Thai)

            fputcsv($out, ['à¸£à¸«à¸±à¸ªà¸žà¸™à¸±à¸à¸‡à¸²à¸™', 'à¸Šà¸·à¹ˆà¸­-à¸™à¸²à¸¡à¸ªà¸à¸¸à¸¥', 'à¹à¸œà¸™à¸', 'à¹‚à¸‹à¸™à¸žà¸·à¹‰à¸™à¸—à¸µà¹ˆ', 'à¹‚à¸•à¹Šà¸°', 'à¸§à¸±à¸™à¸—à¸µà¹ˆà¸ˆà¸­à¸‡', 'à¸Šà¹ˆà¸§à¸‡à¹€à¸§à¸¥à¸²', 'à¹€à¸£à¸´à¹ˆà¸¡-à¸ªà¸´à¹‰à¸™à¸ªà¸¸à¸”', 'à¸ªà¸–à¸²à¸™à¸°', 'à¹€à¸§à¸¥à¸²à¹€à¸Šà¹‡à¸„à¸­à¸´à¸™', 'à¹€à¸§à¸¥à¸²à¹€à¸Šà¹‡à¸„à¹€à¸­à¸²à¸•à¹Œ', 'à¸ªà¸²à¹€à¸«à¸•à¸¸à¸¢à¸à¹€à¸¥à¸´à¸']);

            foreach ($rows as $b) {
                fputcsv($out, [
                    $b->user->employee_code ?? '',
                    $b->user->name,
                    $b->user->department?->name ?? '',
                    $b->desk->zone->name ?? '',
                    $b->desk->code,
                    $b->bookingDate->format('d/m/Y'),
                    $b->timeSlot->name ?? '',
                    $b->starts_at->format('H:i').' - '.$b->ends_at->format('H:i'),
                    $this->statusLabel($b->status),
                    $b->checked_in_at?->format('d/m/Y H:i'),
                    $b->checked_out_at?->format('d/m/Y H:i'),
                    match ($b->cancel_reason) {
                        Booking::CANCEL_BY_USER => 'à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸¢à¸à¹€à¸¥à¸´à¸',
                        Booking::CANCEL_AUTO_LATE => 'à¹€à¸¥à¸¢à¹€à¸§à¸¥à¸²à¹€à¸Šà¹‡à¸„à¸­à¸´à¸™',
                        default => '',
                    },
                ]);
            }

            fclose($out);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    private function bookingQuery(Request $request)
    {
        return Booking::query()
            ->with(['user.department', 'desk.zone', 'timeSlot'])
            ->when($request->query('from'), fn ($q, $from) => $q->where('booking_date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('booking_date', '<=', $to))
            ->when($request->query('status') && $request->query('status') !== '', fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('slot'), fn ($q, $slot) => $q->where('time_slot_id', (int) $slot))
            ->when($request->query('zone'), function ($q, $zone) {
                $q->whereIn('desk_id', Desk::where('zone_id', (int) $zone)->pluck('id'));
            })
            ->when($request->query('department'), function ($q, $department) {
                $q->whereIn('user_id', User::where('department_id', (int) $department)->pluck('id'));
            })
            ->when($request->query('search'), function ($q, $search) {
                $q->whereIn('user_id', User::whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('lower(employee_code) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('lower(email) like ?', ['%'.mb_strtolower($search).'%'])
                    ->pluck('id'));
            })
            ->orderByDesc('booking_date')
            ->orderByDesc('id');
    }

    private function statusOptions(): array
    {
        return [
            Booking::STATUS_CONFIRMED => 'à¸£à¸­à¹€à¸Šà¹‡à¸„à¸­à¸´à¸™',
            Booking::STATUS_CHECKED_IN => 'à¸à¸³à¸¥à¸±à¸‡à¹ƒà¸Šà¹‰à¸‡à¸²à¸™',
            Booking::STATUS_CHECKED_OUT => 'à¹€à¸Šà¹‡à¸„à¹€à¸­à¸²à¸•à¹Œà¹à¸¥à¹‰à¸§',
            Booking::STATUS_CANCELLED => 'à¸¢à¸à¹€à¸¥à¸´à¸à¹à¸¥à¹‰à¸§',
            Booking::STATUS_EXPIRED => 'à¸«à¸¡à¸”à¸­à¸²à¸¢à¸¸ (à¸ªà¸²à¸¢)',
        ];
    }

    private function statusLabel(string $status): string
    {
        return $this->statusOptions()[$status] ?? $status;
    }
}
