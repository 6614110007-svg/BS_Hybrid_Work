{{--
    ตัวเลือก avatar สัตว์ 20 แบบ ใช้ร่วมกันระหว่าง modal ตั้งค่าบัญชีและหน้าโปรไฟล์
    รับ: $animals (array<string,array>), $name (ชื่อฟิลด์), $selected (คีย์ที่เลือกไว้)

    ทุกครั้งที่ผู้ใช้กดเลือก จะยิง event 'avatar-selected' ขึ้นไปให้ส่วนที่อยู่นอกคอมโพเนนต์
    (เช่น ภาพตัวอย่างขนาดใหญ่ในหน้าโปรไฟล์) รู้ว่าค่าเปลี่ยนแล้ว เพราะ x-model
    เขียนค่าลง input hidden ตรง ๆ โดยไม่ได้ยิง event 'input' ตามมา
--}}
@php
    $avatarField = 'avatar-picker-' . $name;
@endphp

<div x-data="{ current: @js($selected) }">
    <p class="text-sm font-medium text-gray-700">เลือก avatar ของคุณ <span class="text-rose-500">*</span></p>
    <p class="mt-0.5 text-xs text-gray-500">เลือกได้ 1 รูป จากทั้งหมด {{ count($animals) }} รูป</p>

    <input type="hidden" name="{{ $name }}" x-model="current" value="{{ $selected }}">

    <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-10">
        @foreach ($animals as $key => $animal)
            <button type="button" x-on:click="current = @js($key); $dispatch('avatar-selected', @js($key))"
                    data-avatar-option="{{ $key }}"
                    x-bind:aria-pressed="current === @js($key) ? 'true' : 'false'"
                    class="group flex flex-col items-center gap-1 rounded-xl border-2 p-2 transition"
                    x-bind:class="current === @js($key)
                        ? 'border-indigo-500 bg-indigo-50 shadow-sm'
                        : 'border-transparent bg-gray-50 hover:border-indigo-200 hover:bg-gray-100'"
                    aria-label="เลือก avatar {{ $animal['label'] }}">
                <span class="flex h-11 w-11 items-center justify-center rounded-full"
                      style="background-color: {{ $animal['fill'] }}22">
                    {!! \App\Support\AnimalAvatar::svg($key, 'h-10 w-10') !!}
                </span>
                <span class="w-full truncate text-center text-[10px] leading-tight text-gray-600">{{ $animal['label'] }}</span>
            </button>
        @endforeach
    </div>

    @error($name)
        <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>