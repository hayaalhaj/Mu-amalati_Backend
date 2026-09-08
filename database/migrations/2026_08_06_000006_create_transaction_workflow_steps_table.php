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
        Schema::create('transaction_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('original_workflow_step_id')->nullable();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->string('status', 20)->default('waiting');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('original_workflow_step_id')->references('id')->on('transaction_type_workflow_steps')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['transaction_id', 'step_order']);
            $table->index(['department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_workflow_steps');
    }
};
