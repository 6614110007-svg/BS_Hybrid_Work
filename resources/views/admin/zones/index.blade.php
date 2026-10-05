@extends('layouts.admin')

@section('title', 'จัดการโซนพื้นที่')

@section('content')
    <div x-data="zoneLiveCounts" data-live-url="{{ route('admin.zones.realtime') }}">

        <div class="mb-4 flex items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-xs text-gray-500">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                จำนวนโต๊ะอัปเดตอัตโนมัติทุก 15 วินาที
            </p>

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
                        <th class="w-40 px-4 py-3">รูปปก</th>
                        <th class="px-4 py-3">ชื่อโซน</th>
                        <th class="px-4 py-3">คำบรรยาย</th>
                        <th class="px-4 py-3">จำนวนโต๊ะทั้งหมด</th>
                        <th class="px-4 py-3 text-right">จัดการ</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($zones as $zone)
                        <tr>
                            <td class="px-4 py-3 align-top font-mono text-xs text-gray-500">{{ $zone->zone_id }}</td>

                            <td class="px-4 py-3 align-top">
                                @if ($zone->imageUrl())
                                    <img src="{{ $zone->imageUrl() }}" alt="รูปปกโซน {{ $zone->zone_name }}"
                                         loading="lazy"
                                         class="h-20 w-32 rounded-lg object-cover">
                                @else
                                    <div class="flex h-20 w-32 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 text-[10px] text-gray-400">
                                        ไม่มีรูปปก
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top font-medium text-gray-800">{{ $zone->zone_name }}</td>

                            <td class="px-4 py-3 align-top text-gray-600">
                                @if ($zone->zone_description)
                                    <span class="line-clamp-3">{{ $zone->zone_description }}</span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top text-gray-600">
                                <span data-zone-desks="{{ $zone->zone_id }}"
                                      data-previous-count="{{ $zone->desks_count }}"
                                      class="transition-colors duration-500">{{ $zone->desks_count }} โต๊ะ</span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.zones.edit', $zone) }}"
                                       class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        แก้ไข
                                    </a>
                                    <form method="POST" action="{{ route('admin.zones.destroy', $zone) }}"
                                          data-confirm="โซน {{ $zone->zone_name }} จะถูกลบถาวร รวมถึงรูปปกและข้อมูลคำบรรยาย ยืนยันหรือไม่?"
                                          data-confirm-title="ลบโซนพื้นที่">
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
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">ยังไม่มีโซนพื้นที่</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection