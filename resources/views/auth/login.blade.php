@extends('layouts.guest')

@section('title', 'เข้าสู่ระบบ')

@section('content')
    <h2 class="mb-1 text-xl font-semibold text-gray-800">เข้าสู่ระบบ</h2>
    <p class="mb-6 text-sm text-gray-500">กรอกอีเมลและรหัสผ่านของคุณ</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="อีเมล" />
            <x-text-input id="email" name="email" type="email" :value="old('email')"
                          required autofocus autocomplete="username" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="password" value="รหัสผ่าน" />
            <x-text-input id="password" name="password" type="password" required
                          autocomplete="current-password" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <x-primary-button class="w-full justify-center">เข้าสู่ระบบ</x-primary-button>
    </form>
@endsection
