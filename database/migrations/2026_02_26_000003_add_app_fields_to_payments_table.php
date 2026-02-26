<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('app_id')
                  ->nullable()
                  ->after('merchant_id')
                  ->constrained('apps')
                  ->nullOnDelete();

            $table->foreignId('app_user_id')
                  ->nullable()
                  ->after('app_id')
                  ->constrained('app_users')
                  ->nullOnDelete();

            $table->index(['app_id', 'status']);
            $table->index('app_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['app_id']);
            $table->dropForeign(['app_user_id']);
            $table->dropColumn(['app_id', 'app_user_id']);
        });
    }
};
