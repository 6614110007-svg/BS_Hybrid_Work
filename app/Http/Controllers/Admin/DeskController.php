<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Zone;
use App\Services\DeskStatusManager;
use App\Support\DeskGrid;
use App\Support\OptionCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeskController extends Controller
{
    public function index(): View
    {
        return view('admin.desks.index', [
            'desks' => Desk::with('zone')->orderBy('zone_id')->orderBy('desk_number')->paginate(20),
            'zones' => OptionCache::zones(),
        ]);
    }

    public function create(): View
    {
        return view('admin.desks.form', [
            'desk' => null,
            'zones' => OptionCache::zones(),
            'statuses' => Desk::statusOptions(),
            'suggestNumber' => Desk::suggestNumber(null),
            'suggestGrid' => Desk::suggestGridPosition(null),
        ]);
    }

    /**
     * ค่าที่แนะนำสำหรับโต๊ะใหม่ ใช้เติมฟอร์มให้ผู้ดูแลไม่ต้องคิดเลขเอง
     * คืนเลขโต๊ะถัดไปและช่อง grid ที่ยังว่างของโซนที่เลือก
     */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'string', Rule::exists('zone', 'zone_id')],
        ]);

        return response()->json([
            'desk_number' => Desk::suggestNumber($data['zone_id']),
            'map_position' => Desk::suggestGridPosition($data['zone_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Desk::create($this->validated($request));

        OptionCache::flush();

        return to_route('admin.desks.index')->with('success', 'เพิ่มโต๊ะเรียบร้อยแล้ว');
    }

    public function edit(Desk $desk): View
    {
        return view('admin.desks.form', [
            'desk' => $desk,
            'zones' => OptionCache::zones(),
            'statuses' => Desk::statusOptions(),
            // ข้ามตัวโต๊ะที่กำลังแก้ไขออกจากตัวเลือก เพื่อไม่ให้แนะนำช่องที่ตัวเองใช้อยู่
            'suggestNumber' => Desk::suggestNumber($desk->zone_id),
            'suggestGrid' => Desk::suggestGridPosition($desk->zone_id),
        ]);
    }

    public function update(Request $request, Desk $desk): RedirectResponse
    {
        $desk->update($this->validated($request, $desk));

        OptionCache::flush();

        return to_route('admin.desks.index')->with('success', 'บันทึกข้อมูลโต๊ะเรียบร้อยแล้ว');
    }

    public function destroy(Desk $desk): RedirectResponse
    {
        if ($desk->bookings()->active()->exists()) {
            return to_route('admin.desks.index')
                ->with('error', 'ไม่สามารถลบโต๊ะที่มีการจองที่ยังใช้งานอยู่ได้');
        }

        $desk->delete();
        OptionCache::flush();

        return to_route('admin.desks.index')->with('success', 'ลบโต๊ะเรียบร้อยแล้ว');
    }

    /**
     * เปิด/ปิดซ่อมบำรุงโต๊ะ
     * (สถานะ Reserved / Checked-In ถูกคำนวณจากการจองอัตโนมัติ)
     */
    public function toggleStatus(Request $request, Desk $desk, DeskStatusManager $desks): RedirectResponse
    {
        $data = $request->validate([
            'desk_status' => ['required', Rule::in(array_keys(Desk::statusOptions()))],
        ]);

        if ($desk->bookings()->active()->exists() && $data['desk_status'] === Desk::STATUS_MAINTENANCE) {
            return back()->with('error', 'ไม่สามารถปิดซ่อมบำรุงโต๊ะที่มีการจองที่ยังใช้งานอยู่ได้');
        }

        $desk->forceFill(['desk_status' => $data['desk_status']])->save();

        // จำนวนโต๊ะที่ใช้งานได้ถูกใช้ในสถิติแดชบอร์ด จึงต้องล้าง cache โครงสร้าง
        OptionCache::flush();

        if ($data['desk_status'] === Desk::STATUS_AVAILABLE) {
            $desks->sync($desk);
        }

        return back()->with('success', "เปลี่ยนสถานะโต๊ะ {$desk->desk_number} เป็น {$desk->statusLabel()} แล้ว");
    }

    private function validated(Request $request, ?Desk $desk = null): array
    {
        return $request->validate([
            'zone_id' => ['required', 'string', Rule::exists('zone', 'zone_id')],
            'desk_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('desk', 'desk_number')->ignore($desk),
            ],
            'map_position' => [
                'nullable',
                'string',
                'max:50',
                // ต้องอยู่ในขอบเขตผัง และห้ามชนกับโต๊ะตัวอื่นในโซนเดียวกัน
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $desk): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! DeskGrid::isValid($value)) {
                        $fail("พิกัดผังต้องอยู่ระหว่าง 0-".DeskGrid::MAX_COLUMN
                            .' ตามแกน และ 0-'.DeskGrid::MAX_ROW.' ตามแถว');

                        return;
                    }

                    $clash = Desk::query()
                        ->where('zone_id', $request->input('zone_id'))
                        ->where('map_position', $value)
                        ->when($desk, fn ($query) => $query->where('desk_id', '!=', $desk->getKey()))
                        ->exists();

                    if ($clash) {
                        $fail('พิกัดผังนี้มีโต๊ะอื่นในโซนอยู่แล้ว กรุณาเลือกช่องที่ว่าง');
                    }
                },
            ],
            'desk_status' => ['required', Rule::in(array_keys(Desk::statusOptions()))],
        ]);
    }
}
