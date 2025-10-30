<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('account_name')->nullable();
            $table->json('external_ids')->nullable();
            $table->json('scopes')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['client_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_integrations');
    }
};
