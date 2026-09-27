@extends('layouts.app')

@section('title', 'ค้นหาและจองโต๊ะ')

@php
    $stateMeta = [
        'available' => ['label' => 'ว่าง', 'dot' => 'bg-emerald-400', 'card' => 'border-emerald-200 bg-emerald-50'],
        'booked' => ['label' => 'ถูกจอง', 'dot' => 'bg-amber-400', 'card' => 'border-amber-200 bg-amber-50'],
        'in_use' => ['label' => 'กำลังใช้งาน', 'dot' => 'bg-sky-400', 'card' => 'border-sky-200 bg-sky-50'],
        'my_booked' => ['label' => 'คุณจองแล้ว', 'dot' => 'bg-indigo-500', 'card' => 'border-indigo-300 bg-indigo-50'],
        'my_in_use' => ['label' => 'คุณกำลังใช้งาน', 'dot' => 'bg-indigo-600', 'card' => 'border-indigo-400 bg-indigo-100'],
        'maintenance' => ['label' => 'ปิดซ่อมบำรุง', 'dot' => 'bg-rose-400', 'card' => 'border-rose-200 bg-rose-50'],
    ];
@endphp

@section('content')
    <form method="GET" action="{{ route('dashboard') }}"
          class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="date" class="mb-1 block text-xs font-medium text-gray-600">วันที่</label>
            <input id="date" type="date" name="date" value="{{ $date->toDateString() }}"
                   min="{{ $minDate }}" max="{{ $maxDate }}"
                   class="w-full rounded-lg border-gray-300 text-sm" />
        </div>

        <div>
            <label for="time_slot" class="mb-1 block text-xs font-medium text-gray-600">ช่วงเวลา</label>
            <select id="time_slot" name="time_slot" class="w-full rounded-lg border-gray-300 text-sm">
                @foreach ($slots as $option)
                    <option value="{{ $option->name }}" @selected($option->name === $slot->name)>
                        {{ $option->name }} ({{ $option->start }} - {{ $option->end }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="zone_id" class="mb-1 block text-xs font-medium text-gray-600">โซน</label>
            <select id="zone_id" name="zone_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">ทุกโซน</option>
                @foreach ($allZones as $option)
                    <option value="{{ $option->zone_id }}" @selected($option->zone_id === $zoneId)>
                        {{ $option->zone_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                ค้นหา
            </button>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                ล้างตัวกรอง
            </a>
        </div>
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-gray-600">
        <span class="font-medium text-gray-700">สถานะโต๊ะ:</span>
        @foreach ($stateMeta as $key => $meta)
            <span class="inline-flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}
            </span>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        @forelse ($zones as $zone)
            <section class="rounded-xl border border-gray-200 bg-white p-4 lg:col-span-2">
                <header class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-800">{{ $zone->zone_name }}</h2>
                    <span class="text-xs text-gray-500">
                        {{ $zone->desks->count() }} โต๊ะ · ใช้งานได้ {{ $zone->desks->reject->isMaintenance()->count() }}
                    </span>
                </header>

                @if ($zone->desks->isEmpty())
                    <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500">โซนนี้ยังไม่มีโต๊ะ</p>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($zone->desks as $desk)
                            @php($state = $deskStates[$desk->desk_id] ?? ['state' => 'available', 'booking_id' => null, 'mine' => false])
                            @php($meta = $stateMeta[$state['state']] ?? $stateMeta['available'])
                            @php($position = $desk->position())
                            <div class="rounded-lg border-2 p-3 {{ $meta['card'] }}"
                                 data-desk-id="{{ $desk->desk_id }}"
                                 data-state="{{ $state['state'] }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">{{ $desk->desk_number }}</p>
                                        <p class="text-[11px] text-gray-500">ตำแหน่ง {{ $position[0] }},{{ $position[1] }}</p>
                                    </div>
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                                </div>

                                <p class="mt-2 text-[11px] font-medium text-gray-600" data-state-label>{{ $meta['label'] }}</p>

                                @if ($state['state'] === 'available')
                                    <form method="POST" action="{{ route('bookings.store') }}" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="desk_id" value="{{ $desk->desk_id }}">
                                        <input type="hidden" name="booking_date" value="{{ $date->toDateString() }}">
                                        <input type="hidden" name="time_slot" value="{{ $slot->name }}">
                                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-2 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                            จองโต๊ะนี้
                                        </button>
                                    </form>
                                @elseif ($state['mine'] && $state['state'] === 'my_booked')
                                    <a href="{{ route('bookings.mine') }}"
                                       class="mt-2 block rounded-lg bg-white px-2 py-1.5 text-center text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50">
                                        ดูใบจอง
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 lg:col-span-2">
                ไม่พบโซนที่ตรงกับตัวกรอง
            </div>
        @endforelse

        <aside class="space-y-4">
            <section class="rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="mb-3 font-semibold text-gray-800">การจองของคุณ</h2>

                @forelse ($myBookings as $booking)
                    <div class="mb-2 rounded-lg border border-gray-200 p-3 last:mb-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $booking->desk->desk_number }}</p>
                        <p class="text-xs text-gray-500">{{ $booking->desk->zone->zone_name }}</p>
                        <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\Booking::statusBadge($booking->booking_status) }}">
                            {{ $booking->statusLabel() }}
                        </span>
                    </div>
                @empty
                    <p class="rounded-lg bg-gray-50 p-3 text-xs text-gray-500">
                        ยังไม่มีการจองในวันที่และช่วงเวลานี้
                    </p>
                @endforelse

                <a href="{{ route('bookings.mine') }}" class="mt-3 block text-center text-xs font-semibold text-indigo-700 underline">
                    ดูการจองทั้งหมด
                </a>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 text-xs leading-5 text-gray-600">
                <h2 class="mb-2 font-semibold text-gray-800">กติกาการจอง</h2>
                <ul class="list-disc space-y-1 pl-4">
                    <li>1 คน จองได้ 1 โต๊ะ ต่อ 1 ช่วงเวลา</li>
                    <li>1 โต๊ะ จองได้ 1 คน ต่อ 1 ช่วงเวลา</li>
                    <li>ต้องเช็คอินภายใน {{ config('booking.late_grace_minutes') }} นาทีหลังเริ่มเวลา มิฉะนั้นระบบจะยกเลิกอัตโนมัติ</li>
                    <li>ต้องถ่ายรูปเซลฟี่เพื่อยืนยันการเข้าใช้โต๊ะ</li>
                    <li>โต๊ะสีเทาคือปิดซ่อมบำรุง จองไม่ได้</li>
                </ul>
            </section>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        window.SEATMAP_STATUS_URL = @json(route('seatmap.status'));
        window.SEATMAP_META = @json($stateMeta);
    </script>
@endpush
