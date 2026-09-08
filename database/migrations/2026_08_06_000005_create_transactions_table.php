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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('transaction_type_id');
            $table->unsignedBigInteger('source_department_id');
            $table->unsignedBigInteger('destination_department_id');
            $table->unsignedBigInteger('current_workflow_step_id')->nullable();
            $table->unsignedBigInteger('current_department_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('last_action_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->restrictOnDelete();
            $table->foreign('source_department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('destination_department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->foreign('current_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('last_action_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'priority']);
            $table->index(['created_by', 'status']);
            $table->index(['current_department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
