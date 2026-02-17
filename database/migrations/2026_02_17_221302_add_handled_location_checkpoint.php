<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bump all existing positive checkpoint sort_orders by 1 to make room at position 1
        DB::table('rubric_checkpoints')
            ->where('type', 'positive')
            ->increment('sort_order');

        // Insert the new checkpoint at sort_order 1
        DB::table('rubric_checkpoints')->insert([
            'name' => 'Handled location properly',
            'external_id' => 'handled_location_properly',
            'type' => 'positive',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rubric_checkpoints')
            ->where('external_id', 'handled_location_properly')
            ->delete();

        // Restore original sort_orders
        DB::table('rubric_checkpoints')
            ->where('type', 'positive')
            ->decrement('sort_order');
    }
};
