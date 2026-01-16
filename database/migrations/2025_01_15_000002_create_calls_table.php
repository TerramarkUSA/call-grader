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
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('ctm_activity_id')->unique();

            // From CTM
            $table->string('caller_name')->nullable();
            $table->string('caller_number');
            $table->integer('talk_time')->default(0);
            $table->string('dial_status');
            $table->string('source')->nullable();
            $table->timestamp('called_at');

            // From Salesforce (nullable until connected)
            $table->string('salesforce_task_id')->nullable();
            $table->foreignId('rep_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Call quality (for bad calls)
            $table->string('call_quality')->default('pending');
            $table->string('call_quality_note')->nullable();
            $table->timestamp('marked_bad_at')->nullable();
            $table->foreignId('marked_bad_by')->nullable()->constrained('users')->nullOnDelete();

            // Processing status
            $table->timestamp('ignored_at')->nullable();
            $table->string('ignore_reason')->nullable();
            $table->timestamp('processed_at')->nullable();

            // Recording & transcript
            $table->string('recording_path')->nullable();
            $table->text('transcript')->nullable();
            $table->tinyInteger('transcription_quality')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'called_at']);
            $table->index(['account_id', 'ignored_at', 'processed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
