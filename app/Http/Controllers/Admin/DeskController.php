<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Zone;
use App\Services\DeskStatusManager;
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
            'zones' => Zone::orderBy('zone_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.desks.form', [
            'desk' => null,
            'zones' => Zone::orderBy('zone_name')->get(),
            'statuses' => Desk::statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Desk::create($this->validated($request));

        return to_route('admin.desks.index')->with('success', 'เพิ่มโต๊ะเรียบร้อยแล้ว');
    }

    public function edit(Desk $desk): View
    {
        return view('admin.desks.form', [
            'desk' => $desk,
            'zones' => Zone::orderBy('zone_name')->get(),
            'statuses' => Desk::statusOptions(),
        ]);
    }

    public function update(Request $request, Desk $desk): RedirectResponse
    {
        $desk->update($this->validated($request, $desk));

        return to_route('admin.desks.index')->with('success', 'บันทึกข้อมูลโต๊ะเรียบร้อยแล้ว');
    }

    public function destroy(Desk $desk): RedirectResponse
    {
        if ($desk->bookings()->active()->exists()) {
            return to_route('admin.desks.index')
                ->with('error', 'ไม่สามารถลบโต๊ะที่มีการจองที่ยังใช้งานอยู่ได้');
        }

        $desk->delete();

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
                Rule::unique('desk', 'desk_number')->ignore($desk?->getKey()),
            ],
            'map_position' => ['nullable', 'string', 'max:50'],
            'desk_status' => ['required', Rule::in(array_keys(Desk::statusOptions()))],
        ]);
    }
}
