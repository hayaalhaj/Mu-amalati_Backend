<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_user_role_values(): void
    {
        $this->assertSame('employee', UserRole::Employee->value);
        $this->assertSame('manager', UserRole::Manager->value);
        $this->assertSame('admin', UserRole::Admin->value);
    }

    public function test_user_role_helpers(): void
    {
        $this->assertTrue(UserRole::Employee->isEmployee());
        $this->assertTrue(UserRole::Manager->isManager());
        $this->assertTrue(UserRole::Admin->isAdmin());
        $this->assertFalse(UserRole::Employee->isAdmin());
    }

    public function test_transaction_status_editable(): void
    {
        $this->assertTrue(TransactionStatus::Draft->isEditable());
        $this->assertTrue(TransactionStatus::Returned->isEditable());
        $this->assertFalse(TransactionStatus::Pending->isEditable());
        $this->assertFalse(TransactionStatus::Approved->isEditable());
    }

    public function test_transaction_status_active(): void
    {
        $this->assertTrue(TransactionStatus::Draft->isActive());
        $this->assertTrue(TransactionStatus::Pending->isActive());
        $this->assertTrue(TransactionStatus::Returned->isActive());
        $this->assertFalse(TransactionStatus::Approved->isActive());
        $this->assertFalse(TransactionStatus::Rejected->isActive());
        $this->assertFalse(TransactionStatus::Completed->isActive());
    }
}
