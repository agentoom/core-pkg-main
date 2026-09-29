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
        Schema::table('ai_credit_topups', function (Blueprint $table) {
            $table->ulid('uuid')->after('id')->nullable()->unique();
            $table->string('token')->after('uuid')->nullable();
            $table->string('status')->after('token')->default('awaiting_payment');
            $table->string('user_email')->after('status')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('payment_received_at')->nullable();

            $table->index(['uuid', 'token', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_credit_topups', function (Blueprint $table) {
            $table->dropIndex(['uuid', 'token', 'status']);
            $table->dropColumn(['uuid', 'token', 'status', 'user_email', 'completed_at', 'payment_received_at']);
        });
    }
};
