@extends('layouts.admin')

@section('title', 'รายการจองโต๊ะทั้งหมด')

@section('content')
    <form method="GET" action="{{ route('admin.bookings.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-gray-600">สถานะ</label>
            <select id="status" name="status" class="rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            กรอง
        </button>

        <a href="{{ route('admin.bookings.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            ล้างตัวกรอง
        </a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">รหัส</th>
                    <th class="px-4 py-3">พนักงาน</th>
                    <th class="px-4 py-3">โซน / โต๊ะ</th>
                    <th class="px-4 py-3">วันที่ / ช่วงเวลา</th>
                    <th class="px-4 py-3">สถานะ</th>
                    <th class="px-4 py-3">รูป</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $booking->booking_id }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $booking->employee->employee_fullname }}</p>
                            <p class="text-xs text-gray-500">{{ $booking->employee->department?->department_name ?? '-' }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $booking->desk->zone->zone_name }}
                            <span class="block text-xs font-medium text-gray-800">{{ $booking->desk->desk_number }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $booking->booking_date->format('d/m/Y') }}
                            <span class="block text-xs text-gray-500">{{ $booking->time_slot }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ \App\Models\Booking::statusBadge($booking->booking_status) }}">
                                {{ $booking->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($booking->checkin_photo)
                                <a href="{{ route('bookings.photo', $booking) }}"
                                   class="font-semibold text-indigo-700 underline">ดูรูป</a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($booking->isCancelable())
                                <form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}"
                                      data-confirm="ยกเลิกการจองของ {{ $booking->employee?->employee_fullname ?? 'พนักงาน' }} วันที่ {{ $booking->booking_date->format('d/m/Y') }} เวลา {{ $booking->start_time }}-{{ $booking->end_time }} แทนพนักงานหรือไม่?"
                                      data-confirm-title="ยกเลิกการจองแทนพนักงาน"
                                      data-confirm-text="ยกเลิกการจอง">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                        ยกเลิก
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">ไม่พบรายการจอง</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
@endsection
