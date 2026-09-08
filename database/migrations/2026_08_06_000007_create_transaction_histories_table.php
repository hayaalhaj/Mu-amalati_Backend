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
        Schema::create('transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('action');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('workflow_step_name')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('performed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['transaction_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_histories');
    }
};
