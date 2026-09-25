@extends('layouts.admin')

@section('title', 'จัดการโซนพื้นที่')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">แบ่งพื้นที่การทำงานเป็นโซนสำหรับแผนผังที่นั่ง</p>
        <a href="{{ route('admin.zones.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + เพิ่มโซน
        </a>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3">ลำดับ</th>
                    <th class="px-4 py-3">รหัส / ชื่อโซน</th>
                    <th class="px-4 py-3">แผนกที่สังกัด</th>
                    <th class="px-4 py-3 text-center">ชั้น</th>
                    <th class="px-4 py-3 text-center">โต๊ะ</th>
                    <th class="px-4 py-3 text-center">สถานะ</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($zones as $zone)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ $zone->sort_order }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $zone->name }}</p>
                            <p class="text-xs text-gray-500">{{ $zone->code ?? '-' }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $zone->department?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $zone->floor ?? '-' }}</td>
                        <td class="px-4 py-3 text-center text-gray-600">{{ $zone->desks_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($zone->is_active)
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">ใช้งาน</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">ปิด</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.zones.edit', $zone) }}"
                                   class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">แก้ไข</a>
                                <form method="POST"
                                      action="{{ route('admin.zones.destroy', $zone) }}"
                                      onsubmit="return confirm('ยืนยันการลบโซนนี้?');">
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
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">ยังไม่มีข้อมูลโซนพื้นที่</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection