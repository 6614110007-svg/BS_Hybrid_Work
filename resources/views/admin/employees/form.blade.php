@extends('layouts.admin')

@section('title', $user ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน')

@section('content')
    @php $isEdit = (bool) $user; @endphp

    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm border border-gray-200">
        <form method="POST"
              action="{{ $isEdit ? route('admin.employees.update', $user) : route('admin.employees.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="name" :value="__('ชื่อ-นามสกุล')" />
                    <x-text-input id="name" class="mt-1 w-full" name="name" :value="old('name', $user?->name)"
                        required placeholder="ชื่อเต็มของพนักงาน" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="employee_code" :value="__('รหัสพนักงาน')" />
                    <x-text-input id="employee_code" class="mt-1 w-full" name="employee_code"
                        :value="old('employee_code', $user?->employee_code)" placeholder="เช่น EMP-002" />
                    <x-input-error :messages="$errors->get('employee_code')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="email" :value="__('อีเมล (ใช้สำหรับเข้าสู่ระบบ)')" />
                <x-text-input id="email" class="mt-1 w-full" type="email" name="email"
                    :value="old('email', $user?->email)" required placeholder="อีเมลจริงของพนักงาน" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="department_id" :value="__('แผนก')" />
                    <select id="department_id" name="department_id"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- ไม่ระบุแผนก --</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(old('department_id', $user?->department_id) == $dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="phone" :value="__('เบอร์โทร')" />
                    <x-text-input id="phone" class="mt-1 w-full" name="phone" :value="old('phone', $user?->phone)"
                        placeholder="เบอร์ติดต่อ" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="role" :value="__('บทบาท')" />
                <select id="role" name="role" required
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="employee" @selected(old('role', $user?->role ?? 'employee') == 'employee')>พนักงาน (Employee)</option>
                    <option value="admin" @selected(old('role', $user?->role) == 'admin')>ผู้ดูแลระบบ (Admin)</option>
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>

            @if ($isEdit)
                <p class="mt-4 rounded-lg bg-blue-50 border border-blue-200 p-3 text-xs text-blue-700">
                    หลังการแก้ไข จะใช้รหัสผ่านเดิมต่อไป ไม่มีการสร้างรหัสชั่วคราวใหม่
                </p>
            @else
                <p class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700">
                    ระบบจะสร้างรหัสผ่านชั่วคราวให้อัตโนมัติและแสดงเพียงครั้งเดียวหลังบันทึก พนักงานต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก
                </p>
            @endif

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.employees.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">ยกเลิก</a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $isEdit ? 'บันทึกการแก้ไข' : 'สร้างบัญชีพนักงาน' }}
                </button>
            </div>
        </form>
    </div>
@endsection