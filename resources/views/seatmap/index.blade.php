@extends('layouts.app')

@section('title', 'ค้นหาและจองโต๊ะ')

@section('content')
    <div x-data="bookingFilters(@js($bookingPicker))">
        <form method="GET" action="{{ route('dashboard') }}" data-booking-filters
              @submit.prevent="search()"
              class="mb-6 grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            {{-- ค่าที่ส่งไปยังเซิร์ฟเวอร์มาจาก state ของ Alpine เสมอ ไม่พึ่ง DOM ของปฏิทิน
     แต่ใส่ค่าเริ่มต้นจากฝั่งเซิร์ฟเวอร์ไว้ด้วย เผื่อผู้ใช้กด "ค้นหา" ก่อน Alpine จะโหลดเสร็จ --}}
            <input type="hidden" name="date" value="{{ $date->toDateString() }}" :value="date">
            <input type="hidden" name="time_slot" value="{{ $slot->name }}" :value="slot">

            <div>
                <label for="booking-date" class="mb-1 block text-xs font-medium text-gray-600">วันที่ต้องการเข้าใช้งาน</label>
                <div class="booking-date">
                    <input id="booking-date" type="text" x-ref="datePicker" autocomplete="off"
                           readonly
                           :aria-busy="loading"
                           class="booking-picker__input"
                           placeholder="คลิกเพื่อเลือกวันที่" />
                    <span class="booking-date__icon" aria-hidden="true">📅</span>
                </div>
                <p class="mt-1 text-[11px] leading-4 text-gray-500">
                    คลิกที่ช่องหรือไอคอนปฏิทินเพื่อเลือก ช่วงวันที่ {{ $bookingPicker['leadDays'] }} วันข้างหน้า
                </p>
            </div>

            <div>
                <span class="mb-1 block text-xs font-medium text-gray-600">ช่วงเวลา</span>
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="ช่วงเวลาที่จอง">
                    <template x-for="item in slots" :key="item.name">
                        <button type="button" role="radio" :aria-checked="slot === item.name"
                                :disabled="isSlotDisabled(item) || loading"
                                @click="selectSlot(item.name)"
                                class="booking-pill"
                                :class="{
                                    'booking-pill--active': slot === item.name && ! isSlotDisabled(item),
                                    'booking-pill--disabled': isSlotDisabled(item),
                                }"
                                x-text="slotLabel(item)"></button>
                    </template>
                </div>
                <p class="mt-1 text-[11px] leading-4 text-amber-600"
                   x-show="slots.some((item) => isSlotDisabled(item))"
                   x-cloak>
                    วันนี้เลยเวลา {{ $bookingPicker['switchAfter'] }} น. แล้ว เลือกได้เฉพาะรอบที่ยังเหลือเวลา
                </p>
            </div>

            <div>
                <label for="zone_id" class="mb-1 block text-xs font-medium text-gray-600">โซน</label>
                <select id="zone_id" name="zone_id" x-model="zoneId" @change="search()"
                        :disabled="loading"
                        class="w-full rounded-xl border-gray-300 text-sm">
                    <option value="">ทุกโซน</option>
                    @foreach ($allZones as $option)
                        <option value="{{ $option->zone_id }}">{{ $option->zone_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                        data-loading-button :data-busy="loading">
                    <span x-show="!loading">ค้นหา</span>
                    <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                        <span class="app-spinner"></span> กำลังโหลด
                    </span>
                </button>
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    ล้างตัวกรอง
                </a>
            </div>
        </form>

        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-600">
            <span class="inline-flex items-center gap-1.5">
                <span class="booking-legend__disabled"></span>
                เสาร์-อาทิตย์ / วันหยุดนักขัตฤกษ์ (เลือกไม่ได้)
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="booking-legend__today"></span>
                วันนี้
            </span>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-600">
            <span class="font-medium text-gray-700">สถานะโต๊ะ:</span>
            @foreach ($stateMeta as $meta)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}
                </span>
            @endforeach
        </div>

        <div data-ajax-region :data-busy="loading">
            @include('seatmap.partial')
        </div>
    </div>
@endsection

{{-- ยืนยันก่อนจอง: สรุปโต๊ะ/โซน/วันที่/ช่วงเวลา ป้องกันผู้ใช้กดผิด --}}
<div x-data="bookingConfirm()" x-cloak x-show="open" @keydown.escape.window="close()"
     class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="booking-confirm-title">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/70" @click="close()"></div>

    <div x-show="open" x-transition.scale.origin.center
         class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 id="booking-confirm-title" class="font-semibold text-gray-800">ยืนยันการจองโต๊ะ</h2>
            <p class="mt-0.5 text-xs text-gray-500">ตรวจสอบรายละเอียดก่อนกดยืนยัน</p>
        </div>

        <dl class="space-y-2.5 px-5 py-4 text-sm">
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-xs text-gray-500">โต๊ะ</dt>
                <dd class="font-semibold text-gray-900" x-text="details.desk || '-'"></dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-xs text-gray-500">โซน</dt>
                <dd class="font-medium text-gray-800" x-text="details.zone || '-'"></dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-xs text-gray-500">วันที่</dt>
                <dd class="font-medium text-gray-800" x-text="details.date || '-'"></dd>
            </div>
            <div class="flex items-baseline justify-between gap-3">
                <dt class="text-xs text-gray-500">ช่วงเวลา</dt>
                <dd class="font-medium text-gray-800" x-text="details.slot || '-'"></dd>
            </div>
        </dl>

        <div class="flex gap-2 border-t border-gray-200 px-5 py-4">
            <button type="button" @click="close()"
                    class="flex-1 rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                ยกเลิก
            </button>
            <button type="button" @click="confirm()"
                    class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                ยืนยันการจอง
            </button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        window.SEATMAP_STATUS_URL = @json(route('seatmap.status'));
        window.SEATMAP_PARTIAL_URL = @json(route('seatmap.partial'));
        window.SEATMAP_META = @json($stateMeta);

        /**
         * Modal ยืนยันก่อนส่งฟอร์มจอง
         *
         * ดักการ submit แบบ capture เพื่อหยุดการส่งจริงก่อน แล้วค่อย submit ซ้ำเมื่อผู้ใช้ยืนยัน
         */
        function bookingConfirm() {
            return {
                open: false,
                details: {},
                pending: null,
                // ปล่อยให้ submit ครั้งถัดไปทะลุผ่านไปได้ ไม่ต้องถูกดักซ้ำ
                releasing: false,

                init() {
                    document.addEventListener('submit', (event) => {
                        const form = event.target;

                        if (!(form instanceof HTMLFormElement) || !form.matches('[data-booking-form]')) {
                            return;
                        }

                        // ปล่อยให้ submit จริงทะลุไปได้หนึ่งครั้งหลังผู้ใช้กด "ยืนยัน"
                        if (this.releasing) {
                            this.releasing = false;

                            return;
                        }

                        // ป้องกัน submit ซ้ำระหว่างที่ modal ยังเปิดอยู่ (เช่น ผู้ใช้กดปุ่มจองสองครั้งติดกัน)
                        if (this.open || this.pending) {
                            event.preventDefault();

                            return;
                        }

                        event.preventDefault();

                        this.details = {
                            desk: form.dataset.deskNumber ?? '',
                            zone: form.dataset.zoneName ?? '',
                            date: form.dataset.bookingDate ?? '',
                            slot: form.dataset.timeSlot ?? '',
                        };

                        this.pending = form;
                        this.open = true;
                    }, true);
                },

                close() {
                    this.open = false;
                    this.pending = null;
                    this.releasing = false;
                },

                confirm() {
                    const form = this.pending;

                    this.open = false;
                    this.pending = null;
                    this.releasing = true;

                    if (form) {
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    }
                },
            };
        }
    </script>
@endpush
