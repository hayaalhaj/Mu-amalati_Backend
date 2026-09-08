<?php

namespace App\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * Determine if this role is the employee role.
     */
    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }

    /**
     * Determine if this role is the manager role.
     */
    public function isManager(): bool
    {
        return $this === self::Manager;
    }

    /**
     * Determine if this role is the admin role.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
