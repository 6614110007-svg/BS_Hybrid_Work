@extends('layouts.app')

@section('title', 'การจองของฉัน')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">รายการจองโต๊ะของคุณทั้งหมด</p>
        <a href="{{ route('dashboard') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            ค้นหาโต๊ะ
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">วันที่</th>
                    <th class="px-4 py-3">ช่วงเวลา</th>
                    <th class="px-4 py-3">โซน / โต๊ะ</th>
                    <th class="px-4 py-3">สถานะ</th>
                    <th class="px-4 py-3">เช็คอิน / เอาต์</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $booking->booking_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $booking->time_slot }}
                            <span class="block text-xs text-gray-400">{{ $booking->start_time }} - {{ $booking->end_time }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $booking->desk->zone->zone_name }}
                            <span class="block text-xs font-medium text-gray-800">{{ $booking->desk->desk_number }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ \App\Models\Booking::statusBadge($booking->booking_status) }}">
                                {{ $booking->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            เข้า: {{ $booking->actual_checkin_time?->format('d/m H:i') ?? '-' }}<br>
                            ออก: {{ $booking->actual_checkout_time?->format('d/m H:i') ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($booking->isReserved())
                                    <a href="{{ route('bookings.checkin', $booking) }}"
                                       class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                        เช็คอิน
                                    </a>

                                    <form method="POST" action="{{ route('bookings.destroy', $booking) }}"
                                          onsubmit="return confirm('ยกเลิกการจองนี้หรือไม่?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                            ยกเลิก
                                        </button>
                                    </form>
                                @elseif ($booking->isCheckedIn())
                                    <a href="{{ route('bookings.photo', $booking) }}"
                                       class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        ดูรูป
                                    </a>

                                    <form method="POST" action="{{ route('bookings.checkout', $booking) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                            เช็คเอาต์
                                        </button>
                                    </form>
                                @elseif ($booking->checkin_photo)
                                    <a href="{{ route('bookings.photo', $booking) }}"
                                       class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        ดูรูป
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">
                            ยังไม่มีการจองโต๊ะ
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
@endsection
