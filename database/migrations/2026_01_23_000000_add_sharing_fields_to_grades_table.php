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
        Schema::table('grades', function (Blueprint $table) {
            $table->timestamp('shared_with_rep_at')->nullable()->after('status');
            $table->string('shared_with_rep_email')->nullable()->after('shared_with_rep_at');
            $table->foreignId('shared_by_user_id')->nullable()->after('shared_with_rep_email')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropForeign(['shared_by_user_id']);
            $table->dropColumn(['shared_with_rep_at', 'shared_with_rep_email', 'shared_by_user_id']);
        });
    }
};
