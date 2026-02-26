<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->uuid('app_id')->unique();
            $table->string('app_secret')->nullable()->comment('Used for HMAC webhook signing');
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('webhook_url')->nullable()->comment('Overrides merchant webhook URL');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
