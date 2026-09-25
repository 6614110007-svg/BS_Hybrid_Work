@props(['zone', 'highlight' => null])

@php
    $desks = $zone->desks ?? collect();
    $cell = 52;
    $size = 44;
    $maxX = $desks->max('x') ?? 1;
    $maxY = $desks->max('y') ?? 1;
    $w = max($maxX * $cell, 260);
    $h = max($maxY * $cell, 120);
@endphp

<div class="relative rounded-lg border border-dashed border-gray-300 bg-gray-50/60"
     style="width: {{ $w }}px; height: {{ $h }}px;">
    @foreach ($desks as $desk)
        @php
            $isHighlight = $highlight && $desk->id === $highlight;
            $style = 'background:white;';
            $labelColor = 'text-gray-800';

            if (! $desk->is_active) {
                $style = 'background:#f3f4f6;border:1px dashed #9ca3af;';
                $labelColor = 'text-gray-400';
            } elseif ($desk->is_maintenance) {
                $style = 'background:#fef3c7;border:1.5px solid #f59e0b;';
                $labelColor = 'text-amber-700';
            } else {
                $style = 'background:#eef2ff;border:1.5px solid #6366f1;';
                $labelColor = 'text-indigo-700';
            }

            if ($isHighlight) {
                $style = 'background:#fecaca;border:2.5px solid #ef4444;';
                $labelColor = 'text-red-700';
            }
        @endphp
        <div class="absolute flex flex-col items-center justify-center rounded-md px-1 text-center shadow-sm"
             style="{{ $style }}; left: {{ ($desk->x - 1) * $cell }}px; top: {{ ($desk->y - 1) * $cell }}px; width: {{ $size }}px; height: {{ $size }}px;">
            <span class="text-[10px] font-semibold leading-tight {{ $labelColor }}">{{ $desk->code }}</span>
        </div>
    @endforeach

    @if ($desks->isEmpty())
        <p class="absolute inset-0 flex items-center justify-center text-xs text-gray-400">ยังไม่มีโต๊ะในโซนนี้</p>
    @endif
</div>