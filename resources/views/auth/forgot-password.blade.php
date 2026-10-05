@extends('layouts.guest')

@section('title', 'ลืมรหัสผ่าน')

@section('content')
    <div x-data="passwordRecoveryPage()" x-cloak x-show="open" @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="recovery-page-title">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/60" @click="close()"></div>

        <div x-show="open" x-transition.scale.origin.center
             class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 id="recovery-page-title" class="font-semibold text-gray-800">ลืมรหัสผ่าน</h2>
                    <p class="mt-0.5 text-xs text-gray-500">กรอกอีเมลที่ผูกไว้ตอนตั้งค่าบัญชี</p>
                </div>
                <button type="button" @click="close()" aria-label="ปิด"
                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                    </svg>
                </button>
            </div>

            @if (session('recovery_notice'))
                <div class="px-5 py-5">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex items-start gap-2.5">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.3a1 1 0 0 0-1.4-1.4L9 10.6 7.7 9.3a1 1 0 0 0-1.4 1.4l2 2a1 1 0 0 0 1.4 0l3-3Z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-emerald-900">{{ session('recovery_notice') }}</p>
                                <p class="mt-1 text-xs text-emerald-800">ติดต่อผู้ดูแลระบบของหน่วยงาน เพื่อขอรีเซ็ตรหัสผ่านชั่วคราว</p>
                            </div>
                        </div>
                    </div>

                    <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-xs text-gray-600">
                        <li>แจ้งอีเมลและชื่อ-นามสกุลของคุณให้ผู้ดูแลระบบทราบ</li>
                        <li>ผู้ดูแลจะตรวจสอบตัวตนกับข้อมูลบุคคล แล้วส่งรหัสผ่านชั่วคราวให้</li>
                        <li>เข้าสู่ระบบแล้วเปลี่ยนรหัสผ่านทันทีที่หน้า "เปลี่ยนรหัสผ่าน"</li>
                    </ol>

                    <a href="{{ route('login') }}"
                       class="mt-4 block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">
                        กลับไปหน้าเข้าสู่ระบบ
                    </a>
                </div>
            @else
                <form method="POST" action="{{ route('password.recovery.store') }}" class="px-5 py-5">
                    @csrf

                    <div>
                        <x-input-label for="recovery-email" value="อีเมลที่ใช้เข้าสู่ระบบ" />
                        <x-text-input id="recovery-email" name="recovery_email" type="email" required autofocus
                                      autocomplete="email" :value="old('recovery_email')"
                                      placeholder="name@example.com" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('recovery_email')" class="mt-1" />
                        <p class="mt-1 text-[11px] text-gray-500">
                            ระบบจะตรวจสอบว่าอีเมลนี้ผูกไว้กับบัญชีใด แล้วแจ้งขั้นตอนให้ติดต่อผู้ดูแลระบบ
                        </p>
                    </div>

                    <div class="mt-5 flex gap-2">
                        <a href="{{ route('login') }}"
                           class="flex-1 rounded-lg bg-gray-100 px-4 py-2 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                            กลับไปเข้าสู่ระบบ
                        </a>
                        <button type="submit"
                                class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                            ตรวจสอบอีเมล
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ลิงก์สำรองสำหรับผู้ที่ JavaScript ไม่ทำงาน — ลิงก์ไปหน้าเข้าสู่ระบบ --}}
    <noscript>
        <p class="mt-4 rounded-lg bg-amber-50 p-3 text-center text-xs text-amber-800">
            หากหน้าต่างกู้คืนรหัสผ่านไม่แสดง กรุณาเปิดใหม่ที่
            <a href="{{ route('login') }}" class="font-semibold underline">หน้าเข้าสู่ระบบ</a>
        </p>
    </noscript>
@endsection

@push('scripts')
    <script>
        /**
         * หน้ากู้คืนรหัสผ่านแบบเต็มหน้า (เปิดจากลิงก์สำรองเมื่อไม่มี JavaScript)
         *
         * เนื้อหาในหน้านี้คือ Modal อยู่แล้ว เพื่อให้ UI เหมือนกันทั้งสองทาง
         */
        function passwordRecoveryPage() {
            return {
                open: true,

                close() {
                    window.location.href = @json(route('login'));
                },
            };
        }
    </script>
@endpush