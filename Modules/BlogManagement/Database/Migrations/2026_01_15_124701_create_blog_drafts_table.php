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
        Schema::create('blog_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('blog_id');
            $table->foreignUuid('blog_category_id')->nullable();
            $table->string('writer')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('thumbnail');
            $table->date('published_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_drafts');
    }
};
