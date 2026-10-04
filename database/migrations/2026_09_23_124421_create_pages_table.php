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
        
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('item_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('item_bases')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('Published');
            $table->boolean('is_sellable')->default(false); // Added dedicated column
            $table->json('json_specifications')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_base_id')->constrained('item_bases')->cascadeOnDelete();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('json_specifications')->nullable();
            $table->json('json_options')->nullable();
            
            // Sellable properties
            $table->string('sku')->unique()->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('shipping_weight', 8, 2)->nullable();
            $table->json('shipping_dimensions')->nullable();
            
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('item_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_item_id')->constrained('item_bases')->cascadeOnDelete();
            $table->foreignId('secondary_item_id')->constrained('item_bases')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['primary_item_id', 'secondary_item_id']);
        });    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_item');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('item_bases');
        Schema::dropIfExists('pages');
    }
};
