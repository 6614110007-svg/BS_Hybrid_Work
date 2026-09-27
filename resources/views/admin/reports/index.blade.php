@extends('layouts.admin')

@section('title', 'รายงานและสถิติ')

@section('content')
    <form method="GET" action="{{ route('admin.reports.index') }}"
          class="mb-4 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="date_from" class="mb-1 block text-xs font-medium text-gray-600">ตั้งแต่วันที่</label>
            <input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] }}"
                   class="w-full rounded-lg border-gray-300 text-sm" />
        </div>

        <div>
            <label for="date_to" class="mb-1 block text-xs font-medium text-gray-600">ถึงวันที่</label>
            <input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] }}"
                   class="w-full rounded-lg border-gray-300 text-sm" />
        </div>

        <div>
            <label for="status" class="mb-1 block text-xs font-medium text-gray-600">สถานะ</label>
            <select id="status" name="status" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="time_slot" class="mb-1 block text-xs font-medium text-gray-600">ช่วงเวลา</label>
            <select id="time_slot" name="time_slot" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($slots as $slot)
                    <option value="{{ $slot->name }}" @selected($filters['time_slot'] === $slot->name)>
                        {{ $slot->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="zone_id" class="mb-1 block text-xs font-medium text-gray-600">โซน</label>
            <select id="zone_id" name="zone_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($zones as $zone)
                    <option value="{{ $zone->zone_id }}" @selected($filters['zone_id'] === $zone->zone_id)>
                        {{ $zone->zone_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="department_id" class="mb-1 block text-xs font-medium text-gray-600">แผนก</label>
            <select id="department_id" name="department_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทั้งหมด</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->department_id }}" @selected($filters['department_id'] === $department->department_id)>
                        {{ $department->department_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="search" class="mb-1 block text-xs font-medium text-gray-600">ค้นหา</label>
            <input id="search" type="search" name="search" value="{{ $filters['search'] }}"
                   placeholder="ชื่อ / อีเมล / หมายเลขโต๊ะ" class="w-full rounded-lg border-gray-300 text-sm" />
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                แสดงรายงาน
            </button>
            <a href="{{ route('admin.reports.export', $filters) }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                ดาวน์โหลด CSV
            </a>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'การจองทั้งหมด', 'value' => $summary['total']],
            ['label' => 'เช็คอินแล้ว', 'value' => $summary['checked_in'] + $summary['completed']],
            ['label' => 'หมดอายุ / ยกเลิก', 'value' => $summary['expired']],
            ['label' => 'อัตราการมาใช้งาน', 'value' => $summary['rate'].'%'],
        ] as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-3xl font-bold text-gray-800">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="mb-3 font-semibold text-gray-800">สถิติรายวัน ({{ $dateFrom->format('d/m/Y') }} - {{ $dateTo->format('d/m/Y') }})</h2>

            <div class="max-h-80 overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">วันที่</th>
                            <th class="px-3 py-2">จอง</th>
                            <th class="px-3 py-2">เข้าใช้</th>
                            <th class="px-3 py-2">ไม่มา</th>
                            <th class="px-3 py-2">อัตรา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($daily as $row)
                            <tr>
                                <td class="px-3 py-2 text-gray-700">{{ $row['label'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['bookings'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['arrived'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['no_show'] }}</td>
                                <td class="px-3 py-2 font-medium text-gray-800">{{ $row['rate'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">ไม่มีข้อมูล</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="mb-3 font-semibold text-gray-800">สถิติรายเดือน</h2>

            <div class="max-h-80 overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2">เดือน</th>
                            <th class="px-3 py-2">จอง</th>
                            <th class="px-3 py-2">เข้าใช้</th>
                            <th class="px-3 py-2">ไม่มา</th>
                            <th class="px-3 py-2">อัตรา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($monthly as $row)
                            <tr>
                                <td class="px-3 py-2 text-gray-700">{{ $row['label'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['bookings'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['arrived'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $row['expired'] }}</td>
                                <td class="px-3 py-2 font-medium text-gray-800">{{ $row['rate'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">ไม่มีข้อมูล</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
        <h2 class="mb-3 font-semibold text-gray-800">อัตราการใช้งานรายโซน ({{ $date->format('d/m/Y') }})</h2>

        <div class="space-y-3">
            @forelse ($zoneOccupancy as $zone)
                <div>
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-medium text-gray-700">{{ $zone['zone_name'] }}</span>
                        <span class="text-gray-500">{{ $zone['occupied'] }}/{{ $zone['total'] }} ({{ $zone['pct'] }}%)</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $zone['pct'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">ไม่มีข้อมูลโซน</p>
            @endforelse
        </div>
    </section>
@endsection
