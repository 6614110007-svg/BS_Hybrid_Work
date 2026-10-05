@extends('layouts.app')

@section('title', 'โปรไฟล์ของฉัน')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        {{-- บัตรประจำตัว: avatar + ชื่อ-นามสกุล + แผนก + รหัสพนักงาน --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5">
                <h2 class="text-lg font-bold text-gray-900">โปรไฟล์ของฉัน</h2>
                <p class="mt-1 text-sm text-gray-600">
                    ข้อมูลพนักงานของคุณตามที่บริษัทลงทะเบียนไว้ และ avatar ที่คุณเลือกได้ตลอดเวลา
                </p>
            </div>

            <div class="flex flex-col items-center gap-5 px-6 py-6 sm:flex-row sm:items-start">
                <span class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-indigo-100 shadow"
                      data-profile-avatar-preview>
                    {!! \App\Support\AnimalAvatar::svg($employee->avatarKey(), 'h-24 w-24') !!}
                </span>

                <dl class="grid w-full gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">ชื่อ-นามสกุล</dt>
                        <dd class="mt-0.5 text-base font-semibold text-gray-900">{{ $employee->employee_fullname }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">แผนก</dt>
                        <dd class="mt-0.5 text-base text-gray-700">
                            {{ $employee->department?->department_name ?? '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">รหัสพนักงาน</dt>
                        <dd class="mt-0.5">
                            <span class="inline-block rounded-lg bg-gray-100 px-2.5 py-1 font-mono text-sm font-semibold tracking-wide text-gray-800">
                                {{ $employee->employee_id }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">โทรศัพท์</dt>
                        <dd class="mt-0.5 text-base text-gray-700">{{ $employee->employee_tel }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- อีเมล: เดิม (ที่บริษัทลงทะเบียน) เทียบกับปัจจุบัน (ที่ผูกไว้ตอนตั้งค่าบัญชี) --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">ข้อมูลอีเมล</h3>
                <p class="mt-0.5 text-xs text-gray-500">อีเมลใช้สำหรับเข้าสู่ระบบและติดต่อกลับโดยผู้ดูแลระบบ</p>
            </div>

            <dl class="grid gap-5 px-6 py-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">อีเมลเดิม (ที่บริษัทลงทะเบียน)</dt>
                    <dd class="mt-1 text-base text-gray-700">
                        @if ($employee->originalEmail())
                            {{ $employee->originalEmail() }}
                        @else
                            <span class="text-gray-400">ไม่มีข้อมูลเดิม</span>
                        @endif
                    </dd>
                    <p class="mt-0.5 text-xs text-gray-400">ข้อมูลนี้แก้ไขไม่ได้จากหน้านี้</p>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">อีเมลปัจจุบันที่ผูกไว้</dt>
                    <dd class="mt-1 text-base font-semibold text-gray-900">{{ $employee->employee_email }}</dd>
                    @if ($employee->hasChangedBoundEmail())
                        <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
                            ต่างจากอีเมลเดิมของบริษัท
                        </p>
                    @else
                        <p class="mt-0.5 text-xs text-gray-400">ตรงกับอีเมลเดิมของบริษัท</p>
                    @endif
                </div>
            </dl>
        </div>

        {{-- สรุปการจอง --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @php
                $statCards = [
                    ['label' => 'จองทั้งหมด', 'value' => $bookingStats['total'], 'class' => 'text-gray-900'],
                    ['label' => 'รอเช็คอิน', 'value' => $bookingStats['reserved'], 'class' => 'text-amber-600'],
                    ['label' => 'กำลังใช้งาน', 'value' => $bookingStats['checked_in'], 'class' => 'text-sky-600'],
                    ['label' => 'ใช้งานเสร็จ', 'value' => $bookingStats['completed'], 'class' => 'text-emerald-600'],
                ];
            @endphp

            @foreach ($statCards as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-center">
                    <p class="text-2xl font-bold {{ $card['class'] }}">{{ $card['value'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $card['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- เปลี่ยน avatar ได้ตลอดเวลา --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">เปลี่ยน avatar</h3>
                <p class="mt-0.5 text-xs text-gray-500">
                    เลือกได้ตลอดเวลา ไม่ต้องรอการตั้งค่าบัญชีครั้งแรก ปัจจุบันคุณใช้
                    <span class="font-semibold text-gray-700">{{ \App\Support\AnimalAvatar::label($employee->avatarKey()) }}</span>
                </p>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="px-6 py-5"
                  x-data="avatarPreview(@js($employee->avatarKey()))">
                @csrf
                @method('PUT')

                @include('components.avatar-picker', [
                    'animals' => $animals,
                    'name' => 'employee_avatar',
                    'selected' => $employee->avatarKey(),
                ])

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-5">
                    <a href="{{ route('password.edit') }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        เปลี่ยนรหัสผ่าน
                    </a>
                    <a href="{{ route('bookings.mine') }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        การจองของฉัน
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        บันทึก avatar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- เก็บ SVG ของ avatar ทุกตัวไว้ฝั่งเซิร์ฟเวอร์ เพื่อให้พรีวิวขนาดใหญ่เป็นรูปจริง
         โดยไม่ต้องเขียนการวาด SVG ซ้ำใน JavaScript --}}
    <template data-avatar-svg-map>
        @foreach ($animals as $avatarKey => $avatarMeta)
            <div data-avatar-svg="{{ $avatarKey }}">{!! \App\Support\AnimalAvatar::svg($avatarKey, 'h-24 w-24') !!}</div>
        @endforeach
    </template>

    <script>
        /**
         * พรีวิว avatar ขนาดใหญ่ให้ตรงกับตัวที่เลือก โดยไม่ต้องกดบันทึก
         *
         * คอมโพเนนต์ avatar-picker เก็บค่าที่เลือกไว้ใน input hidden ชื่อ employee_avatar
         * และยิง event 'avatar-selected' ทุกครั้งที่กดเลือก จึงฟัง event นั้นเพื่อวาดใหม่
         * (ฟัง 'input' ไว้ด้วย เผื่อมีการเปลี่ยนค่าจากทางอื่น)
         */
        function avatarPreview(initial) {
            return {
                render(key) {
                    const target = document.querySelector('[data-profile-avatar-preview]');
                    // SVG ทั้งหมดเก็บใน <template> ซึ่ง document.querySelector มองไม่เห็น
                    // ต้องค้นผ่าน .content ของ template จึงจะเจอรูปที่ต้องการ
                    const map = document.querySelector('[data-avatar-svg-map]');
                    const source = map?.content.querySelector(`[data-avatar-svg="${CSS.escape(key)}"]`);

                    if (!target || !source) {
                        return;
                    }

                    target.innerHTML = source.innerHTML;
                },

                init() {
                    this.$nextTick(() => {
                        this.render(document.querySelector('[name="employee_avatar"]')?.value || initial);
                    });

                    document.addEventListener('avatar-selected', (event) => {
                        if (event.target?.closest('[name="employee_avatar"]') || event.target?.name === 'employee_avatar') {
                            return;
                        }

                        this.render(event.detail);
                    });

                    document.addEventListener('input', (event) => {
                        if (event.target?.name === 'employee_avatar') {
                            this.render(event.target.value);
                        }
                    });
                },
            };
        }
    </script>
@endpush