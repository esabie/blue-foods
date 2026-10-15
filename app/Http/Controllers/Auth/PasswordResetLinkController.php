<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Emails a reset link to an active account. The reply is the same whether or not the
     * address has an account, so the form cannot be used to find out who works here.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::sendResetLink([
            'email' => mb_strtolower(trim($request->input('email'))),
            'status' => UserStatus::Active->value,
        ]);

        return back()->with('status', 'If that email belongs to an active account, a link to reset the password is on its way.');
    }
}
