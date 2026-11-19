<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A warehouse, shop or pickup point.
        Schema::create('commerce_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('city_code', 20)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('village_code', 20)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('ships_from')->default(true);
            $table->boolean('pickup')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // How many of a variant a location has. Their sum is the variant's stock_quantity.
        Schema::create('commerce_location_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('commerce_locations');
            $table->unsignedBigInteger('variant_id')->index();
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['location_id', 'variant_id']);
        });

        // Every change of a location's stock: orders, returns, adjustments, transfers.
        Schema::create('commerce_location_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('commerce_locations');
            $table->unsignedBigInteger('variant_id');
            $table->integer('delta');
            $table->integer('quantity_after');
            $table->string('reason', 20);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('transfer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['variant_id', 'location_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Stock moved from one location to another, with who moved it and why.
        Schema::create('commerce_location_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('commerce_locations');
            $table->foreignId('to_location_id')->constrained('commerce_locations');
            $table->unsignedBigInteger('variant_id')->index();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_location_transfers');
        Schema::dropIfExists('commerce_location_movements');
        Schema::dropIfExists('commerce_location_stock');
        Schema::dropIfExists('commerce_locations');
    }
};
