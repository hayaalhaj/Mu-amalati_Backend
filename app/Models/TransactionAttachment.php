<?php

namespace App\Models;

use Database\Factories\TransactionAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttachment extends Model
{
    /** @use HasFactory<TransactionAttachmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'transaction_type_field_id',
        'uploaded_by',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            Transaction::class
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }

    public function transactionTypeField(): BelongsTo
    {
        return $this->belongsTo(
            TransactionTypeField::class,
            'transaction_type_field_id'
        );
    }
}