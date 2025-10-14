<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('photo_guide_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message');
            $table->dateTime('schedule_time');
            $table->enum('repeat', ['none','daily','weekly','monthly'])->default('none');
            $table->enum('status', ['pending','sent','completed'])->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('reminders'); }
};
