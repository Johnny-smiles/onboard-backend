<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('capture_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shot_recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('channel')->default('email');
            $table->string('target')->nullable();
            $table->timestamp('send_at');
            $table->string('repeat_interval')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_active', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_reminders');
    }
};
