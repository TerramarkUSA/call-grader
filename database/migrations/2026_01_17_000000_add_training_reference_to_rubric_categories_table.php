<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_categories', function (Blueprint $table) {
            $table->text('training_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rubric_categories', function (Blueprint $table) {
            $table->dropColumn('training_reference');
        });
    }
};
