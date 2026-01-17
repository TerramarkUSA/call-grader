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
        Schema::table('reps', function (Blueprint $table) {
            $table->string('ctm_name')->nullable()->after('name'); // For matching CTM data
            $table->string('email')->nullable()->after('ctm_name');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('ctm_name')->nullable()->after('name'); // For matching CTM data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reps', function (Blueprint $table) {
            $table->dropColumn(['ctm_name', 'email']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ctm_name');
        });
    }
};
