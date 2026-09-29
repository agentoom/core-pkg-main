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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id')->index();
            $table->string('external_id')->index();
            $table->string('order_number')->nullable();
            $table->string('status')->nullable();
            $table->string('currency')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax', 15, 4)->default(0);
            $table->decimal('shipping', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->default(0);
            $table->string('customer_email')->nullable();
            $table->dateTime('ordered_at')->index();
            $table->dateTime('creation_date')->nullable()->index();
            $table->dateTime('last_updated_date')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['resource_id', 'external_id']);
            $table->unique(['resource_id', 'order_number']);
            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
