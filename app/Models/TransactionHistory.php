<?php

namespace App\Models;

use App\Enums\TransactionHistoryAction;
use Database\Factories\TransactionHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionHistory extends Model
{
    /** @use HasFactory<TransactionHistoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'performed_by',
        'action',
        'old_status',
        'new_status',
        'workflow_step_name',
        'comment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => TransactionHistoryAction::class,
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

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
