<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StopImpersonationController extends Controller
{
    public function __invoke(Impersonation $impersonation): RedirectResponse
    {
        abort_unless($impersonation->active(), 404);

        $target = Auth::user();
        $admin = $impersonation->stop();

        if ($admin === null) {
            return redirect()->route('login');
        }

        return $target instanceof User
            ? redirect()->route('admin.users.show', $target)
            : redirect()->route('admin.dashboard');
    }
}
