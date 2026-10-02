@extends('layouts.app')

@section('title', 'เช็คอินเข้าใช้โต๊ะ')

@section('content')
    <div class="mx-auto max-w-2xl space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="mb-3 font-semibold text-gray-800">รายละเอียดการจอง</h2>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-gray-500">พนักงาน</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->employee->employee_fullname }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">แผนก</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->employee->department?->department_name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">วันที่</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->booking_date->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">ช่วงเวลา</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->time_slot }} ({{ $booking->start_time }} - {{ $booking->end_time }})</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">โซน</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->desk->zone->zone_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">โต๊ะ</dt>
                    <dd class="font-medium text-gray-800">{{ $booking->desk->desk_number }}</dd>
                </div>
            </dl>

            <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                ต้องเช็คอินก่อนเวลา {{ $deadline->format('H:i') }} น. มิฉะนั้นระบบจะยกเลิกใบจองนี้ให้อัตโนมัติ
                @if ($isWalkIn)
                    <span class="mt-1 block text-amber-700">
                        ใบจองนี้ทำรายการหลังเวลาเริ่มรอบ ระบบจึงนับเวลา {{ config('booking.late_grace_minutes') }}
                        นาทีจากเวลาที่กดจอง
                    </span>
                @endif
            </p>
        </section>

        <form method="POST" action="{{ route('bookings.checkin.store', $booking) }}"
              enctype="multipart/form-data" data-loading-form
              class="rounded-xl border border-gray-200 bg-white p-5">
            @csrf

            <h2 class="mb-3 font-semibold text-gray-800">ถ่ายรูปเซลฟี่เพื่อยืนยัน</h2>

            <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4" data-challenge>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-xl" aria-hidden="true">📸</span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-500">
                            โจทย์สุ่มของคุณ · <span data-challenge-label>{{ $challenge['label'] }}</span>
                        </p>
                        <p class="mt-1 text-sm font-semibold text-indigo-900" data-challenge-text>{{ $challenge['text'] }}</p>
                        <input type="hidden" name="selfie_prompt" data-challenge-input value="{{ $challenge['text'] }}">
                    </div>
                </div>
                <button type="button" data-challenge-refresh
                        class="mt-3 rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
                    สุ่มโจทย์ใหม่
                </button>
            </div>

            <p class="mb-3 text-xs text-gray-500">
                รูปจะถูกบันทึกไว้ในระบบเพื่อการตรวจสอบ รองรับไฟล์ {{ implode(', ', config('booking.checkin_photo_mimes')) }}
                ขนาดไม่เกิน {{ number_format((int) config('booking.checkin_photo_max_kb')) }} KB
            </p>

            <div class="mb-3">
                <label for="photo" class="mb-1 block text-xs font-medium text-gray-600">
                    แนบไฟล์รูป หรือถ่ายรูปตอนนี้
                </label>
                <input id="photo" type="file" name="photo" accept="image/*" capture="user" required
                       class="w-full rounded-lg border border-gray-300 p-2 text-sm" />
                <x-input-error :messages="$errors->get('photo')" class="mt-1" />
            </div>

            <div class="mb-4 rounded-lg bg-gray-50 p-3">
                <p class="mb-2 text-xs font-medium text-gray-600">ตัวอย่างรูป</p>
                <img src="" alt="ตัวอย่างรูปเซลฟี่" data-preview
                     class="hidden max-h-64 w-full rounded-lg border border-gray-200 object-cover" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button data-loading-button>ยืนยันเช็คอิน</x-primary-button>
                <a href="{{ route('bookings.mine') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        window.SELFIE_CHALLENGE = @json(\App\Support\SelfieChallenge::toArray());

        document.getElementById('photo')?.addEventListener('change', function () {
            const file = this.files?.[0];
            const preview = document.querySelector('[data-preview]');

            if (!file || !preview) {
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        });

        document.querySelector('[data-challenge-refresh]')?.addEventListener('click', function () {
            const box = document.querySelector('[data-challenge]');
            const data = window.SELFIE_CHALLENGE ?? {};
            const categories = Object.keys(data.prompts ?? {});

            if (!box || categories.length === 0) {
                return;
            }

            const category = categories[Math.floor(Math.random() * categories.length)];
            const items = data.prompts[category];
            const label = box.querySelector('[data-challenge-label]');
            const prompt = box.querySelector('[data-challenge-text]');
            const input = box.querySelector('[data-challenge-input]');
            const next = items[Math.floor(Math.random() * items.length)];

            if (label) {
                label.textContent = data.labels?.[category] ?? category;
            }

            if (prompt) {
                prompt.textContent = next;
            }

            // ซิงก์โจทย์ใหม่ให้ฟอร์ม เพื่อให้บันทึกตรงกับที่ผู้ใช้เห็นตอนเช็คอิน
            if (input) {
                input.value = next;
            }
        });
    </script>
@endpush
