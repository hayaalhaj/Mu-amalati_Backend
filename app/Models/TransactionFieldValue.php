<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'transaction_type_field_id',
        'value',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            Transaction::class
        );
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(
            TransactionTypeField::class,
            'transaction_type_field_id'
        );
    }
}