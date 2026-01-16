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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('graded_by')->constrained('users')->onDelete('cascade');

            // Scoring
            $table->decimal('overall_score', 3, 2)->nullable();
            $table->string('appointment_quality')->nullable();

            // Grading quality tracking
            $table->integer('playback_seconds')->default(0);
            $table->timestamp('grading_started_at')->nullable();
            $table->timestamp('grading_completed_at')->nullable();

            // Status
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamps();

            $table->unique(['call_id', 'graded_by']);
            $table->index(['graded_by', 'created_at']);
        });

        Schema::create('grade_category_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->foreignId('rubric_category_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('score')->nullable();
            $table->timestamps();

            $table->unique(['grade_id', 'rubric_category_id']);
        });

        Schema::create('grade_checkpoint_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->foreignId('rubric_checkpoint_id')->constrained()->onDelete('cascade');
            $table->boolean('observed');
            $table->timestamps();

            $table->unique(['grade_id', 'rubric_checkpoint_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_checkpoint_responses');
        Schema::dropIfExists('grade_category_scores');
        Schema::dropIfExists('grades');
    }
};
