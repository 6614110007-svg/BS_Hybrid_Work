@extends('layouts.admin')

@section('title', 'แดชบอร์ดผู้ดูแลระบบ')

@section('content')
    <div x-data="adminRealtime(@js([
        'live' => $live,
        'zones' => $zoneOccupancy,
        'activity' => $recentActivity->map(fn ($a) => [
            'booking_id' => $a['booking_id'],
            'employee' => $a['employee'],
            'department' => $a['department'],
            'zone' => $a['zone'],
            'desk' => $a['desk'],
            'date' => $a['date'],
            'slot' => $a['slot'],
            'status' => $a['status'],
            'action_at' => $a['action_at']->toIso8601String(),
            'cancel_reason' => $a['cancel_reason'],
        ])->values(),
    ]))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">ภาพรวม ณ ตอนนี้</h2>
                <p class="text-sm text-gray-500">
                    ข้อมูลอัตโนมัติทุก 10 วินาที
                    <span x-show="live" class="font-medium text-gray-600" x-text="'(อัปเดตล่าสุด ' + serverTime + ' น.)'"></span>
                    <span x-show="error" class="text-red-600">· เชื่อมต่อข้อมูลเรียลไทม์ล้มเหลว</span>
                    <span x-show="updating && !error" class="text-indigo-600">· กำลังอัปเดต...</span>
                </p>
            </div>
            <a href="{{ route('admin.reports.index') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                ไปยังรายงาน &amp; สถิติ
            </a>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">ใช้โต๊ะอยู่ตอนนี้</p>
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </div>
                <p class="mt-1 text-3xl font-bold text-gray-900" x-text="live.using_now">0</p>
                <p class="mt-1 text-xs text-gray-500" x-text="'จาก ' + live.total_usable_desks + ' โต๊ะที่ใช้ได้ · ว่าง ' + live.available_now + ' โต๊ะ'"></p>
                <div class="mt-3 h-2 w-full rounded-full bg-gray-200">
                    <div class="h-2 rounded-full bg-emerald-500"
                         :style="'width:' + (live.total_usable_desks ? Math.round(live.using_now / live.total_usable_desks * 100) : 0) + '%'"></div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">การจองวันนี้</p>
                <p class="mt-1 text-3xl font-bold text-gray-900" x-text="live.today_bookings">0</p>
                <p class="mt-1 text-xs text-gray-500" x-text="'รวมทุกรอบเวลา (เช้า-บ่าย)'"></p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">เช็คอินแล้ว (สำเร็จ)</p>
                <p class="mt-1 text-3xl font-bold text-indigo-900" x-text="live.today_checked_in">0</p>
                <p class="mt-1 text-xs" :class="live.today_rate >= 70 ? 'text-emerald-600' : live.today_rate >= 40 ? 'text-amber-600' : 'text-red-600'"
                   x-text="'อัตราความสำเร็จเช็คอิน ' + live.today_rate + '%'"></p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">องค์กรโดยรวม</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">
                    <span class="text-lg font-medium text-gray-400 align-middle">👥</span> {{ $activeEmployees }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $activeDesks }} โต๊ะใช้ได้ · ซ่อม <span x-text="live.maintenance">{{ $maintenanceDesks }}</span> · {{ $totalZones }} โซน
                </p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-800">อัตราความหนาแน่นรายโซน (%) — วันนี้ รายรอบ</h2>
                    <div class="flex items-center gap-3 text-[11px] text-gray-500">
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>&lt; 50%</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>50–79%</span>
                        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>≥ 80%</span>
                    </div>
                </div>

                <div class="mt-4 space-y-4">
                    <template x-for="zone in zones" :key="zone.zone_id">
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-gray-700" x-text="zone.zone_name"></span>
                                <span class="text-xs text-gray-500">
                                    <span x-text="zone.total"></span> โต๊ะใช้ได้
                                    <span x-show="zone.maintenance > 0"> · ซ่อม <span x-text="zone.maintenance"></span></span>
                                </span>
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <template x-for="slot in zone.slots" :key="slot.slot_id">
                                    <div class="flex items-center gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-medium text-gray-500" x-text="slot.slot_name"></span>
                                        <div class="h-2.5 flex-1 rounded-full bg-gray-200">
                                            <div class="h-2.5 rounded-full transition-all duration-500" :class="colorFor(slot.pct)"
                                                 :style="'width:' + (slot.pct || 0) + '%'"></div>
                                        </div>
                                        <span class="w-20 shrink-0 text-right text-[11px] text-gray-600" x-text="slot.occupied + '/' + zone.total + ' (' + slot.pct + '%)'"></span>
                                    </div>
                                </template>
                                <p x-show="zone.slots.length === 0" class="text-xs text-gray-400">ยังไม่มีช่วงเวลา</p>
                            </div>
                        </div>
                    </template>
                    <p x-show="zones.length === 0" class="text-sm text-gray-500">ยังไม่มีโซนพื้นที่ใช้งาน</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-800">กิจกรรมล่าสุด</h2>
                <div class="mt-4 space-y-3">
                    <template x-for="item in activity" :key="item.booking_id + item.status">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-800" x-text="item.employee"></p>
                                <p class="truncate text-xs text-gray-500"
                                   x-text="item.desk + ' · ' + item.zone + ' · ' + item.slot + ' · ' + item.date"></p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                      :class="badgeFor(item.status)[1]"
                                      x-text="badgeFor(item.status)[0]"></span>
                                <span class="text-[11px] text-gray-400" x-text="timeAgo(item.action_at)"></span>
                            </div>
                        </div>
                    </template>
                    <p x-show="activity.length === 0" class="text-sm text-gray-500">ยังไม่มีกิจกรรม</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">พนักงานทั้งหมด</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $totalEmployees }}</p>
                <p class="mt-1 text-xs text-green-600">{{ $activeEmployees }} คน ใช้งานอยู่</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">พนักงานที่ถูกระงับ</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $inactiveEmployees }}</p>
                <p class="mt-1 text-xs text-red-600">ไม่สามารถเข้าสู่ระบบได้</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">แผนก / โซน</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $totalDepartments }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $totalZones }} โซนพื้นที่ใช้งานอยู่</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">โต๊ะทำงานทั้งหมด</p>
                <p class="mt-1 text-3xl font-bold text-gray-900">{{ $totalDesks }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    ใช้งาน {{ $activeDesks }} · ซ่อมบำรุง {{ $maintenanceDesks }}
                </p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-200">
                <h2 class="text-base font-semibold text-gray-800">พนักงานที่เพิ่มล่าสุด</h2>
                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($recentEmployees as $employee)
                        <div class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $employee->name }}</p>
                                <p class="text-xs text-gray-500">{{ $employee->email }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $employee->department ? 'bg-gray-100 text-gray-600' : 'bg-gray-50 text-gray-400' }}">
                                {{ $employee->department?->name ?? 'ไม่มีแผนก' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">ยังไม่มีพนักงาน</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection