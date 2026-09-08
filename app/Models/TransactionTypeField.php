<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionTypeField extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_type_id',
        'name_en',
        'field_type',
        'is_required',
        'placeholder_en',
        'placeholder_ar',
        'options',
        'field_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options' => 'array',
            'field_order' => 'integer',
        ];
    }

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(
            TransactionType::class
        );
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            TransactionFieldValue::class,
            'transaction_type_field_id'
        );
    }
}