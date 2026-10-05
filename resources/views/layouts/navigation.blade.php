@php
    $current = request()->route()?->getName() ?? '';
    $items = [
        ['label' => 'ค้นหาโต๊ะ', 'route' => 'dashboard'],
        ['label' => 'การจองของฉัน', 'route' => 'bookings.mine'],
        ['label' => 'โปรไฟล์ของฉัน', 'route' => 'profile'],
    ];
@endphp

<nav class="border-b border-gray-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">BS</div>
            <div>
                <p class="text-sm font-bold leading-tight text-gray-800">BS_Hybrid Work</p>
                <p class="text-[11px] text-gray-500">ระบบจองโต๊ะทำงาน (พนักงาน)</p>
            </div>
        </div>

        <div class="flex items-center gap-1">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ $current === $item['route'] ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach

            <a href="{{ route('password.edit') }}"
               class="rounded-lg px-3 py-2 text-sm font-medium {{ $current === 'password.edit' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                เปลี่ยนรหัสผ่าน
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                    ออกจากระบบ
                </button>
            </form>
        </div>
    </div>
</nav>
