{{--
    Modal ตั้งค่าบัญชีครั้งแรก (First-time Login)
    แสดงอัตโนมัติทุกหน้าเมื่อพนักงานยังมี first_login = true
    ผู้ใช้ต้องผูกอีเมล ตั้งรหัสผ่านใหม่ และเลือก avatar จึงจะเข้าใช้งานได้
--}}
<div x-data="accountSetup" x-cloak
     data-requires-setup="{{ $actorNeedsAccountSetup ? 'true' : 'false' }}"
     x-show="open"
     x-on:keydown.escape.prevent="force()"
     class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="account-setup-title">

    <div class="fixed inset-0 bg-gray-900/60" aria-hidden="true"></div>

    <div class="relative my-auto w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50 px-6 py-5">
            <h2 id="account-setup-title" class="text-lg font-bold text-gray-900">ตั้งค่าบัญชีของคุณ</h2>
            <p class="mt-1 text-sm text-gray-600">
                ยินดีต้อนรับ <span class="font-semibold text-gray-900">{{ $actor->employee_fullname }}</span>
                ({{ $actor->employee_id }}) — กรุณาตั้งค่าข้อมูลต่อไปนี้ก่อนเข้าใช้งานระบบ
            </p>
        </div>

        <form method="POST" action="{{ route('account.setup.update') }}"
              class="max-h-[72vh] space-y-6 overflow-y-auto px-6 py-5">
            @csrf
            @method('PUT')

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="setup_email" class="block text-sm font-medium text-gray-700">
                        อีเมลสำหรับติดต่อ <span class="text-rose-500">*</span>
                    </label>
                    <input id="setup_email" name="employee_email" type="email" required
                           value="{{ old('employee_email', $actor->employee_email) }}"
                           placeholder="name@company.com"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('employee_email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">ใช้สำหรับติดต่อกลับโดยผู้ดูแลระบบ</p>
                </div>

                <div>
                    <label for="setup_password" class="block text-sm font-medium text-gray-700">
                        รหัสผ่านใหม่ <span class="text-rose-500">*</span>
                    </label>
                    <input id="setup_password" name="password" type="password" required minlength="8"
                           placeholder="อย่างน้อย 8 ตัวอักษร"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="setup_password_confirmation" class="block text-sm font-medium text-gray-700">
                        ยืนยันรหัสผ่านใหม่ <span class="text-rose-500">*</span>
                    </label>
                    <input id="setup_password_confirmation" name="password_confirmation" type="password" required minlength="8"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            @include('components.avatar-picker', [
                'animals' => \App\Support\AnimalAvatar::all(),
                'name' => 'employee_avatar',
                'selected' => $actor->avatarKey(),
            ])

            <div class="flex flex-col gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <button type="submit"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    อยู่ในระบบต่อ
                </button>
                {{-- formnovalidate เพื่อให้ออกจากระบบได้แม้ยังไม่ได้กรอกข้อมูลให้ครบ --}}
                <button type="submit" name="logout" value="1" formnovalidate
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    ออกจากระบบ
                </button>
            </div>
        </form>
    </div>
</div>