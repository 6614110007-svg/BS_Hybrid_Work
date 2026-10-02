<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\OptionCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('employees')->orderBy('department_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.form', ['department' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($this->validated($request));

        OptionCache::flush();

        return to_route('admin.departments.index')->with('success', 'เพิ่มแผนกเรียบร้อยแล้ว');
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.form', ['department' => $department]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        OptionCache::flush();

        return to_route('admin.departments.index')->with('success', 'บันทึกข้อมูลแผนกเรียบร้อยแล้ว');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return to_route('admin.departments.index')
                ->with('error', 'ไม่สามารถลบแผนกที่ยังมีพนักงานอยู่ได้');
        }

        $department->delete();

        OptionCache::flush();

        return to_route('admin.departments.index')->with('success', 'ลบแผนกเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'department_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('department', 'department_name')->ignore($department?->getKey()),
            ],
        ]);
    }
}
