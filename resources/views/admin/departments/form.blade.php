@extends('layouts.admin')

@section('title', $department ? 'แก้ไขแผนก' : 'เพิ่มแผนก')

@section('content')
    <form method="POST"
          action="{{ $department ? route('admin.departments.update', $department) : route('admin.departments.store') }}"
          class="max-w-xl rounded-xl border border-gray-200 bg-white p-5">
        @csrf
        @if ($department)
            @method('PUT')
        @endif

        <div>
            <x-input-label for="department_name" value="ชื่อแผนก" required />
            <x-text-input id="department_name" name="department_name" :value="old('department_name', $department?->department_name)"
                          required maxlength="255" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('department_name')" class="mt-1" />
        </div>

        <div class="mt-5 flex items-center gap-3">
            <x-primary-button>{{ $department ? 'บันทึกการแก้ไข' : 'เพิ่มแผนก' }}</x-primary-button>
            <a href="{{ route('admin.departments.index') }}" class="text-sm text-gray-600 underline">ยกเลิก</a>
        </div>
    </form>
@endsection
