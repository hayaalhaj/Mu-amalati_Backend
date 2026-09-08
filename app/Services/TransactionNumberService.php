<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionNumberService
{
    /**
     * Generate a unique transaction number like TRX-2026-000001.
     *
     * Uses an atomic upsert on the transactions table to reduce the risk of
     * duplicate numbers when multiple requests are processed concurrently.
     */
    public function generate(): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($year) {
            $prefix = 'TRX-'.$year.'-';

            $last = Transaction::where('transaction_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderBy('transaction_number', 'desc')
                ->value('transaction_number');

            $sequence = $last ? (int) substr($last, strlen($prefix)) : 0;
            $sequence++;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
