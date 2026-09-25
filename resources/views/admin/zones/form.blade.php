@extends('layouts.admin')

@section('title', $zone ? 'แก้ไขโซนพื้นที่' : 'เพิ่มโซนพื้นที่')

@section('content')
    @php $isEdit = (bool) $zone; @endphp

    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm border border-gray-200">
        <form method="POST"
              action="{{ $isEdit ? route('admin.zones.update', $zone) : route('admin.zones.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="code" :value="__('รหัสโซน')" />
                    <x-text-input id="code" class="mt-1 w-full" name="code" :value="old('code', $zone?->code)"
                        placeholder="เช่น Z-A" />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="floor" :value="__('ชั้น')" />
                    <x-text-input id="floor" class="mt-1 w-full" type="number" min="1" max="99" name="floor"
                        :value="old('floor', $zone?->floor)" placeholder="เช่น 2" />
                    <x-input-error :messages="$errors->get('floor')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="name" :value="__('ชื่อโซน')" />
                <x-text-input id="name" class="mt-1 w-full" name="name" :value="old('name', $zone?->name)"
                    required placeholder="เช่น โซน A - ฝั่งหน้าต่าง" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="department_id" :value="__('แผนกที่สังกัด')" />
                <select id="department_id" name="department_id"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- ไม่ระบุแผนก --</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(old('department_id', $zone?->department_id) == $dept->id)>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="description" :value="__('รายละเอียด')" />
                <textarea id="description" name="description" rows="2"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $zone?->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="sort_order" :value="__('ลำดับการแสดงผล')" />
                    <x-text-input id="sort_order" class="mt-1 w-full" type="number" min="0" name="sort_order"
                        :value="old('sort_order', $zone?->sort_order ?? 0)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
                <div class="flex items-end pb-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1"
                            @checked(old('is_active', $zone?->is_active ?? true))
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        เปิดใช้งานโซนนี้
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.zones.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">ยกเลิก</a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มโซน' }}
                </button>
            </div>
        </form>
    </div>
@endsection