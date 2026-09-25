<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            เช็คอินด้วยเซลฟี 📸
        </h2>
    </x-slot>

    <div class="py-10" x-data="checkin({
        action: @js(route('bookings.checkin.store', $booking)),
        back: @js(route('dashboard')),
        deadline: @js($deadline->toIso8601String()),
    })">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Booking summary -->
                <div class="bg-indigo-600 text-white px-5 py-4 flex items-center justify-between">
                    <div>
                        <div class="font-bold">โต๊ะ {{ $booking->desk->code }}</div>
                        <div class="text-indigo-100 text-xs mt-0.5">
                            {{ $booking->desk->zone->name }} · {{ $booking->timeSlot->name }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-indigo-100 text-xs">วันที่จอง</div>
                        <div class="font-semibold text-sm">{{ $booking->booking_date->format('d/m/Y') }}</div>
                    </div>
                </div>

                <!-- Challenge -->
                <div class="px-5 py-4 bg-amber-50 border-b border-amber-100">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide">โจทย์ยืนยันตัวตนประจำวันนี้</div>
                    <p class="mt-1 text-amber-900 font-medium">"{!! $challenge !!}"</p>
                    <p class="mt-1 text-xs text-amber-700">ถ่ายภาพตัวเองพร้อมทำท่าตามโจทย์ข้างต้นเพื่อยืนยันตัวตน</p>
                </div>

                <!-- Camera area -->
                <div class="p-5 space-y-4">
                    <template x-if="!preview">
                        <div class="relative rounded-xl overflow-hidden bg-gray-900 aspect-video">
                            <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover"></video>
                            <div x-show="cameraError" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-900 text-white text-sm px-6 text-center">
                                <p class="font-semibold mb-1">ไม่สามารถเปิดกล้องได้</p>
                                <p class="text-gray-400 text-xs" x-text="cameraError"></p>
                                <p class="mt-3 text-xs text-gray-300">คุณสามารถอัปโหลดรูปจากแกลเลอรีได้ (กล้องหน้าบนมือถือ)</p>
                            </div>
                            <div x-show="streaming" class="absolute top-3 left-3 flex items-center gap-1.5 text-white text-xs bg-black/40 px-2 py-1 rounded-full">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse inline-block"></span> LIVE
                            </div>
                        </div>
                    </template>

                    <template x-if="preview">
                        <div class="relative rounded-xl overflow-hidden border border-gray-200">
                            <img :src="preview" class="w-full aspect-video object-cover" alt="ตัวอย่างรูป">
                        </div>
                    </template>

                    <!-- Countdown to deadline -->
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
                        <div class="text-xs text-gray-500">หมดเวลาเช็คอินภายใน</div>
                        <div class="font-mono font-bold text-indigo-700" x-text="countdown"></div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <template x-if="!preview">
                            <button x-show="streaming" type="button" @click="capture()"
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                                ถ่ายรูป
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0016.07 7H17a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </button>
                        </template>

                        <template x-if="preview">
                            <button type="button" @click="retake()"
                                    class="flex-1 px-4 py-2.5 rounded-lg border border-gray-300 text-gray-600 font-semibold hover:bg-gray-50 transition">
                                ถ่ายใหม่
                            </button>
                        </template>

                        <button x-show="preview" type="button" @click="submit()" :disabled="uploading"
                                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 disabled:opacity-50 transition">
                            <span x-show="!uploading">ยืนยันส่งรูปและเช็คอิน</span>
                            <span x-show="uploading">กำลังส่งรูป…</span>
                        </button>

                        <template x-if="cameraError && !preview">
                            <label class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-sky-600 text-white font-semibold hover:bg-sky-700 transition cursor-pointer">
                                <input type="file" accept="image/*" capture="user" class="sr-only" @change="fromFile($event)">
                                อัปโหลดรูปจากกล้อง/แกลเลอรี
                            </label>
                        </template>
                    </div>

                    <p x-show="submitError" class="text-sm text-red-600" x-text="submitError"></p>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 mt-4">
                ขึ้นอยู่กับสิทธิ์ของเบราว์เซอร์/อุปกรณ์ เซลฟีจะถูกใช้เพื่อยืนยันว่าคุณอยู่ที่โต๊ะจริงตามระบบ
            </p>
        </div>
    </div>
</x-app-layout>