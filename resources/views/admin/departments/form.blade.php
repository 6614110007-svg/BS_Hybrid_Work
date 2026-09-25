@extends('layouts.admin')

@section('title', $department ? 'แก้ไขแผนก' : 'เพิ่มแผนก')

@section('content')
    @php $isEdit = (bool) $department; @endphp

    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm border border-gray-200">
        <form method="POST"
              action="{{ $isEdit ? route('admin.departments.update', $department) : route('admin.departments.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input-label for="code" :value="__('รหัสแผนก')" />
                <x-text-input id="code" class="mt-1 w-full" name="code" :value="old('code', $department?->code)"
                    placeholder="เช่น IT, HR" />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="name" :value="__('ชื่อแผนก')" />
                <x-text-input id="name" class="mt-1 w-full" name="name" :value="old('name', $department?->name)"
                    required placeholder="ชื่อแผนก" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="description" :value="__('รายละเอียด')" />
                <textarea id="description" name="description" rows="3"
                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $department?->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="mt-4">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $department?->is_active ?? true))
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    เปิดใช้งานแผนกนี้
                </label>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.departments.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">ยกเลิก</a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มแผนก' }}
                </button>
            </div>
        </form>
    </div>
@endsection