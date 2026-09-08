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
        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger(
                'transaction_type_field_id'
            )->nullable()->after('transaction_id');

            $table->foreign(
                'transaction_type_field_id'
            )
                ->references('id')
                ->on('transaction_type_fields')
                ->nullOnDelete();

            $table->index(
                'transaction_type_field_id'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_attachments', function (Blueprint $table) {
            $table->dropForeign([
                'transaction_type_field_id',
            ]);

            $table->dropIndex([
                'transaction_type_field_id',
            ]);

            $table->dropColumn(
                'transaction_type_field_id'
            );
        });
    }
};