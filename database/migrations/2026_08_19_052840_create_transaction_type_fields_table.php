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
        Schema::create('transaction_type_fields', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('transaction_type_id');

            $table->string('name_en');
            $table->string('name_ar');

            $table->string('field_type', 30);

            $table->boolean('is_required')->default(false);

            $table->string('placeholder_en')->nullable();
            $table->string('placeholder_ar')->nullable();

            $table->json('options')->nullable();

            $table->unsignedInteger('field_order')->default(1);

            $table->timestamps();

            $table->foreign('transaction_type_id')
                ->references('id')
                ->on('transaction_types')
                ->cascadeOnDelete();

            $table->index([
                'transaction_type_id',
                'field_order',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_type_fields');
    }
};