<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use App\Models\ObjectionType;
use App\Models\GradeCategoryScore;
use App\Models\GradeCheckpointResponse;
use App\Models\CoachingNote;
use App\Models\CallObjectionTag;
use Illuminate\Support\Facades\DB;

class CleanupDuplicates extends Command
{
    protected $signature = 'cleanup:duplicates {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Remove duplicate rubric categories, checkpoints, and objection types';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        DB::beginTransaction();

        try {
            $this->cleanupRubricCategories($dryRun);
            $this->cleanupRubricCheckpoints($dryRun);
            $this->cleanupObjectionTypes($dryRun);

            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry run complete. Run without --dry-run to apply changes.');
            } else {
                DB::commit();
                $this->info('Cleanup complete!');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function cleanupRubricCategories(bool $dryRun): void
    {
        $this->info('Cleaning up Rubric Categories...');
        
        // Group by external_id, keep the one with lowest ID
        $duplicates = RubricCategory::select('external_id')
            ->groupBy('external_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('external_id');

        $totalDeleted = 0;

        foreach ($duplicates as $externalId) {
            $categories = RubricCategory::where('external_id', $externalId)
                ->orderBy('id')
                ->get();
            
            $keepId = $categories->first()->id;
            $deleteIds = $categories->skip(1)->pluck('id');

            // Reassign related records to the kept category
            GradeCategoryScore::whereIn('rubric_category_id', $deleteIds)
                ->update(['rubric_category_id' => $keepId]);
            
            CoachingNote::whereIn('rubric_category_id', $deleteIds)
                ->update(['rubric_category_id' => $keepId]);

            if (!$dryRun) {
                RubricCategory::whereIn('id', $deleteIds)->delete();
            }

            $totalDeleted += $deleteIds->count();
            $this->line("  - '{$externalId}': keeping ID {$keepId}, deleting " . $deleteIds->count() . " duplicates");
        }

        $this->info("  Total categories to delete: {$totalDeleted}");
    }

    private function cleanupRubricCheckpoints(bool $dryRun): void
    {
        $this->info('Cleaning up Rubric Checkpoints...');
        
        // Group by external_id, keep the one with lowest ID
        $duplicates = RubricCheckpoint::select('external_id')
            ->groupBy('external_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('external_id');

        $totalDeleted = 0;

        foreach ($duplicates as $externalId) {
            $checkpoints = RubricCheckpoint::where('external_id', $externalId)
                ->orderBy('id')
                ->get();
            
            $keepId = $checkpoints->first()->id;
            $deleteIds = $checkpoints->skip(1)->pluck('id');

            // Reassign related records to the kept checkpoint
            GradeCheckpointResponse::whereIn('rubric_checkpoint_id', $deleteIds)
                ->update(['rubric_checkpoint_id' => $keepId]);

            if (!$dryRun) {
                RubricCheckpoint::whereIn('id', $deleteIds)->delete();
            }

            $totalDeleted += $deleteIds->count();
            $this->line("  - '{$externalId}': keeping ID {$keepId}, deleting " . $deleteIds->count() . " duplicates");
        }

        $this->info("  Total checkpoints to delete: {$totalDeleted}");
    }

    private function cleanupObjectionTypes(bool $dryRun): void
    {
        $this->info('Cleaning up Objection Types...');
        
        // Group by name, keep the one with lowest ID
        $duplicates = ObjectionType::select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        $totalDeleted = 0;

        foreach ($duplicates as $name) {
            $types = ObjectionType::where('name', $name)
                ->orderBy('id')
                ->get();
            
            $keepId = $types->first()->id;
            $deleteIds = $types->skip(1)->pluck('id');

            // Reassign related records to the kept type
            CoachingNote::whereIn('objection_type_id', $deleteIds)
                ->update(['objection_type_id' => $keepId]);
            
            CallObjectionTag::whereIn('objection_type_id', $deleteIds)
                ->update(['objection_type_id' => $keepId]);

            if (!$dryRun) {
                ObjectionType::whereIn('id', $deleteIds)->delete();
            }

            $totalDeleted += $deleteIds->count();
            $this->line("  - '{$name}': keeping ID {$keepId}, deleting " . $deleteIds->count() . " duplicates");
        }

        $this->info("  Total objection types to delete: {$totalDeleted}");
    }
}
