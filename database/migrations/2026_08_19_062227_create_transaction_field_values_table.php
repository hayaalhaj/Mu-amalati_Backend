<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_field_values', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('transaction_type_field_id');

            $table->text('value')->nullable();

            $table->timestamps();

            $table->foreign('transaction_id')
                ->references('id')
                ->on('transactions')
                ->cascadeOnDelete();

            $table->foreign('transaction_type_field_id')
                ->references('id')
                ->on('transaction_type_fields')
                ->cascadeOnDelete();

            $table->unique([
                'transaction_id',
                'transaction_type_field_id',
            ]);

            $table->index('transaction_type_field_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'transaction_field_values'
        );
    }
};