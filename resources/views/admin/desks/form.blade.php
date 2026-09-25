@extends('layouts.admin')

@section('title', $desk ? 'แก้ไขโต๊ะทำงาน' : 'เพิ่มโต๊ะทำงาน')

@section('content')
    @php
        $isEdit = (bool) $desk;
        $zoneOptions = $zones->mapWithKeys(fn ($z) => [$z->id => $z->name]);
        $desksByZone = $zones->mapWithKeys(fn ($z) => [$z->id => $z->desks->map(fn ($d) => [
            'code' => $d->code,
            'x' => $d->x,
            'y' => $d->y,
            'is_active' => (bool) $d->is_active,
            'is_maintenance' => (bool) $d->is_maintenance,
        ])])->all();
    @endphp

    <div class="max-w-4xl rounded-xl bg-white p-6 shadow-sm border border-gray-200"
         x-data="deskForm({{ json_encode($desksByZone) }}, {{ json_encode($selectedZone) }})">
        <form method="POST"
              action="{{ $isEdit ? route('admin.desks.update', $desk) : route('admin.desks.store') }}">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="zone_id" :value="__('โซนพื้นที่')" />
                    <select id="zone_id" name="zone_id" required
                        @change="onZoneChange($event.target.value)"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">-- เลือกโซน --</option>
                        @foreach ($zoneOptions as $id => $name)
                            <option value="{{ $id }}" @selected(old('zone_id', $selectedZone ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('zone_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" :value="__('รหัสโต๊ะ')" />
                    <x-text-input id="code" class="mt-1 w-full" name="code" :value="old('code', $desk?->code)"
                        required placeholder="เช่น A-01" />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="label" :value="__('ชื่อโต๊ะ')" />
                <x-text-input id="label" class="mt-1 w-full" name="label" :value="old('label', $desk?->label)"
                    placeholder="เช่น โต๊ะ A1" />
                <x-input-error :messages="$errors->get('label')" class="mt-2" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="x" :value="__('พิกัด X (คอลัมน์)')" />
                    <x-text-input id="x" class="mt-1 w-full" type="number" min="0" max="99" name="x"
                        :value="old('x', $desk?->x ?? 1)" required />
                    <x-input-error :messages="$errors->get('x')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="y" :value="__('พิกัด Y (แถว)')" />
                    <x-text-input id="y" class="mt-1 w-full" type="number" min="0" max="99" name="y"
                        :value="old('y', $desk?->y ?? 1)" required />
                    <x-input-error :messages="$errors->get('y')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-3 pt-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1"
                            @checked(old('is_active', $desk?->is_active ?? true))
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        เปิดใช้งานโต๊ะนี้
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_maintenance" value="1"
                            @checked(old('is_maintenance', $desk?->is_maintenance ?? false))
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        กำลังปิดปรับปรุง / ซ่อมบำรุง
                    </label>
                </div>
                <div>
                    <x-input-label for="notes" :value="__('หมายเหตุ')" />
                    <x-text-input id="notes" class="mt-1 w-full" name="notes" :value="old('notes', $desk?->notes)"
                        placeholder="รายละเอียดเพิ่มเติม" />
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('admin.desks.index') }}"
                   class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">ยกเลิก</a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มโต๊ะ' }}
                </button>
            </div>
        </form>

        <div class="mt-6 border-t border-gray-100 pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">ตัวอย่างผังโต๊ะในโซนที่เลือก</p>
            <div class="overflow-x-auto pb-2">
                <div id="map-preview" class="min-h-[120px] rounded-lg border border-dashed border-gray-300 bg-gray-50/60"></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                <span class="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-100 border border-indigo-500 mr-1"></span>พร้อมใช้งาน ·
                <span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-100 border border-amber-500 mr-1 ml-2"></span>ปิดปรับปรุง ·
                <span class="inline-block h-2.5 w-2.5 rounded-sm bg-gray-100 border border-dashed border-gray-400 mr-1 ml-2"></span>ปิดใช้งาน
            </p>
        </div>
    </div>
@endsection