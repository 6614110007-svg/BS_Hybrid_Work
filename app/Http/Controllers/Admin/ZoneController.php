<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.zones.index', [
            'zones' => Zone::with('department')->withCount('desks')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.zones.form', [
            'zone' => null,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Zone::create($data);

        return Redirect::route('admin.zones.index')->with('status', 'เพิ่มโซนพื้นที่เรียบร้อยแล้ว');
    }

    public function edit(Zone $zone): View
    {
        return view('admin.zones.form', [
            'zone' => $zone,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $data = $this->validated($request, $zone);

        $zone->update($data);

        return Redirect::route('admin.zones.index')->with('status', 'บันทึกข้อมูลโซนเรียบร้อยแล้ว');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        if ($zone->desks()->count() > 0) {
            return Redirect::route('admin.zones.index')
                ->withErrors(['zone' => 'ไม่สามารถลบโซนที่มีโต๊ะทำงานอยู่ได้ โปรดลบโต๊ะภายในโซนก่อน']);
        }

        $zone->delete();

        return Redirect::route('admin.zones.index')->with('status', 'ลบโซนพื้นที่เรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Zone $zone = null): array
    {
        $unique = $zone ? $zone->id : null;

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', Rule::unique('zones', 'code')->ignore($unique)],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'floor' => ['nullable', 'integer', 'min:1', 'max:99'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}