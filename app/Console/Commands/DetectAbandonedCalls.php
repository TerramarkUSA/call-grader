<?php

namespace App\Console\Commands;

use App\Models\CallInteraction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectAbandonedCalls extends Command
{
    protected $signature = 'call:detect-abandoned';
    protected $description = 'Flag call interactions as abandoned when opened/transcribed but never graded or skipped within 24 hours';

    public function handle(): void
    {
        $threshold = now()->subHours(24);

        // Find opened/transcribed interactions older than 24h
        // where the same user never graded, skipped, or was already flagged as abandoned
        $abandoned = DB::table('call_interactions as ci')
            ->where('ci.action', 'opened')
            ->where('ci.created_at', '<', $threshold)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('call_interactions as ci2')
                    ->whereColumn('ci2.call_id', 'ci.call_id')
                    ->whereColumn('ci2.user_id', 'ci.user_id')
                    ->whereIn('ci2.action', ['graded', 'skipped', 'abandoned']);
            })
            ->select('ci.call_id', 'ci.user_id', 'ci.page_seconds')
            ->get();

        $count = 0;

        foreach ($abandoned as $row) {
            CallInteraction::create([
                'call_id' => $row->call_id,
                'user_id' => $row->user_id,
                'action' => 'abandoned',
                'page_seconds' => $row->page_seconds,
                'created_at' => now(),
            ]);
            $count++;
        }

        if ($count > 0) {
            Log::info("Detected {$count} abandoned call interactions.");
            $this->info("Flagged {$count} abandoned interactions.");
        } else {
            $this->info('No abandoned interactions found.');
        }
    }
}
