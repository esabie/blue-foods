<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashBook;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(CashBook $cash): Response
    {
        $held = DB::table('stock_balances as b')
            ->join('locations as l', 'l.id', '=', 'b.location_id')
            ->whereNotNull('l.user_id')
            ->groupBy('l.user_id')
            ->selectRaw('l.user_id, SUM(b.quantity) AS units')
            ->pluck('units', 'user_id');
        $lastSale = Sale::notReversed()->groupBy('member_user_id')->selectRaw('member_user_id, MAX(created_at) AS at')->pluck('at', 'member_user_id');
        $owed = $cash->positions()->keyBy('user_id');

        $users = User::human()->orderBy('status')->orderBy('name')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'status' => $user->status->value,
            'locked' => $user->isLocked(),
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'team' => $user->role === Role::Marketing ? [
                'units_held' => (int) ($held[$user->id] ?? 0),
                'cash_owed_pesewas' => $owed[$user->id]['outstanding_pesewas'] ?? 0,
                'cash_overdue' => $owed[$user->id]['overdue'] ?? false,
                'last_sale_at' => isset($lastSale[$user->id]) ? CarbonImmutable::parse($lastSale[$user->id], 'UTC')->toIso8601String() : null,
            ] : null,
        ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => collect(Role::cases())->map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeContact($request);

        $data = $request->validate([
            ...$this->contactRules(),
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user = User::create([...$data, 'status' => UserStatus::Active]);

        return back()->with('success', "Account created for {$user->name}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureHuman($user);
        $this->normalizeContact($request);

        $data = $request->validate($this->contactRules($user));

        $user->update($data);

        return back()->with('success', "{$user->name} updated.");
    }

    /**
     * U1: disabled users cannot sign in; their history remains.
     */
    public function disable(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }

        $user->update(['status' => UserStatus::Disabled]);

        return back()->with('success', "{$user->name} disabled.");
    }

    public function enable(User $user): RedirectResponse
    {
        $this->ensureHuman($user);
        $user->update(['status' => UserStatus::Active]);

        return back()->with('success', "{$user->name} enabled.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureHuman($user);

        $data = $request->validate([
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        return back()->with('success', "Password reset for {$user->name}.");
    }

    /**
     * The online shop system account records online sales and can never sign in.
     */
    private function ensureHuman(User $user): void
    {
        abort_if($user->email === User::SYSTEM_EMAIL, 404);
    }

    private function normalizeContact(Request $request): void
    {
        $request->merge([
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
            'phone' => Phone::normalize($request->input('phone')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function contactRules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^\+\d{9,15}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
        ];
    }
}
