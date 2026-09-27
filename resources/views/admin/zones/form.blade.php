@extends('layouts.admin')

@section('title', $zone ? 'แก้ไขโซนพื้นที่' : 'เพิ่มโซนพื้นที่')

@section('content')
    <form method="POST"
          action="{{ $zone ? route('admin.zones.update', $zone) : route('admin.zones.store') }}"
          class="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        @if ($zone)
            @method('PUT')
        @endif

        <div>
            <x-input-label for="zone_name" value="ชื่อโซน" />
            <x-text-input id="zone_name" name="zone_name" :value="old('zone_name', $zone?->zone_name)"
                          required maxlength="255" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('zone_name')" class="mt-1" />
        </div>

        <p class="mt-3 text-xs text-gray-500">
            จำนวนโต๊ะในโซนจะถูกนับอัตโนมัติจากโต๊ะที่สังกัดอยู่ ไม่ต้องกรอกเอง
        </p>

        <div class="mt-5 flex items-center gap-3">
            <x-primary-button>{{ $zone ? 'บันทึกการแก้ไข' : 'เพิ่มโซน' }}</x-primary-button>
            <a href="{{ route('admin.zones.index') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection
