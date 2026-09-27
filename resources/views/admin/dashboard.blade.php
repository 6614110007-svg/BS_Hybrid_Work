@extends('layouts.admin')

@section('title', 'แดชบอร์ดผู้ดูแลระบบ')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['key' => 'using_now', 'label' => 'กำลังใช้งานอยู่', 'value' => $live['using_now'], 'hint' => 'เช็คอินแล้ววันนี้', 'tone' => 'text-sky-600'],
            ['key' => 'available_now', 'label' => 'โต๊ะว่าง', 'value' => $live['available_now'], 'hint' => 'ใช้งานได้ '.$live['total_usable_desks'].' โต๊ะ', 'tone' => 'text-emerald-600'],
            ['key' => 'today_bookings', 'label' => 'การจองวันนี้', 'value' => $live['today_bookings'], 'hint' => 'เช็คอินแล้ว '.$live['today_checked_in'], 'tone' => 'text-indigo-600'],
            ['key' => 'today_rate', 'label' => 'อัตราการเข้าใช้', 'value' => $live['today_rate'].'%', 'hint' => 'ปิดซ่อมบำรุง '.$live['maintenance'].' โต๊ะ', 'tone' => 'text-amber-600'],
        ] as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-3xl font-bold {{ $card['tone'] }}" data-live="{{ $card['key'] }}">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'บัญชีผู้ดูแล', 'value' => $totalAdmins, 'route' => 'admin.employees.index'],
            ['label' => 'พนักงานทั้งหมด', 'value' => $totalEmployees, 'route' => 'admin.employees.index'],
            ['label' => 'แผนก', 'value' => $totalDepartments, 'route' => 'admin.departments.index'],
            ['label' => 'โซน / โต๊ะ', 'value' => $totalZones.' / '.$totalDesks, 'route' => 'admin.desks.index'],
        ] as $card)
            <a href="{{ route($card['route']) }}" class="rounded-xl border border-gray-200 bg-white p-4 hover:border-indigo-300">
                <p class="text-xs text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-800">{{ $card['value'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="mb-3 font-semibold text-gray-800">อัตราการใช้งานรายโซน (วันนี้)</h2>

            <div class="space-y-3">
                @forelse ($zoneOccupancy as $zone)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-700">{{ $zone['zone_name'] }}</span>
                            <span class="text-gray-500">{{ $zone['occupied'] }}/{{ $zone['total'] }} โต๊ะ ({{ $zone['pct'] }}%)</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $zone['pct'] }}%"></div>
                        </div>

                        <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-gray-500">
                            @foreach ($zone['slots'] as $slotRow)
                                <span>{{ $slotRow['time_slot'] }}: {{ $slotRow['occupied'] }} ({{ $slotRow['pct'] }}%)</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">ยังไม่มีข้อมูลโซน</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">กิจกรรมล่าสุด</h2>
                <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-indigo-700 underline">ดูทั้งหมด</a>
            </div>

            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($recentActivity as $row)
                    <li class="py-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-gray-800">{{ $row['employee'] }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $row['date'] }} · {{ $row['time_slot'] }} · {{ $row['zone'] }} / {{ $row['desk'] }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\Booking::statusBadge($row['status']) }}">
                                {{ $row['status_label'] }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-gray-500">ยังไม่มีการจอง</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800">พนักงานที่เพิ่มล่าสุด</h2>
            <a href="{{ route('admin.employees.index') }}" class="text-xs font-semibold text-indigo-700 underline">จัดการพนักงาน</a>
        </div>

        <ul class="divide-y divide-gray-100 text-sm">
            @forelse ($recentEmployees as $employee)
                <li class="flex items-center justify-between gap-3 py-2">
                    <div>
                        <p class="font-medium text-gray-800">{{ $employee->employee_fullname }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $employee->employee_email }} · {{ $employee->department?->department_name ?? 'ไม่ระบุแผนก' }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $employee->statusLabel() === 'ใช้งานอยู่' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $employee->roleLabel() }} · {{ $employee->statusLabel() }}
                    </span>
                </li>
            @empty
                <li class="py-6 text-center text-sm text-gray-500">ยังไม่มีพนักงาน</li>
            @endforelse
        </ul>
    </section>
@endsection

@push('scripts')
    <script>
        window.ADMIN_REALTIME_URL = @json(route('admin.dashboard.realtime'));
    </script>
@endpush
