<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'check_in_at',
        'check_out_at',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function isWorking(): bool
    {
        return $this->check_in_at !== null &&
            $this->check_out_at === null;
    }

    public function isCheckedOut(): bool
    {
        return $this->check_in_at !== null &&
            $this->check_out_at !== null;
    }
}