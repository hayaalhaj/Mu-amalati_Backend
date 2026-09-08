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
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('destination_department_id');
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('destination_department_id')->references('id')->on('departments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
