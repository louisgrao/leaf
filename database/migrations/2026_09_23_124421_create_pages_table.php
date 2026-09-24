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

        Schema::create('item_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('item_bases')->nullOnDelete();
            $table->string('base_title');
            $table->string('slug')->unique()->nullable();
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2)->default(0);
            $table->boolean('base_status')->default(true);
            $table->timestamps();
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_base_id')->constrained('item_bases')->cascadeOnDelete();
            $table->string('title');
            $table->string('sku')->unique()->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('quantity')->default(0);
            $table->boolean('status')->default(true);
            $table->json('json_attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('item_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_item_id')->constrained('item_bases')->cascadeOnDelete();
            $table->foreignId('secondary_item_id')->constrained('item_bases')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['primary_item_id', 'secondary_item_id']);
        });
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
