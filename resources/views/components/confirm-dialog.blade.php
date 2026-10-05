{{--
    Modal ยืนยันการทำรายการ ใช้ร่วมกันทุกหน้า (แทน window.confirm ของเบราว์เซอร์)
    วางไว้ครั้งเดียวใน layout แล้วเรียกใช้ผ่าน data-confirm ที่ฟอร์ม
--}}
<div x-data="confirmDialog" x-cloak x-show="open" x-on:keydown.escape="cancel"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title"
     x-on:click.self="cancel">

    <div class="absolute inset-0 bg-gray-900/50" aria-hidden="true"></div>

    <div x-show="open" x-transition.duration.150ms
         class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">

        <div class="flex items-start gap-4 p-6">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                  :class="{
                      'bg-rose-100 text-rose-600': tone === 'danger',
                      'bg-indigo-100 text-indigo-600': tone === 'primary',
                  }"
                  aria-hidden="true">
                <svg x-show="tone === 'danger'" class="h-6 w-6" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                </svg>
                <svg x-show="tone === 'primary'" x-cloak class="h-6 w-6" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <h2 id="confirm-dialog-title" class="text-base font-semibold text-gray-900" x-text="title"></h2>
                <p class="mt-1.5 text-sm leading-relaxed text-gray-600" x-text="message"></p>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" x-ref="cancelButton" x-on:click="cancel"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                ยกเลิก
            </button>
            <button type="button" x-on:click="approve"
                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white transition"
                    :class="{
                        'bg-rose-600 hover:bg-rose-700': tone === 'danger',
                        'bg-indigo-600 hover:bg-indigo-700': tone === 'primary',
                    }"
                    x-text="confirmText"></button>
        </div>
    </div>
</div>