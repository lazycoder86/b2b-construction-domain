<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\DeleteUser;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            Audit::record('user.email_changed', organization: false, metadata: [
                'from' => $request->user()->getOriginal('email'),
                'to' => $request->user()->email,
            ]);
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, DeleteUser $deleteUser): RedirectResponse
    {
        $user = $request->user();

        // Validate before logging out so a blocked deletion keeps the user signed in.
        $deleteUser->ensureCanDelete($user);

        // Logged before logout so the entry has an actor snapshot (user_id is nulled on delete).
        Audit::record('user.deleted', organization: false, actor: $user);

        Auth::logout();

        $deleteUser->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
