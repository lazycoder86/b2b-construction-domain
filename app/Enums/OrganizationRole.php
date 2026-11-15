<?php

namespace App\Enums;

/**
 * Roles a user can hold within an organization.
 *
 * Permissions live in code (not the database) until custom roles are needed.
 * Check them via Policies; never trust the frontend's copy of these flags.
 *
 * Owner-only abilities (granted through '*'): organization.delete,
 * organization.security, members.manage-owners, ownership.transfer,
 * billing.manage (subscribe, change plan, payment methods), organization.export.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => [
                'organization.view',
                'organization.update',
                'members.view',
                'members.invite',
                'members.update',
                'members.remove',
                'billing.view',
                'audit.view',
            ],
            self::Member, self::Viewer => [
                'organization.view',
                'members.view',
            ],
        };
    }

    public function can(string $permission): bool
    {
        $permissions = $this->permissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * Roles this role may grant to others (via invitation or role change).
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => self::cases(),
            self::Admin => [self::Admin, self::Member, self::Viewer],
            self::Member, self::Viewer => [],
        };
    }

    public function canAssign(self $role): bool
    {
        return in_array($role, $this->assignableRoles(), true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()], self::cases());
    }
}
