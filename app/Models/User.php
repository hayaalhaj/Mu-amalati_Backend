<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'role',
        'department_id',
        'status',
        'annual_leave_days',
        'used_leave_days',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'annual_leave_days' => 'integer',
            'used_leave_days' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class
        );
    }

    public function createdTransactions(): HasMany
    {
        return $this->hasMany(
            Transaction::class,
            'created_by'
        );
    }

    public function reviewedWorkflowSteps(): HasMany
    {
        return $this->hasMany(
            TransactionWorkflowStep::class,
            'reviewed_by'
        );
    }

    public function performedHistoryActions(): HasMany
    {
        return $this->hasMany(
            TransactionHistory::class,
            'performed_by'
        );
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(
            TransactionAttachment::class,
            'uploaded_by'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class
        );
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(
            Attendance::class
        );
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(
            Department::class,
            'manager_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isManager(): bool
    {
        return $this->role->isManager();
    }

    public function isEmployee(): bool
    {
        return $this->role->isEmployee();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * The department ids managed by this user.
     *
     * @return array<int, int>
     */
    public function managedDepartmentIds(): array
    {
        return $this->managedDepartments()
            ->pluck('id')
            ->all();
    }

    /**
     * Get the user's attendance for today.
     */
    public function todayAttendance(): ?Attendance
    {
        return $this->attendances()
            ->whereDate(
                'date',
                now()->toDateString()
            )
            ->latest('id')
            ->first();
    }

    /**
     * Determine whether the user is currently on approved leave.
     */
    public function isOnLeave(): bool
    {
        return $this->createdTransactions()
            ->whereHas(
                'transactionType',
                function ($query) {
                    $query->where(
                        'name_en',
                        'Leave Request'
                    );
                }
            )
            ->where(
                'status',
                'approved'
            )
            ->whereDate(
                'start_date',
                '<=',
                now()->toDateString()
            )
            ->whereDate(
                'end_date',
                '>=',
                now()->toDateString()
            )
            ->exists();
    }

    /**
     * Get the current work status.
     *
     * Possible values:
     * - on_leave
     * - working
     * - inactive
     */
    public function workStatus(): string
    {
        if ($this->isOnLeave()) {
            return 'on_leave';
        }

        if (! $this->isActive()) {
            return 'inactive';
        }

        $attendance =
            $this->todayAttendance();

        if (
            $attendance &&
            $attendance->check_in_at &&
            ! $attendance->check_out_at
        ) {
            return 'working';
        }

        return 'inactive';
    }
}