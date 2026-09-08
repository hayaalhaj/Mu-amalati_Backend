<?php

namespace App\Models;

use Database\Factories\TransactionTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    /** @use HasFactory<TransactionTypeFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name_en',
        'name_ar',
        'description',
        'destination_department_id',
        'requires_attachment',
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
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function destinationDepartment(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'destination_department_id'
        );
    }

    public function fields(): HasMany
    {
        return $this->hasMany(
            TransactionTypeField::class
        )->orderBy('field_order');
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(
            TransactionTypeWorkflowStep::class
        )->orderBy('step_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            Transaction::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }
}