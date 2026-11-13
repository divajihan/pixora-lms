<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Entry point after login (Fortify "home"): sends users to their role's dashboard.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $role = $user->primaryRole();

        abort_if($role === null, 403, 'Akun Anda belum memiliki peran.');

        return redirect()->route($role->dashboardRoute());
    }
}
