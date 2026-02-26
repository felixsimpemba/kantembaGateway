<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('external_user_id')->comment('User ID from the client app');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // A user ID must be unique within an app
            $table->unique(['app_id', 'external_user_id']);
            $table->index(['app_id', 'email']);
            $table->index(['app_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
