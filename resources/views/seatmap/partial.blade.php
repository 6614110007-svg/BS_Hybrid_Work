{{-- ผังโต๊ะ + การจองของฉัน (โหลดซ้ำได้ทั้งหน้าเต็มและผ่าน fetch) --}}
<div class="grid gap-4 lg:grid-cols-3" data-seatmap-body>
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
                             data-state="{{ $state['state'] }}"
                             data-dot-class="{{ $meta['dot'] }}"
                             data-card-class="{{ $meta['card'] }}">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $desk->desk_number }}</p>
                                    <p class="text-[11px] text-gray-500">ตำแหน่ง {{ $position[0] }},{{ $position[1] }}</p>
                                </div>
                                <span data-state-dot class="h-2.5 w-2.5 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                            </div>

                            <p class="mt-2 text-[11px] font-medium text-gray-600" data-state-label>{{ $meta['label'] }}</p>

                            @if ($state['state'] === 'available')
                                <form method="POST" action="{{ route('bookings.store') }}" class="mt-2" data-loading-form
                                      data-booking-form
                                      data-desk-number="{{ $desk->desk_number }}"
                                      data-zone-name="{{ $zone->zone_name }}"
                                      data-booking-date="{{ $date->format('d/m/Y') }}"
                                      data-time-slot="{{ $slot->name }} {{ $slot->start }} - {{ $slot->end }}">
                                    @csrf
                                    <input type="hidden" name="desk_id" value="{{ $desk->desk_id }}">
                                    <input type="hidden" name="booking_date" value="{{ $date->toDateString() }}">
                                    <input type="hidden" name="time_slot" value="{{ $slot->name }}">
                                    <button type="submit" class="w-full rounded-lg bg-indigo-600 px-2 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                                            data-loading-button>
                                        จองโต๊ะนี้
                                    </button>
                                </form>
                            @elseif ($state['state'] === 'my_booked')
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

            @forelse ($myBookings as $myBooking)
                <div class="mb-2 rounded-lg border border-gray-200 p-3 last:mb-0">
                    <p class="text-sm font-semibold text-gray-800">{{ $myBooking->desk->desk_number }}</p>
                    <p class="text-xs text-gray-500">{{ $myBooking->desk->zone->zone_name }}</p>
                    <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ \App\Models\Booking::statusBadge($myBooking->booking_status) }}">
                        {{ $myBooking->statusLabel() }}
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
                <li>จองได้เฉพาะวันที่ไม่ใช่เสาร์-อาทิตย์และไม่ใช่วันหยุดนักขัตฤกษ์</li>
                <li>จองล่วงหน้าได้ไม่เกิน {{ config('booking.lead_days') }} วัน</li>
                <li>ต้องเช็คอินภายใน {{ config('booking.late_grace_minutes') }} นาทีหลังเริ่มเวลา
                    (ถ้าจองวันเดียวกันหลังเลยเวลาเริ่มแล้ว ให้นับจากเวลาที่กดจอง)
                    มิฉะนั้นระบบจะยกเลิกอัตโนมัติ
                </li>
                <li>ต้องถ่ายรูปเซลฟี่เพื่อยืนยันการเข้าใช้โต๊ะ ระบบจะสุ่มโจทย์ให้ 1 ข้อ</li>
                <li>โต๊ะสีเทาคือปิดซ่อมบำรุง จองไม่ได้</li>
            </ul>
        </section>
    </aside>
</div>
