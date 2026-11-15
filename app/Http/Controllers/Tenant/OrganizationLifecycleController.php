<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Organizations\ScheduleOrganizationDeletion;
use App\Concerns\PasswordValidationRules;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Jobs\ExportOrganizationData;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationLifecycleController extends Controller
{
    use PasswordValidationRules;

    public function export(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('export', $organization);

        ExportOrganizationData::dispatch($organization->id, $request->user()->id);
        Audit::record('organization.export_requested', $organization, $organization);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export started. We will email you a download link.')]);

        return back();
    }

    public function download(Organization $organization, string $export): StreamedResponse
    {
        Gate::authorize('export', $organization);

        $path = "exports/{$organization->public_id}/{$export}.zip";
        abort_unless(preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $export) === 1 && Storage::disk('local')->exists($path), 404);

        Audit::record('organization.export_downloaded', $organization, $organization, ['export' => $export]);

        return Storage::disk('local')->download($path, "{$organization->slug}-export-".now()->format('Y-m-d').'.zip');
    }

    public function destroy(Request $request, Organization $organization, ScheduleOrganizationDeletion $schedule): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        $request->validate([
            'password' => $this->currentPasswordRules(),
            'confirmation' => ['required', 'string', 'in:'.$organization->slug],
        ], ['confirmation.in' => __('Type the organization URL (:slug) to confirm.', ['slug' => $organization->slug])]);

        $schedule->handle($organization, $request->user());

        return to_route('organizations.deletion.show', ['organization' => $organization->slug]);
    }
}
