<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Support\OptionCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');
        $role = $request->query('role');

        $employees = Employee::with('department')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('employee_fullname', 'like', "%{$search}%")
                    ->orWhere('employee_email', 'like', "%{$search}%")
                    ->orWhere('employee_tel', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('employee_status', $status))
            ->when($role, fn ($query) => $query->where('employee_role', $role))
            ->orderBy('employee_fullname')
            ->paginate(20)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => self::departmentOptions(),
            'roles' => Employee::roleOptions(),
            'statuses' => Employee::statusOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['employee_password'] = $data['password'];
        unset($data['password'], $data['password_confirmation']);

        $employee = Employee::create($data);
        OptionCache::flush();

        return to_route('admin.employees.index')
            ->with('success', "เพิ่มพนักงาน {$employee->employee_fullname} เรียบร้อยแล้ว");
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request, $employee);

        if (! empty($data['password'])) {
            $data['employee_password'] = $data['password'];
        }

        unset($data['password'], $data['password_confirmation']);

        $employee->fill($data)->save();
        OptionCache::flush();

        return to_route('admin.employees.index')
            ->with('success', "บันทึกข้อมูล {$employee->employee_fullname} เรียบร้อยแล้ว");
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->bookings()->active()->exists()) {
            return to_route('admin.employees.index')
                ->with('error', 'ไม่สามารถลบพนักงานที่มีการจองที่ยังใช้งานอยู่ได้');
        }

        $employee->delete();
        OptionCache::flush();

        return to_route('admin.employees.index')->with('success', 'ลบพนักงานเรียบร้อยแล้ว');
    }

    public function toggleStatus(Employee $employee): RedirectResponse
    {
        if ($employee->isActive() && $employee->bookings()->active()->exists()) {
            return back()->with('error', 'ไม่สามารถระงับบัญชีที่มีการจองที่ยังใช้งานอยู่ได้');
        }

        $employee->forceFill([
            'employee_status' => $employee->isActive() ? Employee::STATUS_INACTIVE : Employee::STATUS_ACTIVE,
        ])->save();
        OptionCache::flush();

        return back()->with('success', "เปลี่ยนสถานะ {$employee->employee_fullname} เป็น {$employee->statusLabel()} แล้ว");
    }

    private function formData(?Employee $employee = null): array
    {
        return [
            'employee' => $employee,
            'departments' => self::departmentOptions(),
            'roles' => Employee::roleOptions(),
            'statuses' => Employee::statusOptions(),
        ];
    }

    /**
     * ตัวเลือกแผนก — cache ไว้ เพราะอ่านซ้ำทุกครั้งที่เปิดหน้ารายชื่อ/ฟอร์ม
     * แต่เปลี่ยนบ่อยมาก โดย DepartmentController จะล้าง cache ให้เอง
     *
     * @return \Illuminate\Support\Collection<int, Department>
     */
    public static function departmentOptions()
    {
        return OptionCache::departments();
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_fullname' => ['required', 'string', 'max:255'],
            'employee_tel' => ['required', 'string', 'max:20'],
            'employee_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('employee', 'employee_email')->ignore($employee?->getKey()),
            ],
            'employee_role' => ['required', Rule::in(array_keys(Employee::roleOptions()))],
            'employee_status' => ['required', Rule::in(array_keys(Employee::statusOptions()))],
            'department_id' => ['required', 'string', Rule::exists('department', 'department_id')],
            'password' => [$employee ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);
    }
}
