<?php

namespace App\Http\Controllers\Settings;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GDPR access request: everything stored about the signed-in user, as JSON.
 */
class ExportPersonalDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        Audit::record('user.data_exported', organization: false);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
                'passkeys' => $user->passkeys()->count(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'organizations' => $user->memberships()->with('organization:id,name,slug')->get()->map(fn (Membership $membership) => [
                'organization' => $membership->organization->name,
                'role' => $membership->role->value,
                'joined_at' => $membership->joined_at?->toIso8601String(),
            ]),
            'activity' => AuditLog::query()->where('user_id', $user->id)->latest('id')->limit(5000)->get()
                ->map(fn (AuditLog $entry) => $entry->toDisplayArray()),
        ], options: JSON_PRETTY_PRINT)->header('Content-Disposition', 'attachment; filename="personal-data.json"');
    }
}
