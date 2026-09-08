<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'manager_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function transactionTypes(): HasMany
    {
        return $this->hasMany(TransactionType::class, 'destination_department_id');
    }

    public function workflowStepDefinitions(): HasMany
    {
        return $this->hasMany(TransactionTypeWorkflowStep::class);
    }

    public function sourceTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_department_id');
    }

    public function destinationTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_department_id');
    }

    public function currentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'current_department_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
