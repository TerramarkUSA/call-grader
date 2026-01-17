<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('sf_chance_id', 18)->nullable()->index();
            $table->string('sf_lead_id', 18)->nullable();
            $table->string('sf_owner_id', 18)->nullable();
            $table->string('sf_project')->nullable();
            $table->string('sf_land_sale')->nullable();
            $table->string('sf_contact_status')->nullable();
            $table->boolean('sf_appointment_made')->nullable();
            $table->boolean('sf_toured_property')->nullable();
            $table->boolean('sf_opportunity_created')->nullable();
            $table->timestamp('sf_synced_at')->nullable();
            $table->timestamp('sf_outcome_synced_at')->nullable();
            $table->unsignedTinyInteger('sf_sync_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'sf_chance_id',
                'sf_lead_id',
                'sf_owner_id',
                'sf_project',
                'sf_land_sale',
                'sf_contact_status',
                'sf_appointment_made',
                'sf_toured_property',
                'sf_opportunity_created',
                'sf_synced_at',
                'sf_outcome_synced_at',
                'sf_sync_attempts',
            ]);
        });
    }
};
