@extends('layouts.admin')

@section('title', $zone ? 'แก้ไขโซนพื้นที่' : 'เพิ่มโซนพื้นที่')

@section('content')
    <form method="POST" enctype="multipart/form-data"
          action="{{ $zone ? route('admin.zones.update', $zone) : route('admin.zones.store') }}"
          class="max-w-2xl rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        @if ($zone)
            @method('PUT')
        @endif

        <div>
            <x-input-label for="zone_name" value="ชื่อโซน" required />
            <x-text-input id="zone_name" name="zone_name" :value="old('zone_name', $zone?->zone_name)"
                          required maxlength="255" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('zone_name')" class="mt-1" />
        </div>

        <div class="mt-4">
            <label for="zone_description" class="block text-sm font-medium text-gray-700">
                คำบรรยาย / บรรยากาศโซน
            </label>
            <textarea id="zone_description" name="zone_description" rows="3" maxlength="1000"
                      placeholder="เช่น โซนติดหน้าต่าง ตอนเช้ามีแสงธรรมชาติ มีที่นั่งริมระเบียงด้านนอก"
                      class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('zone_description', $zone?->zone_description) }}</textarea>
            <x-input-error :messages="$errors->get('zone_description')" class="mt-1" />
            <p class="mt-1 text-xs text-gray-500">ใช้แสดงเป็นคำอธิบายย่อในหน้าจองโต๊ะของพนักงาน</p>
        </div>

        <div class="mt-4">
            <label for="zone_image" class="block text-sm font-medium text-gray-700">
                รูปปกโซน
            </label>

            @if ($zone?->imageUrl())
                <div class="mt-2 flex items-start gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <img src="{{ $zone->imageUrl() }}" alt="รูปปกโซน {{ $zone->zone_name }}"
                         class="h-24 w-36 rounded-lg object-cover">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="remove_zone_image" value="1"
                               class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                        ลบรูปปกเดิม
                    </label>
                </div>
            @endif

            <input id="zone_image" name="zone_image" type="file" accept="image/jpeg,image/png,image/webp"
                   class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
            <x-input-error :messages="$errors->get('zone_image')" class="mt-1" />
            <p class="mt-1 text-xs text-gray-500">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 4 MB</p>
        </div>

        <p class="mt-4 text-xs text-gray-500">
            จำนวนโต๊ะในโซนจะถูกนับอัตโนมัติจากโต๊ะที่สังกัดอยู่ ไม่ต้องกรอกเอง
        </p>

        <div class="mt-5 flex items-center gap-3">
            <x-primary-button>{{ $zone ? 'บันทึกการแก้ไข' : 'เพิ่มโซน' }}</x-primary-button>
            <a href="{{ route('admin.zones.index') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection