<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Determine if this status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
