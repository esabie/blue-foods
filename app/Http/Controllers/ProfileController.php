<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Name, email, phone and role are managed by management (U1); users only change their password.
     */
    public function edit(): Response
    {
        return Inertia::render('Profile/Edit');
    }
}
