<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.zones.index', [
            'zones' => Zone::withCount('desks')->orderBy('zone_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.zones.form', ['zone' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Zone::create($this->validated($request));

        return to_route('admin.zones.index')->with('success', 'เพิ่มโซนทำงานเรียบร้อยแล้ว');
    }

    public function edit(Zone $zone): View
    {
        return view('admin.zones.form', ['zone' => $zone]);
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $zone->update($this->validated($request, $zone));

        return to_route('admin.zones.index')->with('success', 'บันทึกข้อมูลโซนเรียบร้อยแล้ว');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        if ($zone->desks()->exists()) {
            return to_route('admin.zones.index')
                ->with('error', 'ไม่สามารถลบโซนที่ยังมีโต๊ะอยู่ได้');
        }

        $zone->delete();

        return to_route('admin.zones.index')->with('success', 'ลบโซนเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Zone $zone = null): array
    {
        return $request->validate([
            'zone_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('zone', 'zone_name')->ignore($zone?->getKey()),
            ],
        ]);
    }
}
