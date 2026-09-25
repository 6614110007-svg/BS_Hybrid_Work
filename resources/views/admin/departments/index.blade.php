@extends('layouts.admin')

@section('title', 'จัดการแผนก')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">เพิ่ม/แก้ไขแผนกขององค์กร</p>
        <a href="{{ route('admin.departments.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + เพิ่มแผนก
        </a>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3">รหัส</th>
                    <th class="px-4 py-3">ชื่อแผนก</th>
                    <th class="px-4 py-3">รายละเอียด</th>
                    <th class="px-4 py-3 text-center">พนักงาน</th>
                    <th class="px-4 py-3 text-center">โซน</th>
                    <th class="px-4 py-3 text-center">สถานะ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($departments as $department)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">
                            {{ $department->code ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $department->name }}</td>
                        <td class="px-4 py-3 max-w-[16rem] truncate text-gray-500">
                            {{ $department->description ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $department->employees_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $department->zones_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($department->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">ใช้งาน</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">ปิด</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.departments.edit', $department) }}"
                                   class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">แก้ไข</a>
                                <form method="POST"
                                      action="{{ route('admin.departments.destroy', $department) }}"
                                      onsubmit="return confirm('ยืนยันการลบแผนกนี้?');">
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
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">ยังไม่มีข้อมูลแผนก</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection