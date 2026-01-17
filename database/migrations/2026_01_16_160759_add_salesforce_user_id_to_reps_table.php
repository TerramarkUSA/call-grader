<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reps', function (Blueprint $table) {
            $table->string('sf_user_id', 18)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reps', function (Blueprint $table) {
            $table->dropColumn('sf_user_id');
        });
    }
};
