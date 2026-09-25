@extends('layouts.admin')

@section('title', 'จัดการโต๊ะทำงาน')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">กำหนดพิกัดและสถานะโต๊ะทำงานในแต่ละโซน</p>
        <a href="{{ route('admin.desks.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + เพิ่มโต๊ะ
        </a>
    </div>

    <div class="mt-4 space-y-6">
        @forelse ($zones as $zone)
            <div class="rounded-xl bg-white shadow-sm border border-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ $zone->name }}</h2>
                        <p class="text-xs text-gray-500">
                            {{ $zone->department?->name ?? 'ไม่มีแผนก' }} · ชั้น {{ $zone->floor ?? '-' }} ·
                            โต๊ะ {{ $zone->desks->count() }} ตัว
                        </p>
                    </div>
                    <div class="flex gap-3 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-100 border border-indigo-500"></span> พร้อมใช้งาน</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-amber-100 border border-amber-500"></span> ปิดปรับปรุง</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-gray-100 border border-dashed border-gray-400"></span> ปิดใช้งาน</span>
                    </div>
                </div>

                <div class="p-5">
                    <x-seat-map-preview :zone="$zone" />
                </div>

                <div class="overflow-x-auto border-t border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2.5">รหัสโต๊ะ</th>
                                <th class="px-4 py-2.5">ชื่อ</th>
                                <th class="px-4 py-2.5 text-center">พิกัด (X, Y)</th>
                                <th class="px-4 py-2.5 text-center">สถานะ</th>
                                <th class="px-4 py-2.5 text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($zone->desks as $desk)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-mono font-medium text-gray-800">{{ $desk->code }}</td>
                                    <td class="px-4 py-2.5 text-gray-600">{{ $desk->label ?? '-' }}</td>
                                    <td class="px-4 py-2.5 text-center font-mono text-gray-500">{{ $desk->x }}, {{ $desk->y }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if (! $desk->is_active)
                                            <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600">ปิดใช้งาน</span>
                                        @elseif ($desk->is_maintenance)
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">ปิดปรับปรุง</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">พร้อมใช้งาน</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.desks.edit', $desk) }}"
                                               class="rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">แก้ไข</a>
                                            <form method="POST"
                                                  action="{{ route('admin.desks.destroy', $desk) }}"
                                                  onsubmit="return confirm('ยืนยันการลบโต๊ะนี้?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="rounded-lg border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50">ลบ</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">ยังไม่มีข้อมูลโซน โปรดเพิ่มโซนพื้นที่ก่อนสร้างโต๊ะทำงาน</p>
            </div>
        @endforelse
    </div>
@endsection