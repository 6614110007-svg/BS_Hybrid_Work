@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}

    {{-- ช่องบังคับกรอกแสดงดาวสีแดง --}}
    @if ($required)
        <span class="text-rose-500" aria-hidden="true">*</span>
        <span class="sr-only">(จำเป็นต้องกรอก)</span>
    @endif
</label>