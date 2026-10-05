{{--
    Banner/Header ประจำโซน สำหรับหน้าเลือกจองโต๊ะ (/dashboard)

    แสดงรูปปกโซน (thumbnail_url) + คำอธิบายบรรยากาศ ที่ผู้ดูแลกระทยาไว้
    พร้อมจำนวนโต๊ะที่ว่างจริง ณ ขณะนั้น

    รับ:
      $summary — array จาก SeatMapController::zoneSummaries()
      $zone    — App\Models\Zone (ใช้ fallback ตอนไม่มี summary)
--}}
@php
    $summary = $summary ?? [
        'description' => $zone->zone_description,
        'image' => $zone->imageUrl(),
        'total' => $zone->desks->count(),
        'available' => $zone->desks->reject->isMaintenance()->count(),
    ];
    $total = (int) $summary['total'];
    $available = (int) $summary['available'];
@endphp

<section class="zone-header overflow-hidden rounded-xl border border-gray-200 bg-white"
         data-zone-header="{{ $zone->zone_id }}">
    <div class="flex flex-col gap-4 sm:flex-row">
        {{-- รูปปกโซน — ถ้ายังไม่มีรูปให้แสดง placeholder แทนการเว้นว่าง --}}
        <div class="relative h-32 w-full shrink-0 overflow-hidden bg-gray-100 sm:h-auto sm:w-48">
            @if ($summary['image'])
                <img src="{{ $summary['image'] }}"
                     alt="รูปประจำโซน{{ $zone->zone_name }}"
                     loading="lazy"
                     class="h-full w-full object-cover" />
            @else
                <div class="flex h-full w-full flex-col items-center justify-center gap-1 bg-gradient-to-br from-indigo-50 to-slate-100 text-indigo-300">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M2.25 12l8.954-8.955a.75.75 0 011.06 0l7.736 7.736a.75.75 0 010 1.06L11.25 21.75a.75.75 0 01-1.06 0L2.25 13.06a.75.75 0 010-1.06zm6.44 1.06l4.31-4.31 3.06 3.06-4.31 4.31-3.06-3.06z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-[11px] font-medium text-indigo-400">ยังไม่มีรูปประจำโซน</span>
                </div>
            @endif
        </div>

        <div class="min-w-0 flex-1 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-semibold text-gray-800">{{ $zone->zone_name }}</h2>
                    <p class="mt-0.5 text-[11px] text-gray-400">รหัสโซน {{ $zone->zone_id }}</p>
                </div>

                {{-- จำนวนโต๊ะว่าง ณ ขณะนี้ (อัปเดตแบบ real-time จาก /seatmap/status) --}}
                <span class="zone-header__count inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold"
                      data-zone-available="{{ $zone->zone_id }}"
                      data-zone-total="{{ $zone->zone_id }}">
                    <span class="h-2 w-2 rounded-full {{ $available > 0 ? 'bg-emerald-400' : 'bg-rose-400' }}"
                          data-zone-dot="{{ $zone->zone_id }}"></span>
                    <span data-zone-count-text="{{ $zone->zone_id }}">{{ $total }} โต๊ะ · ใช้งานได้ {{ $available }}</span>
                </span>
            </div>

            <p class="mt-2 text-sm leading-6 text-gray-600">
                {{ $summary['description'] ?: 'ยังไม่มีคำอธิบายเพิ่มเติมสำหรับโซนนี้' }}
            </p>

            @if ($available === 0)
                <p class="mt-2 text-xs font-medium text-amber-600">
                    ขณะนี้โต๊ะในโซนนี้ถูกจองหรือปิดซ่อมบำรุงครบทุกตัว ลองเปลี่ยนวันที่หรือช่วงเวลา
                </p>
            @endif
        </div>
    </div>
</section>