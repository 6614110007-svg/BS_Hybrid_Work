@extends('layouts.admin')

@section('title', 'จัดการพนักงาน')

@section('content')
    <form method="GET" action="{{ route('admin.employees.index') }}"
          class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="search" class="mb-1 block text-xs font-medium text-gray-600">ค้นหา</label>
            <input id="search" type="search" name="search" value="{{ request('search') }}"
                   placeholder="ชื่อ / อีเมล / เบอร์โทร" class="w-full rounded-lg border-gray-300 text-sm" />
        </div>

        <div>
            <label for="role" class="mb-1 block text-xs font-medium text-gray-600">บทบาท</label>
            <select id="role" name="role" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($roles as $key => $label)
                    <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-gray-600">สถานะ</label>
            <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                กรอง
            </button>
            <a href="{{ route('admin.employees.create') }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                เพิ่ม
            </a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">รหัส</th>
                    <th class="px-4 py-3">ชื่อ-นามสกุล</th>
                    <th class="px-4 py-3">แผนก</th>
                    <th class="px-4 py-3">อีเมล / โทรศัพท์</th>
                    <th class="px-4 py-3">บทบาท</th>
                    <th class="px-4 py-3">สถานะ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $employee->employee_id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $employee->employee_fullname }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $employee->department?->department_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $employee->employee_email }}
                            <span class="block text-gray-400">{{ $employee->employee_tel }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $employee->roleLabel() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $employee->isActive() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                {{ $employee->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.employees.status', $employee) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        {{ $employee->isActive() ? 'ระงับ' : 'เปิดใช้งาน' }}
                                    </button>
                                </form>

                                <a href="{{ route('admin.employees.edit', $employee) }}"
                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    แก้ไข
                                </a>

                                <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}"
                                      onsubmit="return confirm('ลบพนักงานคนนี้หรือไม่?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                        ลบ
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">ไม่พบพนักงาน</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>
@endsection
