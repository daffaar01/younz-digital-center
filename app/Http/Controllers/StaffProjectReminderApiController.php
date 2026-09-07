<?php

namespace App\Http\Controllers;

use App\Models\ClientProjectReminder;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffProjectReminderApiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $items = ClientProjectReminder::query()
            ->orderByRaw('active_until is null, active_until asc')
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json(['data' => [
            'items' => $items->map(fn (ClientProjectReminder $item): array => $this->projectData($item))->values(),
            'summary' => [
                'total' => $items->count(),
                'expiring_soon' => $items->filter(fn (ClientProjectReminder $item): bool => $this->expiryState($item) === 'expiring_soon')->count(),
                'expired' => $items->filter(fn (ClientProjectReminder $item): bool => $this->expiryState($item) === 'expired')->count(),
            ],
            'options' => [
                'project_types' => ['web', 'android'],
                'payment_statuses' => ['belum_diisi', 'dp', 'lunas'],
                'contact_methods' => ['belum_diisi', 'whatsapp', 'tatap_muka'],
            ],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);
        $project = ClientProjectReminder::create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit->log('client_project_reminder.created', $project, after: $this->auditData($project));

        return response()->json(['message' => 'Pengingat proyek berhasil ditambahkan.', 'data' => $this->projectData($project)], 201);
    }

    public function update(Request $request, ClientProjectReminder $projectReminder): JsonResponse
    {
        $this->authorizeManager($request);
        $before = $this->auditData($projectReminder);
        if (! $request->exists('active_from') && $projectReminder->active_from) {
            $request->merge(['active_from' => $projectReminder->active_from->toDateString()]);
        }
        if (! $request->exists('active_until') && $projectReminder->active_until) {
            $request->merge(['active_until' => $projectReminder->active_until->toDateString()]);
        }
        $projectReminder->fill($this->validated($request, true));
        $projectReminder->updated_by = $request->user()->id;
        $projectReminder->save();
        $this->audit->log('client_project_reminder.updated', $projectReminder, before: $before, after: $this->auditData($projectReminder));

        return response()->json(['message' => 'Pengingat proyek berhasil diperbarui.', 'data' => $this->projectData($projectReminder)]);
    }

    public function revealCredentials(Request $request, ClientProjectReminder $projectReminder): JsonResponse
    {
        $this->authorizeManager($request);
        $token = $request->user()->currentAccessToken();
        abort_unless(
            $request->user()->tokenCan('project-reminders:reveal')
            && $token?->created_at?->greaterThanOrEqualTo(now()->subMinutes(15)),
            403,
            'Verifikasi dua faktor perlu diperbarui. Silakan masuk kembali.'
        );
        $this->audit->log('client_project_reminder.credentials_revealed', $projectReminder, metadata: ['step_up' => 'recent_totp_login']);

        return response()->json(['data' => [
            'hosting_login_email' => $projectReminder->hosting_login_email,
            'hosting_login_password' => $projectReminder->hosting_login_password,
        ]]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(
            $request->user()->isStaff()
            && $request->user()->tokenCan('staff:dashboard')
            && $request->user()->hasRole('owner', 'admin'),
            403
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'project_name' => [$required, 'string', 'max:180'],
            'customer_name' => [$required, 'string', 'max:180'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:2000'],
            'project_type' => [$required, Rule::in(['web', 'android'])],
            'hosting_provider' => ['nullable', 'string', 'max:100'],
            'active_from' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'hosting_login_email' => ['nullable', 'email', 'max:180'],
            'hosting_login_password' => ['nullable', 'string', 'max:1000'],
            'payment_status' => [$required, Rule::in(['belum_diisi', 'dp', 'lunas'])],
            'amount' => ['nullable', 'integer', 'min:0'],
            'transaction_date' => ['nullable', 'date'],
            'contact_method' => [$required, Rule::in(['belum_diisi', 'whatsapp', 'tatap_muka'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** @return array<string, mixed> */
    private function projectData(ClientProjectReminder $item): array
    {
        $email = $item->hosting_login_email;

        return [
            'id' => $item->id,
            'project_name' => $item->project_name,
            'customer_name' => $item->customer_name,
            'whatsapp' => $item->whatsapp,
            'email' => $item->email,
            'address' => $item->address,
            'project_type' => $item->project_type,
            'hosting_provider' => $item->hosting_provider,
            'active_from' => $item->active_from?->toDateString(),
            'active_until' => $item->active_until?->toDateString(),
            'days_remaining' => $item->active_until ? today()->diffInDays($item->active_until, false) : null,
            'expiry_state' => $this->expiryState($item),
            'hosting_login_email_masked' => $this->maskEmail($email),
            'has_hosting_password' => filled($item->hosting_login_password),
            'payment_status' => $item->payment_status,
            'amount' => $item->amount,
            'transaction_date' => $item->transaction_date?->toDateString(),
            'contact_method' => $item->contact_method,
            'notes' => $item->notes,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    private function expiryState(ClientProjectReminder $item): string
    {
        if (! $item->active_until) {
            return 'unknown';
        }

        $days = today()->diffInDays($item->active_until, false);

        return $days < 0 ? 'expired' : ($days <= 90 ? 'expiring_soon' : 'active');
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email ? '••••••' : null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, 1).str_repeat('*', max(3, mb_strlen($local) - 2)).mb_substr($local, -1);

        return $visible.'@'.$domain;
    }

    /** @return array<string, mixed> */
    private function auditData(ClientProjectReminder $item): array
    {
        return [
            'project_type' => $item->project_type,
            'hosting_provider' => $item->hosting_provider,
            'active_from' => $item->active_from?->toDateString(),
            'active_until' => $item->active_until?->toDateString(),
            'expiry_state' => $this->expiryState($item),
            'payment_status' => $item->payment_status,
            'amount' => $item->amount,
            'transaction_date' => $item->transaction_date?->toDateString(),
            'contact_method' => $item->contact_method,
            'has_hosting_credentials' => filled($item->hosting_login_email) || filled($item->hosting_login_password),
        ];
    }
}
