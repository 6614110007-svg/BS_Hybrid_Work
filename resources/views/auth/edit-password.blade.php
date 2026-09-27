@extends('layouts.guest')

@section('title', 'เปลี่ยนรหัสผ่าน')

@section('content')
    <h2 class="mb-1 text-xl font-semibold text-gray-800">เปลี่ยนรหัสผ่าน</h2>
    <p class="mb-6 text-sm text-gray-500">
        ผู้ใช้งาน: <span class="font-medium text-gray-700">{{ $actor?->actorName() }}</span>
        ({{ $actor?->actorEmail() }})
    </p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" value="รหัสผ่านปัจจุบัน" />
            <x-text-input id="current_password" name="current_password" type="password"
                          required autocomplete="current-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password" value="รหัสผ่านใหม่" />
            <x-text-input id="password" name="password" type="password" required
                          autocomplete="new-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="ยืนยันรหัสผ่านใหม่" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" required
                          autocomplete="new-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <p class="text-xs text-gray-500">รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร</p>

        <div class="flex items-center gap-3">
            <x-primary-button>บันทึกรหัสผ่านใหม่</x-primary-button>
            <a href="{{ route($actor?->homeRoute() ?? 'login') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection
