<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Zone;
use App\Services\Analytics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Analytics $analytics): View
    {
        $filters = $this->validated($request);

        $date = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);
        $dateFrom = $date->copy()->startOfMonth();
        $dateTo = $to->copy()->endOfMonth();

        $summary = $this->summary($filters);

        return view('admin.reports.index', [
            'filters' => $filters,
            'date' => $date,
            'to' => $to,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $summary,
            'daily' => $analytics->dailyStats($dateFrom->toDateString(), $dateTo->toDateString()),
            'monthly' => $analytics->monthlyStats($dateFrom->toDateString(), $dateTo->toDateString()),
            'zoneOccupancy' => $analytics->zoneOccupancy($date->toDateString()),
            'zones' => Zone::orderBy('zone_name')->get(),
            'departments' => Department::orderBy('department_name')->get(),
            'statuses' => Booking::statusOptions(),
            'slots' => \App\Support\TimeSlot::all(),
        ]);
    }

    public function export(Request $request, Analytics $analytics): StreamedResponse
    {
        $filters = $this->validated($request);

        $date = Carbon::parse($filters['date_from']);
        $to = Carbon::parse($filters['date_to']);

        return response()->streamDownload(function () use ($filters, $date, $to) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'วันที่จอง', 'ช่วงเวลา', 'เวลาเริ่ม', 'เวลาสิ้นสุด',
                'ชื่อพนักงาน', 'แผนก', 'อีเมล', 'โทรศัพท์', 'บทบาท',
                'โซน', 'โต๊ะ', 'สถานะ', 'เวลาเช็คอิน', 'เวลาเช็คเอาต์', 'มีรูปเช็คอิน',
            ]);

            $this->rows($filters)->chunk(500)->each(function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['booking_date'],
                        $row['time_slot'],
                        $row['start_time'],
                        $row['end_time'],
                        $row['employee_fullname'],
                        $row['department_name'],
                        $row['employee_email'],
                        $row['employee_tel'],
                        $row['employee_role'],
                        $row['zone_name'],
                        $row['desk_number'],
                        $row['booking_status'],
                        $row['actual_checkin_time'],
                        $row['actual_checkout_time'],
                        $row['checkin_photo'] ? 'มี' : 'ไม่มี',
                    ]);
                }
            });

            fclose($handle);
        }, 'report-bookings-'.Carbon::today()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Booking>
     */
    private function query(array $filters)
    {
        return Booking::query()
            ->with(['employee.department', 'desk.zone'])
            ->whereDate('booking_date', '>=', Carbon::parse($filters['date_from'])->toDateString())
            ->whereDate('booking_date', '<=', Carbon::parse($filters['date_to'])->toDateString())
            ->when($filters['status'], fn ($q, $status) => $q->where('booking_status', $status))
            ->when($filters['time_slot'], fn ($q, $slot) => $q->where('time_slot', $slot))
            ->when($filters['zone_id'], fn ($q, $zone) => $q->whereHas('desk', fn ($d) => $d->where('zone_id', $zone)))
            ->when($filters['department_id'], fn ($q, $dept) => $q->whereHas(
                'employee',
                fn ($e) => $e->where('department_id', $dept)
            ))
            ->when($filters['search'], function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->whereHas('employee', fn ($e) => $e->where('employee_fullname', 'like', "%{$search}%"))
                        ->orWhereHas('employee', fn ($e) => $e->where('employee_email', 'like', "%{$search}%"))
                        ->orWhereHas('desk', fn ($d) => $d->where('desk_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->orderBy('booking_id');
    }

    private function rows(array $filters)
    {
        return $this->query($filters)->get()->map(fn (Booking $booking) => [
            'booking_id' => $booking->booking_id,
            'booking_date' => $booking->booking_date->format('d/m/Y'),
            'time_slot' => $booking->time_slot,
            'start_time' => $booking->start_time->format('H:i'),
            'end_time' => $booking->end_time->format('H:i'),
            'employee_fullname' => $booking->employee->employee_fullname,
            'employee_email' => $booking->employee->employee_email,
            'employee_tel' => $booking->employee->employee_tel,
            'employee_role' => $booking->employee->roleLabel(),
            'department_name' => $booking->employee->department?->department_name ?? '-',
            'zone_name' => $booking->desk->zone->zone_name,
            'desk_number' => $booking->desk->desk_number,
            'booking_status' => $booking->statusLabel(),
            'actual_checkin_time' => $booking->actual_checkin_time?->format('d/m/Y H:i'),
            'actual_checkout_time' => $booking->actual_checkout_time?->format('d/m/Y H:i'),
            'checkin_photo' => $booking->checkin_photo,
        ]);
    }

    /**
     * @return array{total:int, reserved:int, checked_in:int, completed:int, expired:int, rate:int}
     */
    private function summary(array $filters): array
    {
        $rows = $this->query($filters)
            ->get(['booking_status'])
            ->groupBy('booking_status');

        $count = fn (string $status) => $rows->get($status, collect())->count();
        $total = $rows->sum(fn ($group) => $group->count());
        $arrived = $count(Booking::STATUS_CHECKED_IN) + $count(Booking::STATUS_COMPLETED);

        return [
            'total' => $total,
            'reserved' => $count(Booking::STATUS_RESERVED),
            'checked_in' => $count(Booking::STATUS_CHECKED_IN),
            'completed' => $count(Booking::STATUS_COMPLETED),
            'expired' => $count(Booking::STATUS_EXPIRED),
            'rate' => $total > 0 ? round($arrived / $total * 100) : 0,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(array_keys(Booking::statusOptions()))],
            'time_slot' => ['nullable', 'string', Rule::in(\App\Support\TimeSlot::names())],
            'zone_id' => ['nullable', 'string', Rule::exists('zone', 'zone_id')],
            'department_id' => ['nullable', 'string', Rule::exists('department', 'department_id')],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $data['date_from'] = $data['date_from'] ?? Carbon::today()->subDays(29)->toDateString();
        $data['date_to'] = $data['date_to'] ?? Carbon::today()->toDateString();

        foreach (['status', 'time_slot', 'zone_id', 'department_id'] as $key) {
            $data[$key] = ($data[$key] ?? null) ?: null;
        }

        $data['search'] = trim((string) ($data['search'] ?? ''));

        return $data;
    }
}
