<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            การจองของฉัน
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-50 border border-red-300 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">ประวัติการจองทั้งหมดของคุณ</p>
                <a href="{{ route('dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← กลับไปแผนผังที่นั่ง</a>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    @php
                        $badge = match ($booking->status) {
                            \App\Models\Booking::STATUS_CONFIRMED => ['รอเช็คอิน', 'bg-indigo-100 text-indigo-700'],
                            \App\Models\Booking::STATUS_CHECKED_IN => ['กำลังใช้งาน', 'bg-sky-100 text-sky-700'],
                            \App\Models\Booking::STATUS_CHECKED_OUT => ['เช็คเอาต์แล้ว', 'bg-gray-100 text-gray-600'],
                            \App\Models\Booking::STATUS_CANCELLED => ['ยกเลิกแล้ว', 'bg-red-100 text-red-600'],
                            default => ['หมดอายุ', 'bg-red-100 text-red-600'],
                        };
                        $now = now();
                        $canCheckin = $booking->status === \App\Models\Booking::STATUS_CONFIRMED
                            && $now->gte($booking->starts_at->subMinutes((int) config('booking.early_checkin_minutes', 60)))
                            && $now->lte($booking->starts_at->addMinutes((int) config('booking.late_grace_minutes', 60)));
                    @endphp
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-800">โต๊ะ {{ $booking->desk->code }}</span>
                                <span class="text-sm text-gray-500">{{ $booking->desk->zone->name }}</span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                {{ $booking->booking_date->format('D d/m/Y') }} ·
                                {{ $booking->timeSlot->name }}
                                ({{ $booking->starts_at->format('H:i') }} - {{ $booking->ends_at->format('H:i') }} น.)
                            </div>
                            @if ($booking->status === \App\Models\Booking::STATUS_EXPIRED)
                                <div class="text-xs text-red-500 mt-0.5">ถูกยกเลิกอัตโนมัติเพราะเช็คอินสายเกินกำหนด</div>
                            @endif
                        </div>

                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>

                        <div class="flex items-center gap-2">
                            @if ($canCheckin)
                                <a href="{{ route('bookings.checkin', $booking) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition">เช็คอินด้วยรูปถ่าย</a>
                            @elseif ($booking->status === \App\Models\Booking::STATUS_CHECKED_IN)
                                <form action="{{ route('bookings.checkout', $booking) }}" method="POST">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">เช็คเอาต์</button>
                                </form>
                            @endif

                            @if ($booking->checkin_photo_path && in_array($booking->status, [\App\Models\Booking::STATUS_CHECKED_IN, \App\Models\Booking::STATUS_CHECKED_OUT]))
                                <a href="{{ route('bookings.photo', $booking) }}" target="_blank" rel="noopener" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition">ดูภาพเช็คอิน</a>
                            @endif

                            @if ($booking->status === \App\Models\Booking::STATUS_CONFIRMED)
                                <form action="{{ route('bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('ยกเลิกการจองโต๊ะนี้?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition">ยกเลิก</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-gray-400">ยังไม่มีการจอง</div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>
</x-app-layout>