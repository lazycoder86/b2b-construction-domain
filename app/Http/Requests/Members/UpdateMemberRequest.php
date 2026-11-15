<?php

namespace App\Http\Requests\Members;

use App\Enums\OrganizationRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = OrganizationRole::tryFrom((string) $this->input('role'));

        return $role === null
            ? $this->user()->can('updateMembers', $this->organization())
            : $this->user()->can('update', [$this->membership(), $role]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }

    public function organization(): Organization
    {
        /** @var Organization */
        return $this->route('organization');
    }

    public function membership(): Membership
    {
        /** @var User $member */
        $member = $this->route('member');

        return $this->organization()->membershipFor($member) ?? abort(404);
    }

    public function role(): OrganizationRole
    {
        return OrganizationRole::from($this->validated('role'));
    }
}
