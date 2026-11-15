<?php

namespace App\Http\Requests\Invitations;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = OrganizationRole::tryFrom((string) $this->input('role'));

        // An invalid role is a validation error, not an authorization one.
        return $role === null
            ? $this->user()->can('inviteMembers', $this->organization())
            : $this->user()->can('invite', [Invitation::class, $this->organization()->id, $role]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }

    public function organization(): Organization
    {
        /** @var Organization */
        return $this->route('organization');
    }

    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->validated('role'));
    }
}
