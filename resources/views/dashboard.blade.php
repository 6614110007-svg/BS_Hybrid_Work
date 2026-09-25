<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            หน้าแรกพนักงาน
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-lg font-medium">สวัสดี, {{ auth()->user()->name }}</p>
                    <p class="mt-1 text-sm text-gray-500">
                        ระบบค้นหาและจองโต๊ะทำงาน (Interactive Seat Map) จะถูกเพิ่มในขั้นตอนถัดไป
                        คุณสามารถเปลี่ยนรหัสผ่านได้ที่หน้าโปรไฟล์
                    </p>
                    <a href="{{ route('profile.edit') }}" class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        ไปที่โปรไฟล์
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>