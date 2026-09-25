<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        บัญชีของคุณยังใช้<b>รหัสผ่านชั่วคราว</b>อยู่ กรุณาตั้งรหัสผ่านใหม่ก่อนดำเนินการต่อ
    </div>

    @if (session('status'))
        <div class="mb-4 text-sm font-medium text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.change.store') }}">
        @csrf

        <div>
            <x-input-label for="current_password" :value="__('รหัสผ่านปัจจุบัน')" />
            <x-text-input id="current_password" class="block mt-1 w-full" type="password" name="current_password"
                required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('รหัสผ่านใหม่')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('ยืนยันรหัสผ่านใหม่')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('บันทึกรหัสผ่านใหม่') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>