{{--
    ช่องช่วยแนะนำเลขโต๊ะถัดไปและพิกัด grid ที่ว่าง
    เมื่อผู้ดูแลเปลี่ยนโซน ระบบจะดึงค่าจาก GET /admin/desks/suggest
    แล้วเติมให้อัตโนมัติเฉพาะช่องที่ยังไม่ได้พิมพ์เอง
--}}
<div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold text-indigo-900">ระบบช่วยแนะนำอัตโนมัติ</p>

        <button type="button" x-on:click="refresh()"
                class="rounded-lg border border-indigo-200 bg-white px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
            แนะนำใหม่
        </button>
    </div>

    <p x-show="loading" class="mt-2 text-xs text-indigo-700">กำลังคำนวณ…</p>

    <p x-show="!loading && error" x-text="error" class="mt-2 text-xs text-rose-600"></p>

    <p x-show="!loading && !error && hint" x-text="hint" class="mt-2 text-xs text-indigo-700"></p>

    <p x-show="!loading && !error && !hint" class="mt-2 text-xs text-indigo-700">
        เลือกโซนด้านบนก่อน ระบบจะเติมเลขโต๊ะถัดไปและช่องว่างบนผังให้
    </p>
</div>