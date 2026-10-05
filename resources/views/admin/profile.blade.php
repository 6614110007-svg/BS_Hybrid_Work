@extends('layouts.admin')

@section('title', 'โปรไฟล์ผู้ดูแลระบบ')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        {{-- บัตรประจำตัว: avatar + ชื่อที่แสดง + บทบาท (แก้ไขไม่ได้) --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5">
                <h2 class="text-lg font-bold text-gray-900">โปรไฟล์ผู้ดูแลระบบ</h2>
                <p class="mt-1 text-sm text-gray-600">
                    ปรับแต่งชื่อที่แสดงและรูปประจำตัว ส่วนบทบาทและสิทธิ์กำหนดโดยระบบ
                </p>
            </div>

            <div class="flex flex-col items-center gap-5 px-6 py-6 sm:flex-row sm:items-start">
                <span class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-indigo-100 shadow">
                    {!! \App\Support\AnimalAvatar::svg($admin->avatarKey(), 'h-24 w-24') !!}
                </span>

                <dl class="grid w-full gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">ชื่อที่แสดง</dt>
                        <dd class="mt-0.5 text-base font-semibold text-gray-900">{{ $admin->displayName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">ชื่อ-นามสกุลจริง</dt>
                        <dd class="mt-0.5 text-base text-gray-700">{{ $admin->admin_fullname }}</dd>
                        <p class="mt-0.5 text-xs text-gray-400">แก้ไขได้จากฐานข้อมูลบุคลากรเท่านั้น</p>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">อีเมล</dt>
                        <dd class="mt-0.5 text-base text-gray-700">{{ $admin->admin_email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">บทบาท</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                                </svg>
                                {{ $admin->roleLabel() }}
                            </span>
                            <p class="mt-0.5 text-xs text-gray-400">บทบาทนี้แก้ไขไม่ได้</p>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- ฟอร์มแก้ไขชื่อที่แสดงและเลือก avatar --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-900">แก้ไขข้อมูลที่แสดง</h3>
            </div>

            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-6 px-6 py-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="admin_display_name" class="block text-sm font-medium text-gray-700">
                        ชื่อที่แสดง
                    </label>
                    <input id="admin_display_name" name="admin_display_name" type="text" maxlength="100"
                           value="{{ old('admin_display_name', $admin->admin_display_name) }}"
                           placeholder="{{ $admin->admin_fullname }}"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('admin_display_name')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">
                        เว้นว่างไว้เพื่อแสดงชื่อ-นามสกุลจริง ({{ $admin->admin_fullname }})
                    </p>
                </div>

                @include('components.avatar-picker', [
                    'animals' => $animals,
                    'name' => 'admin_avatar',
                    'selected' => $admin->avatarKey(),
                ])

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-5">
                    <a href="{{ route('password.edit') }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        เปลี่ยนรหัสผ่าน
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        บันทึกโปรไฟล์
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection