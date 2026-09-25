@extends('layouts.admin')

@section('title', 'จัดการพนักงาน')

@section('content')
    @if ($tempPassword)
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4">
            <p class="text-sm font-semibold text-green-800">
                สร้างบัญชีพนักงาน <span class="font-mono">{{ $createdEmployee }}</span> เรียบร้อยแล้ว
            </p>
            <p class="mt-1 text-sm text-green-700">
                รหัสผ่านชั่วคราว: <span class="font-mono font-bold">{{ $tempPassword }}</span>
            </p>
            <p class="mt-1 text-xs text-green-600">
                กรุณาส่งรหัสผ่านนี้ให้พนักงาน (แสดงเพียงครั้งเดียว) พนักงานจะต้องเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก
            </p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">สร้างบัญชีพนักงานด้วยอีเมลจริง (ไม่มีบัญชีที่สมัครเอง)</p>
        <a href="{{ route('admin.employees.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + เพิ่มพนักงาน
        </a>
    </div>

    <form method="GET" action="{{ route('admin.employees.index') }}"
          class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div>
            <x-text-input class="w-full" name="search" :value="$filters['search'] ?? ''"
                placeholder="ค้นหาชื่อ / อีเมล / รหัสพนักงาน" />
        </div>
        <div>
            <select name="department_id"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">ทุกแผนก</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>
                        {{ $dept->name }}
                    </option>
                @endforeach
                <option value="none" @selected(($filters['department_id'] ?? '') == 'none')>ไม่มีแผนก</option>
            </select>
        </div>
        <div>
            <select name="status"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">ทุกสถานะ</option>
                <option value="active" @selected(($filters['status'] ?? '') == 'active')>ใช้งาน (Active)</option>
                <option value="inactive" @selected(($filters['status'] ?? '') == 'inactive')>ระงับ (Inactive)</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">ค้นหา</button>
            <a href="{{ route('admin.employees.index') }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">ล้าง</a>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3">พนักงาน</th>
                    <th class="px-4 py-3">รหัสพนักงาน</th>
                    <th class="px-4 py-3">แผนก</th>
                    <th class="px-4 py-3 text-center">สถานะ</th>
                    <th class="px-4 py-3 text-center">รหัสชั่วคราว</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($employees as $employee)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $employee->name }}</p>
                            <p class="text-xs text-gray-500">{{ $employee->email }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $employee->employee_code ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $employee->department?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($employee->isActive())
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">ใช้งาน</span>
                            @else
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">ถูกระงับ</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($employee->must_change_password)
                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">ยังไม่ได้เปลี่ยน</span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.employees.edit', $employee) }}"
                                   class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">แก้ไข</a>
                                <form method="POST"
                                      action="{{ route('admin.employees.toggle-status', $employee) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="rounded-lg border px-3 py-1 text-xs font-medium
                                                {{ $employee->isActive()
                                                    ? 'border-red-200 text-red-600 hover:bg-red-50'
                                                    : 'border-green-200 text-green-600 hover:bg-green-50' }}">
                                        {{ $employee->isActive() ? 'ระงับ' : 'เปิดใช้งาน' }}
                                    </button>
                                </form>
                                <form method="POST"
                                      action="{{ route('admin.employees.destroy', $employee) }}"
                                      onsubmit="return confirm('ยืนยันการลบพนักงาน {{ $employee->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">ไม่พบพนักงาน</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $employees->links() }}
    </div>
@endsection