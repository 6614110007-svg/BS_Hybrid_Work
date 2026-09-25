<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with('department')->where('role', User::ROLE_EMPLOYEE);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(fn ($q) => $q
                ->whereRaw('lower(name) like lower(?)', ["%{$search}%"])
                ->orWhereRaw('lower(email) like lower(?)', ["%{$search}%"])
                ->orWhereRaw('lower(employee_code) like lower(?)', ["%{$search}%"]));
        }

        if ($request->filled('department_id')) {
            if ($request->input('department_id') === 'none') {
                $query->whereNull('department_id');
            } else {
                $query->where('department_id', $request->input('department_id'));
            }
        }

        if ($request->filled('status')) {
            $query->where('employee_status', $request->input('status'));
        }

        return view('admin.employees.index', [
            'employees' => $query->latest()->paginate(15)->withQueryString(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['search', 'department_id', 'status']),
            'tempPassword' => session('temp_password'),
            'createdEmployee' => session('created_employee'),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', [
            'user' => null,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $tempPassword = Str::password(10, letters: true, numbers: true, symbols: true, spaces: false);

        $user = User::create([
            ...$data,
            'password' => $tempPassword,
            'employee_status' => User::STATUS_ACTIVE,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        return Redirect::route('admin.employees.index')->with([
            'status' => 'สร้างบัญชีพนักงานเรียบร้อยแล้ว',
            'temp_password' => $tempPassword,
            'created_employee' => $user->email,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.employees.form', [
            'user' => $user,
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $user->update($data);

        return Redirect::route('admin.employees.index')->with('status', 'บันทึกข้อมูลพนักงานเรียบร้อยแล้ว');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return Redirect::route('admin.employees.index')
                ->withErrors(['employee' => 'ไม่สามารถระงับบัญชีของตนเองได้']);
        }

        $user->update([
            'employee_status' => $user->employee_status === User::STATUS_ACTIVE
                ? User::STATUS_INACTIVE
                : User::STATUS_ACTIVE,
        ]);

        $action = $user->employee_status === User::STATUS_ACTIVE ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';

        return Redirect::route('admin.employees.index')
            ->with('status', "{$action}บัญชี {$user->name} เรียบร้อยแล้ว");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return Redirect::route('admin.employees.index')
                ->withErrors(['employee' => 'ไม่สามารถลบบัญชีของตนเองได้']);
        }

        $user->delete();

        return Redirect::route('admin.employees.index')->with('status', 'ลบพนักงานเรียบร้อยแล้ว');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $ignore = $user ? $user->id : null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('users', 'employee_code')->ignore($ignore)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:employee,admin'],
        ]);
    }
}