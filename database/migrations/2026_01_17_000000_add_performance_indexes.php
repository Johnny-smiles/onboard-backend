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
        Schema::table('photos', function (Blueprint $table) {
            $table->index('approved');
            $table->index('review_status');
            $table->index(['client_id', 'created_at']);
            $table->index(['approved', 'review_status']);
        });

        Schema::table('photo_publications', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'scheduled_at']); // Critical for dispatchDue()
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'read_at']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['approved']);
            $table->dropIndex(['review_status']);
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropIndex(['approved', 'review_status']);
        });

        Schema::table('photo_publications', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'scheduled_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_id', 'read_at']);
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
