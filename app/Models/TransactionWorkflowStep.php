<?php

namespace App\Models;

use App\Enums\WorkflowStepStatus;
use Database\Factories\TransactionWorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionWorkflowStep extends Model
{
    /** @use HasFactory<TransactionWorkflowStepFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'department_id',
        'original_workflow_step_id',
        'step_order',
        'name',
        'status',
        'reviewed_by',
        'comment',
        'reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'status' => WorkflowStepStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function originalWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(TransactionTypeWorkflowStep::class, 'original_workflow_step_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
