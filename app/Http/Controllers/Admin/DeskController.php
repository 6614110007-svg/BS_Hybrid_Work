<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class DeskController extends Controller
{
    public function index(): View
    {
        return view('admin.desks.index', [
            'zones' => Zone::with('department')
                ->with(['desks' => fn ($q) => $q->orderBy('code')])
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.desks.form', [
            'desk' => null,
            'zones' => Zone::where('is_active', true)->with('desks')->orderBy('sort_order')->get(),
            'selectedZone' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Desk::create($data);

        return Redirect::route('admin.desks.index')->with('status', 'เพิ่มโต๊ะทำงานเรียบร้อยแล้ว');
    }

    public function edit(Desk $desk): View
    {
        return view('admin.desks.form', [
            'desk' => $desk,
            'zones' => Zone::where('is_active', true)->with('desks')->orderBy('sort_order')->get(),
            'selectedZone' => $desk->zone_id,
        ]);
    }

    public function update(Request $request, Desk $desk): RedirectResponse
    {
        $data = $this->validated($request, $desk);

        $desk->update($data);

        return Redirect::route('admin.desks.index')->with('status', 'บันทึกข้อมูลโต๊ะเรียบร้อยแล้ว');
    }

    public function destroy(Desk $desk): RedirectResponse
    {
        $desk->delete();

        return Redirect::route('admin.desks.index')->with('status', 'ลบโต๊ะทำงานเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Desk $desk = null): array
    {
        $data = $request->validate([
            'zone_id' => ['required', 'exists:zones,id'],
            'code' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:100'],
            'x' => ['required', 'integer', 'min:0', 'max:99'],
            'y' => ['required', 'integer', 'min:0', 'max:99'],
            'is_active' => ['sometimes', 'boolean'],
            'is_maintenance' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:desks,code,'
                .($desk?->id ?? 'NULL').',id,zone_id,'.$data['zone_id']],
        ], [
            'code.unique' => 'รหัสโต๊ะนี้มีอยู่แล้วในโซนที่เลือก',
        ]);

        return $data;
    }
}