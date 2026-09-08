<?php

namespace App\Models;

use App\Enums\TransactionPriority;
use App\Enums\TransactionStatus;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_number',
        'created_by',
        'transaction_type_id',
        'source_department_id',
        'destination_department_id',
        'current_workflow_step_id',
        'current_department_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'priority',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'returned_at',
        'last_action_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TransactionPriority::class,
            'status' => TransactionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function sourceDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }

    public function destinationDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'destination_department_id');
    }

    public function currentWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(
            TransactionWorkflowStep::class,
            'current_workflow_step_id'
        );
    }

    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'current_department_id'
        );
    }

    public function lastActionUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'last_action_by'
        );
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(
            TransactionWorkflowStep::class
        )->orderBy('step_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(
            TransactionHistory::class
        )->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            TransactionAttachment::class
        );
    }
    public function fieldValues(): HasMany
{
    return $this->hasMany(
        TransactionFieldValue::class
    );
}
    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeFilter(
        Builder $query,
        array $filters
    ): Builder {
        return $query
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) =>
                    $q->where('status', $status)
            )
            ->when(
                $filters['priority'] ?? null,
                fn ($q, $priority) =>
                    $q->where('priority', $priority)
            )
            ->when(
                $filters['transaction_type_id'] ?? null,
                fn ($q, $id) =>
                    $q->where('transaction_type_id', $id)
            )
            ->when(
                $filters['source_department_id'] ?? null,
                fn ($q, $id) =>
                    $q->where('source_department_id', $id)
            )
            ->when(
                $filters['destination_department_id'] ?? null,
                fn ($q, $id) =>
                    $q->where('destination_department_id', $id)
            )
            ->when(
                $filters['current_department_id'] ?? null,
                fn ($q, $id) =>
                    $q->where('current_department_id', $id)
            )
            ->when(
                $filters['created_by'] ?? null,
                fn ($q, $id) =>
                    $q->where('created_by', $id)
            )
            ->when(
                $filters['created_from'] ?? null,
                fn ($q, $date) =>
                    $q->whereDate(
                        'created_at',
                        '>=',
                        $date
                    )
            )
            ->when(
                $filters['created_to'] ?? null,
                fn ($q, $date) =>
                    $q->whereDate(
                        'created_at',
                        '<=',
                        $date
                    )
            )
            ->when(
                $filters['search'] ?? null,
                function ($q, $search) {
                    $q->where(function ($sub) use ($search) {
                        $sub
                            ->where(
                                'title',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'transaction_number',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->when(
                $filters['sort'] ?? null,
                fn ($q, $sort) =>
                    $this->applySort($q, $sort)
            )
            ->when(
                ! isset($filters['sort']),
                fn ($q) =>
                    $q->orderBy(
                        'created_at',
                        'desc'
                    )
            );
    }

    /**
     * Apply the requested sort order.
     */
    protected function applySort(
        Builder $query,
        string $sort
    ): Builder {
        return match ($sort) {
            'oldest' =>
                $query->orderBy(
                    'created_at',
                    'asc'
                ),

            'priority' =>
                $query
                    ->orderByRaw(
                        "CASE priority
                            WHEN 'high' THEN 0
                            WHEN 'medium' THEN 1
                            WHEN 'low' THEN 2
                            ELSE 3
                        END"
                    )
                    ->orderBy(
                        'created_at',
                        'desc'
                    ),

            'transaction_number' =>
                $query->orderBy(
                    'transaction_number'
                ),

            default =>
                $query->orderBy(
                    'created_at',
                    'desc'
                ),
        };
    }
}