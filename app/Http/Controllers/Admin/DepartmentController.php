<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::withCount('employees', 'zones')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.form', [
            'department' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Department::create($data);

        return Redirect::route('admin.departments.index')
            ->with('status', 'เพิ่มแผนกเรียบร้อยแล้ว');
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.form', [
            'department' => $department,
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validated($request, $department);

        $department->update($data);

        return Redirect::route('admin.departments.index')
            ->with('status', 'บันทึกข้อมูลแผนกเรียบร้อยแล้ว');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return Redirect::route('admin.departments.index')
                ->withErrors(['department' => 'ไม่สามารถลบแผนกที่มีพนักงานหรือโซนทำงานอยู่ได้']);
        }

        $department->delete();

        return Redirect::route('admin.departments.index')
            ->with('status', 'ลบแผนกเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $unique = $department ? $department->id : null;

        return $request->validate([
            'code' => ['nullable', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($unique)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}