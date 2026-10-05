@extends('layouts.admin')

@section('title', $employee ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน')

@section('content')
    <form method="POST"
          action="{{ $employee ? route('admin.employees.update', $employee) : route('admin.employees.store') }}"
          class="max-w-3xl rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        @if ($employee)
            @method('PUT')
        @endif

        @unless ($employee)
            <div class="mb-5 rounded-lg border border-indigo-100 bg-indigo-50/70 p-3">
                <p class="text-sm text-indigo-900">
                    <span class="font-semibold">รหัสพนักงานถัดไป:</span>
                    <span class="font-mono font-semibold">{{ $nextEmployeeId }}</span>
                    <span class="text-xs text-indigo-700">
                        (ระบบออกรหัสอัตโนมัติ รูปแบบ EMP + ปี 2 หลัก + เดือน 2 หลัก + ลำดับ 4 หลัก)
                    </span>
                </p>
                <p class="mt-1 text-xs text-indigo-700">
                    พนักงานใหม่จะเข้าสู่ระบบครั้งแรกแล้วต้องตั้งค่าบัญชี (ผูกอีเมล เปลี่ยนรหัสผ่าน เลือก avatar) ก่อนใช้งานได้
                </p>
            </div>
        @endunless

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="employee_fullname" value="ชื่อ-นามสกุล" required />
                <x-text-input id="employee_fullname" name="employee_fullname"
                              :value="old('employee_fullname', $employee?->employee_fullname)" required class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('employee_fullname')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="employee_tel" value="เบอร์โทรศัพท์" required />
                <x-text-input id="employee_tel" name="employee_tel" inputmode="numeric" pattern="0[0-9]{9}"
                              :value="old('employee_tel', $employee?->employee_tel)" required maxlength="10"
                              placeholder="0812345678" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('employee_tel')" class="mt-1" />
                <p class="mt-1 text-xs text-gray-500">ต้องเป็นตัวเลข 10 หลัก และขึ้นต้นด้วย 0</p>
            </div>

            <div>
                <x-input-label for="employee_email" value="อีเมล (ใช้เข้าสู่ระบบ)" required />
                <x-text-input id="employee_email" name="employee_email" type="email"
                              :value="old('employee_email', $employee?->employee_email)" required class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('employee_email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="department_id" value="แผนก" required />
                <select id="department_id" name="department_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <option value="">-- เลือกแผนก --</option>
                    @foreach ($departments as $option)
                        <option value="{{ $option->department_id }}"
                                @selected(old('department_id', $employee?->department_id) === $option->department_id)>
                            {{ $option->department_name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="employee_role" value="บทบาท" required />
                <select id="employee_role" name="employee_role" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($roles as $key => $label)
                        <option value="{{ $key }}"
                                @selected(old('employee_role', $employee?->employee_role ?? \App\Models\Employee::ROLE_EMPLOYEE) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('employee_role')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="employee_status" value="สถานะ" required />
                <select id="employee_status" name="employee_status" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}"
                                @selected(old('employee_status', $employee?->employee_status ?? \App\Models\Employee::STATUS_ACTIVE) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('employee_status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password"
                               value="{{ $employee ? 'รหัสผ่านใหม่ (เว้นว่างไว้ = ไม่เปลี่ยน)' : 'รหัสผ่านเริ่มต้น' }}"
                               :required="! $employee" />
                <x-text-input id="password" name="password" type="password" autocomplete="new-password"
                              :required="! $employee" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="ยืนยันรหัสผ่าน" :required="! $employee" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                              autocomplete="new-password" :required="! $employee" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500">
            รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร และถูกเก็บแบบ hash ในฐานข้อมูล
        </p>

        @if ($employee)
            <div class="mt-4">
                <x-input-label for="employee_avatar" value="Avatar ปัจจุบัน" />
                <div class="mt-1 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-white">
                        {!! \App\Support\AnimalAvatar::svg($employee->avatarKey(), 'h-12 w-12') !!}
                    </span>
                    <div class="text-sm">
                        <p class="font-medium text-gray-800">{{ \App\Support\AnimalAvatar::label($employee->avatarKey()) }}</p>
                        <p class="text-xs text-gray-500">
                            พนักงานเปลี่ยน avatar เองได้ในหน้าตั้งค่าบัญชี
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-5 flex items-center gap-3">
            <x-primary-button>{{ $employee ? 'บันทึกการแก้ไข' : 'เพิ่มพนักงาน' }}</x-primary-button>
            <a href="{{ route('admin.employees.index') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection