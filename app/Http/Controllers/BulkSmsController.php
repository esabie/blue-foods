<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\BulkSms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BulkSmsController extends Controller
{
    public function __construct(private readonly BulkSms $bulkSms) {}

    public function create(): Response
    {
        $people = User::human()
            ->where('status', UserStatus::Active)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'phone']);

        $staffCounts = [
            'marketing' => $people->where('role', Role::Marketing)->count(),
            'warehouse' => $people->where('role', Role::Warehouse)->count(),
            'management' => $people->where('role', Role::Management)->count(),
            'all_staff' => $people->count(),
            'selected' => null,
        ];

        $onlineCustomers = $this->bulkSms->onlineCustomerPhones()->count();

        return Inertia::render('BulkSms/Create', [
            'staff_audiences' => collect(BulkSms::STAFF_AUDIENCES)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'count' => $staffCounts[$value] ?? null,
            ])->values(),
            'public_audiences' => collect(BulkSms::PUBLIC_AUDIENCES)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'count' => $value === 'online_customers' ? $onlineCustomers : null,
            ])->values(),
            'people' => $people->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'phone' => $u->phone,
            ]),
            'recent' => AuditLog::query()
                ->where('entity', 'bulk_sms')
                ->latest('id')
                ->limit(8)
                ->with('user:id,name')
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'by' => $log->user?->name ?? 'System',
                    'audience' => $log->after_json['audience_label'] ?? $log->after_json['audience'] ?? '—',
                    'channel' => $log->after_json['channel'] ?? 'staff',
                    'count' => $log->after_json['recipient_count'] ?? 0,
                    'message' => $log->after_json['message'] ?? '',
                    'sent_at' => $log->created_at?->toIso8601String(),
                ]),
            'max_length' => BulkSms::MAX_LENGTH,
            'max_public' => BulkSms::MAX_PUBLIC_RECIPIENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', 'string', Rule::in(array_keys(BulkSms::AUDIENCES))],
            'message' => ['required', 'string', 'max:'.BulkSms::MAX_LENGTH],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'phones' => ['nullable', 'string', 'max:20000'],
        ]);

        $result = $this->bulkSms->send(
            $data['audience'],
            $data['message'],
            $request->user(),
            $data['user_ids'] ?? [],
            $data['phones'] ?? '',
        );

        $n = $result['count'];

        return redirect()
            ->route('bulk-sms.create')
            ->with('success', $n === 1 ? '1 text queued.' : "{$n} texts queued.");
    }
}
