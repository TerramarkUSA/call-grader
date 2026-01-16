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
        Schema::create('rubric_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('external_id');
            $table->text('description')->nullable();
            $table->decimal('weight', 3, 2);
            $table->json('scoring_criteria');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rubric_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('external_id');
            $table->text('description')->nullable();
            $table->enum('type', ['positive', 'negative']);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('objection_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('objection_types');
        Schema::dropIfExists('rubric_checkpoints');
        Schema::dropIfExists('rubric_categories');
    }
};
