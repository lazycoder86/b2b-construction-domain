<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending_deletion';

    public function isAccessible(): bool
    {
        return $this === self::Active;
    }
}
