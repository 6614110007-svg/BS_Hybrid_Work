@extends('layouts.admin')

@section('title', 'จัดการโซนพื้นที่')

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.zones.create') }}"
           class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            เพิ่มโซน
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">รหัส</th>
                    <th class="px-4 py-3">ชื่อโซน</th>
                    <th class="px-4 py-3">จำนวนโต๊ะทั้งหมด</th>
                    <th class="px-4 py-3 text-right">จัดการ</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
                @forelse ($zones as $zone)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $zone->zone_id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $zone->zone_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $zone->desks_count }} โต๊ะ</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.zones.edit', $zone) }}"
                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    แก้ไข
                                </a>
                                <form method="POST" action="{{ route('admin.zones.destroy', $zone) }}"
                                      onsubmit="return confirm('ลบโซนนี้หรือไม่?');">
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
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">ยังไม่มีโซนพื้นที่</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
