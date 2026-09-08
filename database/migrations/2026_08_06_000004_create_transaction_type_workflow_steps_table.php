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
        Schema::create('transaction_type_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_type_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->foreign('transaction_type_id')->references('id')->on('transaction_types')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->unique(['transaction_type_id', 'step_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_type_workflow_steps');
    }
};
