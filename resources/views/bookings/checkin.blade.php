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
            </p>
        </section>

        <form method="POST" action="{{ route('bookings.checkin.store', $booking) }}"
              enctype="multipart/form-data" class="rounded-xl border border-gray-200 bg-white p-5">
            @csrf

            <h2 class="mb-3 font-semibold text-gray-800">ถ่ายรูปเซลฟี่เพื่อยืนยัน</h2>

            <p class="mb-3 text-xs text-gray-500">
                รูปจะถูกบันทึกไว้ในระบบเพื่อการตรวจสอบ รองรับไฟล์ {{ implode(', ', config('booking.checkin_photo_mimes')) }}
                ขนาดไม่เกิน {{ number_format((int) config('booking.checkin_photo_max_kb')) }} KB
            </p>

            <div class="mb-3">
                <input id="photo" type="file" name="photo" accept="image/*" required
                       class="w-full rounded-lg border border-gray-300 p-2 text-sm" />
                <x-input-error :messages="$errors->get('photo')" class="mt-1" />
            </div>

            <div class="mb-4 rounded-lg bg-gray-50 p-3">
                <p class="mb-2 text-xs font-medium text-gray-600">ตัวอย่างรูป</p>
                <img src="" alt="ตัวอย่างรูปเซลฟี่" data-preview
                     class="hidden max-h-64 w-full rounded-lg border border-gray-200 object-cover" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>ยืนยันเช็คอิน</x-primary-button>
                <a href="{{ route('bookings.mine') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('photo')?.addEventListener('change', function () {
            const file = this.files?.[0];
            const preview = document.querySelector('[data-preview]');

            if (!file || !preview) {
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
        });
    </script>
@endpush
