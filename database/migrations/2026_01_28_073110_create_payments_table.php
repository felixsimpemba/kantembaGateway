<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->enum('status', ['initialized', 'pending', 'succeeded', 'failed', 'refunded'])->default('initialized');
            $table->string('payment_method')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            // Foreign key
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');

            // Indexes
            $table->index('merchant_id');
            $table->index('reference');
            $table->index('status');
            $table->index('idempotency_key');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
