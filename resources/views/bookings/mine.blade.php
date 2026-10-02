@extends('layouts.app')

@section('title', 'การจองของฉัน')

@section('content')
    {{-- ทั้งตารางและ lightbox ต้องอยู่ใน x-data เดียวกัน เพื่อให้ปุ่ม "ดูรูป" ที่อยู่ในตารางส่ง event ได้ --}}
    <div x-data="photoLightbox()">
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
                            @php($overdue = $booking->isCheckinOverdue())
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $overdue ? 'bg-slate-200 text-slate-500' : \App\Models\Booking::statusBadge($booking->booking_status) }}">
                                {{ $overdue ? 'หมดเวลา (Expired)' : $booking->statusLabel() }}
                            </span>
                            @if ($overdue)
                                <span class="mt-1 block text-[11px] text-gray-400">
                                    เลยเวลาเช็คอิน {{ $booking->checkinDeadline()->format('d/m H:i') }} น.
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            เข้า: {{ $booking->actual_checkin_time?->format('d/m H:i') ?? '-' }}<br>
                            ออก: {{ $booking->actual_checkout_time?->format('d/m H:i') ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @if ($overdue)
                                    <span class="cursor-not-allowed rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-400"
                                          title="เลยเวลาเช็คอินแล้ว กรุณาเลือกโต๊ะใหม่">
                                        เช็คอินไม่ได้แล้ว
                                    </span>

                                    <form method="POST" action="{{ route('bookings.destroy', $booking) }}"
                                          onsubmit="return confirm('ยกเลิกการจองนี้หรือไม่?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                            ยกเลิก
                                        </button>
                                    </form>
                                @elseif ($booking->isReserved())
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
                                    <button type="button" @click="$dispatch('open-photo', { id: @js($booking->booking_id) })"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        ดูรูป
                                    </button>

                                    <form method="POST" action="{{ route('bookings.checkout', $booking) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                            เช็คเอาต์
                                        </button>
                                    </form>
                                @elseif ($booking->checkin_photo)
                                    <button type="button" @click="$dispatch('open-photo', { id: @js($booking->booking_id) })"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        ดูรูป
                                    </button>
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

    {{-- ปุ่ม "ดูรูป" ทั้งหมดเปิด lightbox เดียวกัน (ข้อมูลถูกส่งมาเป็น JSON) --}}
    <script type="application/json" id="checkin-photo-data">
        @json($photoViews)
    </script>

    <div data-photo-lightbox x-cloak x-show="open" @keydown.escape.window="close()"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6"
         role="dialog" aria-modal="true" aria-labelledby="photo-lightbox-title">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/80"
             @click="close()"></div>

        <div x-show="open" x-transition.scale.origin.center
             class="relative z-10 flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

            <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 id="photo-lightbox-title" class="font-semibold text-gray-800">รูปเช็คอิน</h2>
                    <p class="mt-0.5 text-xs text-gray-500" x-text="current ? current.desk_label : ''"></p>
                </div>
                <button type="button" @click="close()"
                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                        aria-label="ปิด">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 px-5 py-4">
                <template x-if="current">
                    <div>
                        <img :src="current.url" :alt="'รูปเช็คอินของ ' + current.employee_name"
                             class="mx-auto max-h-[52vh] w-auto rounded-xl border border-gray-200 bg-white object-contain shadow-sm" />

                        <dl class="mt-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-gray-500">ชื่อ-นามสกุลพนักงาน</dt>
                                <dd class="font-medium text-gray-800" x-text="current.employee_name"></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">แผนก</dt>
                                <dd class="font-medium text-gray-800" x-text="current.department"></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">เวลาที่กดเช็คอิน</dt>
                                <dd class="font-medium text-gray-800" x-text="current.checked_in_at"></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">วันที่จอง / ช่วงเวลา</dt>
                                <dd class="font-medium text-gray-800" x-text="current.slot_label"></dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs text-gray-500">โจทย์เซลฟี่ที่ได้รับ</dt>
                                <dd class="font-medium text-gray-800" x-text="current.prompt || 'ไม่มีข้อมูลโจทย์'"></dd>
                            </div>
                        </dl>
                    </div>
                </template>
            </div>

            <div class="flex justify-end border-t border-gray-200 px-5 py-3">
                <button type="button" @click="close()"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                    ปิด
                </button>
            </div>
        </div>
    </div>
    </div>
@endsection

@push('scripts')
    <script>
        /**
         * Lightbox สำหรับดูรูปเช็คอิน
         *
         * ข้อมูลทั้งหมดถูกฝังมากับหน้าแบบ JSON เพื่อให้เปิดดูได้ทันที
         * ไม่ต้องต่อ URL ใหม่ และแสดงรายละเอียดของใบจองครบถ้วน
         */
        function photoLightbox() {
            return {
                open: false,
                current: null,

                init() {
                    let items = [];

                    try {
                        items = JSON.parse(document.getElementById('checkin-photo-data')?.textContent ?? '[]');
                    } catch (error) {
                        items = [];
                    }

                    this.items = Array.isArray(items) ? items : [];

                    this.$watch('open', (value) => {
                        document.body.classList.toggle('overflow-hidden', value);
                    });

                    // ปุ่ม "ดูรูป" อยู่นอก x-data root ของ lightbox
                    // จึงต้องรับ event ที่ระดับ document แทน this.$root
                    document.addEventListener('open-photo', (event) => {
                        const found = this.items.find((item) => item.id === event.detail?.id);

                        if (found) {
                            this.current = found;
                            this.open = true;
                        }
                    });
                },

                close() {
                    this.open = false;
                },
            };
        }
    </script>
@endpush
