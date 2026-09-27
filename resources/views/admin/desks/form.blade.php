@extends('layouts.admin')

@section('title', $desk ? 'แก้ไขโต๊ะทำงาน' : 'เพิ่มโต๊ะทำงาน')

@section('content')
    <form method="POST"
          action="{{ $desk ? route('admin.desks.update', $desk) : route('admin.desks.store') }}"
          class="max-w-2xl rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        @if ($desk)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="zone_id" value="โซนพื้นที่" />
                <select id="zone_id" name="zone_id" required
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    <option value="">-- เลือกโซน --</option>
                    @foreach ($zones as $option)
                        <option value="{{ $option->zone_id }}"
                                @selected(old('zone_id', $desk?->zone_id) === $option->zone_id)>
                            {{ $option->zone_name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('zone_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="desk_number" value="หมายเลขโต๊ะ" />
                <x-text-input id="desk_number" name="desk_number" :value="old('desk_number', $desk?->desk_number)"
                              required maxlength="50" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('desk_number')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="map_position" value="ตำแหน่งบนผัง (รูปแบบ x,y)" />
                <x-text-input id="map_position" name="map_position" :value="old('map_position', $desk?->map_position)"
                              placeholder="เช่น 1,3" maxlength="50" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('map_position')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="desk_status" value="สถานะ" />
                <select id="desk_status" name="desk_status" required
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}"
                                @selected(old('desk_status', $desk?->desk_status ?? \App\Models\Desk::STATUS_AVAILABLE) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('desk_status')" class="mt-1" />
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500">
            สถานะ Reserved และ Checked-In ถูกคำนวณอัตโนมัติจากข้อมูลการจอง
        </p>

        <div class="mt-5 flex items-center gap-3">
            <x-primary-button>{{ $desk ? 'บันทึกการแก้ไข' : 'เพิ่มโต๊ะ' }}</x-primary-button>
            <a href="{{ route('admin.desks.index') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection
