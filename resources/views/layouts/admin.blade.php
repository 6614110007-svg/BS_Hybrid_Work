<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'ผู้ดูแลระบบ') · {{ config('app.name', 'BS_Hybrid Work') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&family=prompt:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="antialiased bg-gray-100 font-sans text-gray-900" x-data="{ sidebar: false }">
        <div class="min-h-screen">
            @php
                $navigation = [
                    ['label' => 'แดชบอร์ด', 'route' => 'admin.dashboard'],
                    ['label' => 'รายการจอง', 'route' => 'admin.bookings.index'],
                    ['label' => 'พนักงาน', 'route' => 'admin.employees.index'],
                    ['label' => 'แผนก', 'route' => 'admin.departments.index'],
                    ['label' => 'โซนพื้นที่', 'route' => 'admin.zones.index'],
                    ['label' => 'โต๊ะทำงาน', 'route' => 'admin.desks.index'],
                    ['label' => 'รายงาน & สถิติ', 'route' => 'admin.reports.index'],
                ];
                $current = request()->route()?->getName() ?? '';
            @endphp

            <nav class="fixed inset-y-0 left-0 z-40 w-64 bg-indigo-950 text-white transition-transform lg:translate-x-0"
                 :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
                <div class="flex h-16 items-center gap-2 border-b border-white/10 px-6">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold">BS</div>
                    <div>
                        <p class="text-sm font-bold leading-tight">BS_Hybrid Work</p>
                        <p class="text-[11px] text-indigo-300">ผู้ดูแลระบบ (Admin)</p>
                    </div>
                </div>

                <div class="space-y-1 px-3 py-4">
                    @foreach ($navigation as $item)
                        <a href="{{ route($item['route']) }}"
                           class="block rounded-lg px-3 py-2 text-sm font-medium {{ $current === $item['route'] ? 'bg-indigo-600 text-white' : 'text-indigo-200 hover:bg-white/10 hover:text-white' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="absolute inset-x-0 bottom-0 border-t border-white/10 p-4">
                    @if (! $actorIsAdmin && $actor)
                        <a href="{{ route('dashboard') }}"
                           class="mb-2 block rounded-lg px-3 py-2 text-sm text-indigo-200 hover:bg-white/10 hover:text-white">
                            ไปหน้าพนักงาน
                        </a>
                    @endif

                    <a href="{{ route('password.edit') }}"
                       class="mb-2 block rounded-lg px-3 py-2 text-sm text-indigo-200 hover:bg-white/10 hover:text-white">
                        เปลี่ยนรหัสผ่าน
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-indigo-200 hover:bg-white/10 hover:text-white">
                            ออกจากระบบ
                        </button>
                    </form>
                </div>
            </nav>

            <div class="lg:pl-64">
                <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <button type="button" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden"
                                x-on:click="sidebar = true" aria-label="เปิดเมนู">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>

                        <h1 class="text-lg font-semibold text-gray-800">@yield('title', 'ผู้ดูแลระบบ')</h1>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.profile.edit') }}"
                           class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-gray-100"
                           title="แก้ไขโปรไฟล์ผู้ดูแลระบบ">
                            <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-indigo-100">
                                {!! \App\Support\AnimalAvatar::svg($actor?->avatarKey(), 'h-9 w-9') !!}
                            </span>
                            <span class="hidden text-left sm:block">
                                <span class="block text-sm font-medium leading-tight text-gray-800">{{ $actor?->displayName() }}</span>
                                <span class="block text-xs leading-tight text-gray-500">{{ $actor?->roleLabel() }}</span>
                            </span>
                        </a>
                    </div>
                </header>

                <main class="p-4 sm:p-6">
                    @include('layouts.flash')

                    @yield('content')
                </main>
            </div>
        </div>

        @include('components.confirm-dialog')

        {{-- พนักงานที่มี employee_role = 'Administrator' เข้าผ่านหน้า Admin ได้
             จึงต้องบังคับให้ตั้งรหัสผ่าน/อีเมลครั้งแรกเหมือนหน้าพนักงาน --}}
        @if ($actorNeedsAccountSetup)
            @include('components.account-setup-modal')
        @endif

        @stack('scripts')
    </body>
</html>
