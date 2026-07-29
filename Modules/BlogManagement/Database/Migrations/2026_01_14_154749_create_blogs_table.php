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
        Schema::create('blogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('readable_id')->unique();
            $table->string('slug')->unique();
            $table->foreignUuid('blog_category_id')->nullable();
            $table->string('writer')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('thumbnail');
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedInteger('click_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_image');
            $table->unsignedTinyInteger('is_drafted')->default(0);
            $table->unsignedTinyInteger('is_published')->default(0);
            $table->date('published_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
