<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photo_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('example_image_url')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photo_guides'); }
};
