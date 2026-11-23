<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a partner's share pictures look (App\Lib\ShareLooks): one row per Food & Shops
 * brand, Market shop or bus agency. Their colour, the layout of the WhatsApp Status
 * picture, the item it shows big, and the picture they designed themselves, which E-Ride
 * staff can take down (removed_*).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('share_looks')) {
            return;
        }
        Schema::create('share_looks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 10);                     // store | market | bus
            $table->uuid('owner_id');                       // stores.id (the brand) | market_shops.id | bus_agencies.id
            $table->string('color', 7)->nullable();         // #RRGGBB; null = E-Ride red
            $table->string('layout', 20)->nullable();
            $table->string('item', 64)->nullable();         // the item shown big (store item id, Market item code)
            $table->string('design')->nullable();           // file in storage/app/public/share-design
            $table->unsignedSmallInteger('design_w')->nullable();
            $table->unsignedSmallInteger('design_h')->nullable();
            $table->boolean('strip')->default(true);        // E-Ride strip (QR, link) under their own picture
            $table->timestamp('design_at')->nullable();
            $table->string('removed_reason')->nullable();   // staff took their picture down
            $table->timestamp('removed_at')->nullable();
            $table->uuid('removed_by')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'owner_id']);
            $table->index('design_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_looks');
    }
};
