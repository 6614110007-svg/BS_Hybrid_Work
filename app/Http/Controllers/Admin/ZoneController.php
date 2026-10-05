<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Support\OptionCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ZoneController extends Controller
{
    private const IMAGE_PATH = 'zones';

    public function index(): View
    {
        return view('admin.zones.index', [
            'zones' => Zone::withCount('desks')->orderBy('zone_name')->get(),
        ]);
    }

    /**
     * จำนวนโต๊ะสดของแต่ละโซน สำหรับอัปเดตหน้าจัดการโซนแบบ real-time
     */
    public function realtime(): JsonResponse
    {
        return response()->json([
            'zones' => Zone::withCount('desks')
                ->orderBy('zone_name')
                ->get(['zone_id', 'zone_name'])
                ->map(fn (Zone $zone): array => [
                    'zone_id' => $zone->zone_id,
                    'zone_name' => $zone->zone_name,
                    'desks_count' => $zone->desks_count,
                ]),
        ]);
    }

    public function create(): View
    {
        return view('admin.zones.form', ['zone' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        unset($data['remove_zone_image']);

        if ($request->hasFile('zone_image')) {
            $data['zone_image'] = $request->file('zone_image')->store(self::IMAGE_PATH, 'public');
        } else {
            unset($data['zone_image']);
        }

        Zone::create($data);

        OptionCache::flush();

        return to_route('admin.zones.index')->with('success', 'เพิ่มโซนทำงานเรียบร้อยแล้ว');
    }

    public function edit(Zone $zone): View
    {
        return view('admin.zones.form', ['zone' => $zone]);
    }

    public function update(Request $request, Zone $zone): RedirectResponse
    {
        $data = $this->validated($request, $zone);

        // เลือกรูปใหม่ → อัปโหลดและลบรูปเดิมทิ้ง
        if ($request->hasFile('zone_image')) {
            $this->deleteImage($zone->zone_image);
            $data['zone_image'] = $request->file('zone_image')->store(self::IMAGE_PATH, 'public');
        } elseif ($request->boolean('remove_zone_image')) {
            $this->deleteImage($zone->zone_image);
            $data['zone_image'] = null;
        } else {
            unset($data['zone_image'], $data['remove_zone_image']);
        }

        $zone->update($data);

        OptionCache::flush();

        return to_route('admin.zones.index')->with('success', 'บันทึกข้อมูลโซนเรียบร้อยแล้ว');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        if ($zone->desks()->exists()) {
            return to_route('admin.zones.index')
                ->with('error', 'ไม่สามารถลบโซนที่ยังมีโต๊ะอยู่ได้');
        }

        $this->deleteImage($zone->zone_image);

        $zone->delete();

        OptionCache::flush();

        return to_route('admin.zones.index')->with('success', 'ลบโซนเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Zone $zone = null): array
    {
        return $request->validate([
            'zone_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('zone', 'zone_name')->ignore($zone),
            ],
            'zone_description' => ['nullable', 'string', 'max:1000'],
            'zone_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_zone_image' => ['nullable', 'boolean'],
        ]);
    }

    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}