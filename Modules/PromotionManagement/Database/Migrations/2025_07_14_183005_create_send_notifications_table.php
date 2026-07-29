<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('targeted_users');
            $table->text('image')->nullable();
            $table->unsignedSmallInteger('is_active')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_notifications');
    }
};
