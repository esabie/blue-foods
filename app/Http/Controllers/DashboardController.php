<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send each role to its home screen.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            Role::Marketing => redirect()->route('member.home'),
            Role::Warehouse => redirect()->route('dashboards.stock'),
            Role::Management => redirect()->route('dashboards.overview'),
        };
    }
}
