<?php

namespace App\Http\Controllers;

use App\Actions\Approvals\RequestActionApproval;
use App\Enums\ApprovalType;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RequestActionApproval $requestApproval,
    ) {}

    public function index(): View
    {
        $canBootstrapOwner = User::query()->where('role', UserRole::Owner->value)->where('is_active', true)->count() === 1;

        return view('employees.index', [
            'employees' => User::query()->where('role', '!=', UserRole::Customer)->latest()->paginate(20),
            'roles' => array_values(array_filter(
                UserRole::cases(),
                fn (UserRole $role) => $role !== UserRole::Customer && ($role !== UserRole::Owner || $canBootstrapOwner),
            )),
            'canBootstrapOwner' => $canBootstrapOwner,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $canBootstrapOwner = User::query()->where('role', UserRole::Owner->value)->where('is_active', true)->count() === 1;
        $roleValues = collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role === UserRole::Customer || ($role === UserRole::Owner && ! $canBootstrapOwner))
            ->pluck('value')
            ->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($roleValues)],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $employee = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $this->audit->log('employee.created', $employee, after: $employee->only(['name', 'email', 'phone', 'role', 'is_active']));
        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::EmployeeAccessChange,
            $employee,
            ['data' => ['role' => $data['role'], 'is_active' => true]],
            "Aktivasi akses pegawai baru {$employee->name}.",
        );

        $message = $data['role'] === UserRole::Owner->value
            ? "Calon owner kedua dibuat nonaktif. Owner saat ini dapat menyetujui bootstrap satu kali ({$approval->request_number})."
            : "Akun dibuat nonaktif dan menunggu persetujuan owner lain ({$approval->request_number}).";

        return back()->with('status', $message);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        if ($employee->id === $request->user()->id && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages(['is_active' => 'Owner tidak dapat menonaktifkan akun sendiri.']);
        }

        abort_if($employee->role === UserRole::Customer, 404);
        $allowed = collect(UserRole::cases())->reject(fn (UserRole $role) => $role === UserRole::Customer)->pluck('value')->all();
        $data = $request->validate([
            'role' => ['required', Rule::in($allowed)],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($employee->id === $request->user()->id && ($data['role'] !== UserRole::Owner->value || ! $data['is_active'])) {
            throw ValidationException::withMessages(['role' => 'Owner tidak dapat menurunkan atau menonaktifkan aksesnya sendiri.']);
        }
        $approval = $this->requestApproval->handle(
            $request->user(),
            ApprovalType::EmployeeAccessChange,
            $employee,
            ['data' => $data],
            "Perubahan akses untuk {$employee->name}.",
        );

        return back()->with('status', "Perubahan akses menunggu persetujuan owner lain ({$approval->request_number}).");
    }
}
