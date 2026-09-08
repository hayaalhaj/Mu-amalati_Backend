<?php

namespace App\Models;

use Database\Factories\TransactionTypeWorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionTypeWorkflowStep extends Model
{
    /** @use HasFactory<TransactionTypeWorkflowStepFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_type_id',
        'department_id',
        'step_order',
        'name',
        'is_final',
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
            'is_final' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function transactionWorkflowStepSnapshots(): HasMany
    {
        return $this->hasMany(TransactionWorkflowStep::class, 'original_workflow_step_id');
    }
}
