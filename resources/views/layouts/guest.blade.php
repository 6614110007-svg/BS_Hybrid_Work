<!DOCTYPE html>
<html lang="th">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'เข้าสู่ระบบ') · {{ config('app.name', 'BS_Hybrid Work') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&family=prompt:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="flex min-h-screen flex-col items-center bg-gray-100 px-4 py-10 font-sans text-gray-900 antialiased">
        <div class="w-full sm:max-w-md">
            <div class="mb-6 flex items-center justify-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">BS</div>
                <div>
                    <p class="text-base font-bold leading-tight text-gray-800">BS_Hybrid Work</p>
                    <p class="text-xs text-gray-500">ระบบจองโต๊ะทำงาน</p>
                </div>
            </div>

            <div class="overflow-hidden bg-white px-6 py-6 shadow-md sm:rounded-lg">
                @include('layouts.flash')

                @yield('content')
            </div>

            <p class="mt-4 text-center text-xs text-gray-500">
                ผู้ดูแลระบบและพนักงานใช้หน้าเข้าสู่ระบบเดียวกัน ระบบจะพาท่านไปยังหน้าตามสิทธิ์ของบัญชี
            </p>
        </div>

        @stack('scripts')
    </body>
</html>
