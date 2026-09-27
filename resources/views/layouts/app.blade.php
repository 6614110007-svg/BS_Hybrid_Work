<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'ระบบจองโต๊ะ') · {{ config('app.name', 'BS_Hybrid Work') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&family=prompt:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="antialiased bg-gray-100 font-sans text-gray-900">
        <div class="min-h-screen">
            @include('layouts.navigation')

            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <h1 class="text-lg font-semibold text-gray-800">@yield('title', 'ระบบจองโต๊ะ')</h1>

                    <div class="flex items-center gap-3">
                        @if ($actorIsAdmin)
                            <a href="{{ route('admin.dashboard') }}"
                               class="hidden rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 sm:inline">
                                ไปหน้าผู้ดูแลระบบ
                            </a>
                        @endif

                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-800">{{ $actor?->actorName() }}</p>
                            <p class="text-xs text-gray-500">{{ $actor?->statusLabel() }}</p>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">
                            {{ mb_strtoupper(mb_substr((string) $actor?->actorName(), 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
                @include('layouts.flash')

                @yield('content')
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
