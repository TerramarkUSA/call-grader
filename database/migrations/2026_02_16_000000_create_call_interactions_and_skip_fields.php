<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create call_interactions table
        Schema::create('call_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // opened, transcribed, skipped, graded, abandoned
            $table->integer('page_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['call_id', 'user_id']);
            $table->index(['user_id', 'action', 'created_at']);
        });

        // 2. Add skip fields + legacy backup to calls table
        Schema::table('calls', function (Blueprint $table) {
            $table->foreignId('transcribed_by')->nullable()->after('transcript')->constrained('users')->nullOnDelete();
            $table->timestamp('skipped_at')->nullable()->after('processed_at');
            $table->foreignId('skipped_by')->nullable()->after('skipped_at')->constrained('users')->nullOnDelete();
            $table->string('skip_reason')->nullable()->after('skipped_by');
            $table->string('legacy_call_quality')->nullable()->after('call_quality');
        });

        // 3. Add user_id to transcription_logs
        Schema::table('transcription_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('call_id')->constrained()->nullOnDelete();
        });

        // 4. Migrate existing bad-call data to new skip taxonomy
        $reasonMap = [
            'no_conversation' => 'not_a_real_call',
            'voicemail'       => 'not_a_real_call',
            'wrong_number'    => 'not_a_real_call',
            'spam'            => 'not_a_real_call',
            'test'            => 'wrong_call_type',
            'other'           => 'other',
        ];

        $oldQualities = array_keys($reasonMap);

        // Backup original call_quality
        DB::table('calls')
            ->whereIn('call_quality', $oldQualities)
            ->update(['legacy_call_quality' => DB::raw('call_quality')]);

        // Remap each old value
        foreach ($reasonMap as $oldQuality => $newReason) {
            DB::table('calls')
                ->where('call_quality', $oldQuality)
                ->update([
                    'call_quality' => 'skipped',
                    'skip_reason' => $newReason,
                    'skipped_at' => DB::raw('marked_bad_at'),
                    'skipped_by' => DB::raw('marked_bad_by'),
                ]);
        }

        // Create synthetic call_interactions rows for historical skips
        $skippedCalls = DB::table('calls')
            ->whereNotNull('legacy_call_quality')
            ->whereIn('legacy_call_quality', $oldQualities)
            ->select('id', 'skipped_by', 'skip_reason', 'skipped_at')
            ->get();

        foreach ($skippedCalls as $call) {
            if ($call->skipped_by && $call->skipped_at) {
                DB::table('call_interactions')->insert([
                    'call_id' => $call->id,
                    'user_id' => $call->skipped_by,
                    'action' => 'skipped',
                    'metadata' => json_encode(['reason' => $call->skip_reason]),
                    'created_at' => $call->skipped_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Restore original call_quality values from legacy backup
        $oldQualities = ['no_conversation', 'voicemail', 'wrong_number', 'spam', 'test', 'other'];

        DB::table('calls')
            ->whereNotNull('legacy_call_quality')
            ->whereIn('legacy_call_quality', $oldQualities)
            ->update([
                'call_quality' => DB::raw('legacy_call_quality'),
                'skip_reason' => null,
                'skipped_at' => null,
                'skipped_by' => null,
            ]);

        // Remove synthetic interactions
        DB::table('call_interactions')->where('action', 'skipped')
            ->whereIn('call_id', function ($q) {
                $q->select('id')->from('calls')->whereNotNull('legacy_call_quality');
            })->delete();

        Schema::table('transcription_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['transcribed_by']);
            $table->dropForeign(['skipped_by']);
            $table->dropColumn(['transcribed_by', 'skipped_at', 'skipped_by', 'skip_reason', 'legacy_call_quality']);
        });

        Schema::dropIfExists('call_interactions');
    }
};
