@extends('layouts.admin')

@section('title', 'รายงานและสถิติการใช้งาน')

@section('content')
    <div x-data="{ tab: 'daily' }">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div>
                    <label for="from" class="block text-xs font-medium text-gray-600">จากวันที่</label>
                    <input type="date" name="from" value="{{ $from }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="to" class="block text-xs font-medium text-gray-600">ถึงวันที่</label>
                    <input type="date" name="to" value="{{ $to }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-600">สถานะ</label>
                    <select name="status" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทั้งหมด</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($f['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="search" class="block text-xs font-medium text-gray-600">ค้นหาพนักงาน</label>
                    <input type="text" name="search" value="{{ $f['search'] ?? '' }}" placeholder="ชื่อ / รหัส / อีเมล"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="slot" class="block text-xs font-medium text-gray-600">ช่วงเวลา</label>
                    <select name="slot" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทั้งหมด</option>
                        @foreach (\App\Models\TimeSlot::active()->ordered()->get() as $slot)
                            <option value="{{ $slot->id }}" @selected(($f['slot'] ?? '') == $slot->id)>{{ $slot->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="zone" class="block text-xs font-medium text-gray-600">โซนพื้นที่</label>
                    <select name="zone" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทั้งหมด</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected(($f['zone'] ?? '') == $zone->id)>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="department" class="block text-xs font-medium text-gray-600">แผนก</label>
                    <select name="department" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">ทั้งหมด</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(($f['department'] ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 flex items-end gap-2">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        ค้นหา
                    </button>
                    <a href="{{ route('admin.reports.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        ล้างค่า
                    </a>
                    <a href="{{ route('admin.reports.export', request()->query()) }}"
                       class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Export CSV
                    </a>
                </div>
            </div>
        </form>

        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">การจองทั้งหมด</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($totals['bookings']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">มาถึงจริง (เช็คอิน)</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ number_format($totals['arrived']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">ยกเลิกโดยผู้ใช้</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ number_format($totals['cancelled']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">หมดอายุ (ไม่มา)</p>
                <p class="mt-1 text-3xl font-bold text-red-600">{{ number_format($totals['expired']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-500">อัตรามาใช้จริง</p>
                <p class="mt-1 text-3xl font-bold text-indigo-600">{{ $totals['rate'] }}%</p>
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">แนวโน้มการใช้งาน</h2>
                <div class="inline-flex rounded-lg border border-gray-300 p-0.5">
                    <button type="button" @click="tab = 'daily'"
                            class="rounded-md px-3 py-1 text-sm font-medium" :class="tab === 'daily' ? 'bg-indigo-600 text-white' : 'text-gray-600'">
                        รายวัน
                    </button>
                    <button type="button" @click="tab = 'monthly'"
                            class="rounded-md px-3 py-1 text-sm font-medium" :class="tab === 'monthly' ? 'bg-indigo-600 text-white' : 'text-gray-600'">
                        รายเดือน
                    </button>
                </div>
            </div>

            <div x-show="tab === 'daily'" class="mt-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="py-2 pr-4">วันที่</th>
                            <th class="py-2 pr-4">จองทั้งหมด</th>
                            <th class="py-2 pr-4">เช็คอิน</th>
                            <th class="py-2 pr-4">ยกเลิก</th>
                            <th class="py-2 pr-4">หมดอายุ</th>
                            <th class="py-2 pr-4 w-1/3">อัตราเช็คอิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daily as $row)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 font-medium text-gray-800">{{ \Carbon\Carbon::parse($row['date'])->translatedFormat('j M Y') }}</td>
                                <td class="py-2 pr-4">{{ $row['bookings'] }}</td>
                                <td class="py-2 pr-4 text-emerald-600">{{ $row['arrived'] }}</td>
                                <td class="py-2 pr-4 text-amber-600">{{ $row['cancelled'] }}</td>
                                <td class="py-2 pr-4 text-red-600">{{ $row['expired'] }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full {{ ($row['rate'] >= 70 ? 'bg-emerald-500' : ($row['rate'] >= 40 ? 'bg-amber-500' : 'bg-rose-500')) }}"
                                                 style="width: {{ $row['rate'] }}%"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs text-gray-500">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-gray-500">ไม่มีข้อมูลในช่วงวันที่นี้</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab === 'monthly'" x-cloak class="mt-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="py-2 pr-4">เดือน</th>
                            <th class="py-2 pr-4">จองทั้งหมด</th>
                            <th class="py-2 pr-4">เช็คอิน</th>
                            <th class="py-2 pr-4">ยกเลิก</th>
                            <th class="py-2 pr-4">หมดอายุ</th>
                            <th class="py-2 pr-4 w-1/3">อัตราเช็คอิน</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monthly as $row)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 font-medium text-gray-800">{{ $row['label'] }}</td>
                                <td class="py-2 pr-4">{{ $row['bookings'] }}</td>
                                <td class="py-2 pr-4 text-emerald-600">{{ $row['arrived'] }}</td>
                                <td class="py-2 pr-4 text-amber-600">{{ $row['cancelled'] }}</td>
                                <td class="py-2 pr-4 text-red-600">{{ $row['expired'] }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full {{ ($row['rate'] >= 70 ? 'bg-emerald-500' : ($row['rate'] >= 40 ? 'bg-amber-500' : 'bg-rose-500')) }}"
                                                 style="width: {{ $row['rate'] }}%"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs text-gray-500">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-gray-500">ไม่มีข้อมูลในช่วงนี้</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (count($zoneOccupancy))
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-800">ความหนาแน่นรายโซน — วันที่สิ้นสุดช่วง ({{ \Carbon\Carbon::parse($to)->translatedFormat('j M Y') }})</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th class="py-2 pr-4">โซนพื้นที่</th>
                                <th class="py-2 pr-4">โต๊ะใช้ได้</th>
                                @foreach (\App\Models\TimeSlot::active()->ordered()->get() as $slot)
                                    <th class="py-2 pr-4">{{ $slot->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zoneOccupancy as $zone)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2 pr-4 font-medium text-gray-800">{{ $zone['zone_name'] }}</td>
                                    <td class="py-2 pr-4 text-gray-500">
                                        {{ $zone['total'] }}
                                        @if ($zone['maintenance'] > 0)
                                            <span class="text-xs text-amber-600">(ซ่อม {{ $zone['maintenance'] }})</span>
                                        @endif
                                    </td>
                                    @foreach ($zone['slots'] as $slotRow)
                                        <td class="py-2 pr-4">
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-24 rounded-full bg-gray-200">
                                                    <div class="h-2 rounded-full {{ ($slotRow['pct'] >= 80 ? 'bg-rose-500' : ($slotRow['pct'] >= 50 ? 'bg-amber-500' : 'bg-emerald-500')) }}"
                                                         style="width: {{ $slotRow['pct'] }}%"></div>
                                                </div>
                                                <span class="text-xs text-gray-500">{{ $slotRow['occupied'] }}/{{ $zone['total'] }} ({{ $slotRow['pct'] }}%)</span>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">ประวัติการจอง / การเช็คอิน</h2>
                <span class="text-xs text-gray-500">{{ number_format($bookings->total()) }} รายการ</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="py-2 pr-4">พนักงาน</th>
                            <th class="py-2 pr-4">โต๊ะ</th>
                            <th class="py-2 pr-4">วันที่ / ช่วงเวลา</th>
                            <th class="py-2 pr-4">สถานะ</th>
                            <th class="py-2 pr-4">เช็คอิน</th>
                            <th class="py-2 pr-4">เช็คเอาต์</th>
                            <th class="py-2 pr-4">รูป</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            <tr class="border-b border-gray-100 align-top">
                                <td class="py-2 pr-4">
                                    <p class="font-medium text-gray-800">{{ $booking->user->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $booking->user->employee_code }}@if ($booking->user->department)? {{ $booking->user->department->name }}@endif
                                    </p>
                                </td>
                                <td class="py-2 pr-4">
                                    <p class="font-medium text-gray-800">{{ $booking->desk->code }}</p>
                                    <p class="text-xs text-gray-500">{{ $booking->desk->zone->name }}</p>
                                </td>
                                <td class="py-2 pr-4">
                                    <p>{{ $booking->booking_date->format('d/m/Y') }}</p>
                                    <p class="text-xs text-gray-500">{{ $booking->timeSlot->name }} ({{ $booking->starts_at->format('H:i') }}-{{ $booking->ends_at->format('H:i') }})</p>
                                </td>
                                <td class="py-2 pr-4">
                                    @php
                                        $badge = match ($booking->status) {
                                            \App\Models\Booking::STATUS_CONFIRMED => 'bg-sky-100 text-sky-700',
                                            \App\Models\Booking::STATUS_CHECKED_IN => 'bg-blue-100 text-blue-700',
                                            \App\Models\Booking::STATUS_CHECKED_OUT => 'bg-emerald-100 text-emerald-700',
                                            \App\Models\Booking::STATUS_CANCELLED => 'bg-gray-100 text-gray-500',
                                            \App\Models\Booking::STATUS_EXPIRED => 'bg-red-100 text-red-600',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badge }}">
                                        {{ $statuses[$booking->status] ?? $booking->status }}
                                    </span>
                                    @if ($booking->cancel_reason === \App\Models\Booking::CANCEL_AUTO_LATE)
                                        <p class="mt-0.5 text-[11px] text-red-500">เลยเวลาเช็คอิน</p>
                                    @elseif ($booking->cancel_reason === \App\Models\Booking::CANCEL_BY_USER)
                                        <p class="mt-0.5 text-[11px] text-gray-400">ผู้ใช้ยกเลิก</p>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-xs text-gray-600">{{ $booking->checked_in_at?->format('d/m H:i') ?? '-' }}</td>
                                <td class="py-2 pr-4 text-xs text-gray-600">{{ $booking->checked_out_at?->format('d/m H:i') ?? '-' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($booking->checkin_photo_path)
                                        <a href="{{ route('bookings.photo', $booking) }}" target="_blank"
                                           class="rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                            ดูรูป
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-300">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 text-center text-gray-500">ไม่พบข้อมูลตามเงื่อนไข</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
@endsection