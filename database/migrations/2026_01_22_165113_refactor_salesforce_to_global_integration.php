<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add office mapping field to accounts
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('sf_office_name')->nullable()->after('sf_field_mapping');
        });

        // Note: SF credentials will be stored in settings table as:
        // - sf_instance_url
        // - sf_client_id
        // - sf_client_secret (encrypted)
        // - sf_access_token (encrypted)
        // - sf_refresh_token (encrypted)
        // - sf_token_expires_at
        // - sf_connected_at
        // - sf_field_mapping (JSON)
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('sf_office_name');
        });
    }
};
