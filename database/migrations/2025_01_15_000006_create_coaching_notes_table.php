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
        Schema::create('coaching_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');

            // Transcript reference
            $table->integer('line_index_start')->nullable();
            $table->integer('line_index_end')->nullable();
            $table->string('timestamp_start')->nullable();
            $table->string('timestamp_end')->nullable();
            $table->text('transcript_text')->nullable();

            // Note content
            $table->text('note_text');
            $table->foreignId('rubric_category_id')->nullable()->constrained()->nullOnDelete();

            // Objection tracking
            $table->boolean('is_objection')->default(false);
            $table->foreignId('objection_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('objection_outcome', ['overcame', 'failed', 'partial'])->nullable();
            $table->boolean('is_exemplar')->default(false);

            $table->timestamps();

            $table->index(['author_id', 'is_objection']);
            $table->index(['call_id', 'author_id']);
        });

        Schema::create('call_objection_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('objection_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('tagged_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['call_id', 'objection_type_id', 'tagged_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_objection_tags');
        Schema::dropIfExists('coaching_notes');
    }
};
