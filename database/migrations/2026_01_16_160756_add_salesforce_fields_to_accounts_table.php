<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('sf_instance_url')->nullable();
            $table->string('sf_client_id')->nullable();
            $table->text('sf_client_secret')->nullable();
            $table->text('sf_refresh_token')->nullable();
            $table->text('sf_access_token')->nullable();
            $table->timestamp('sf_token_expires_at')->nullable();
            $table->timestamp('sf_connected_at')->nullable();
            $table->json('sf_field_mapping')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'sf_instance_url',
                'sf_client_id',
                'sf_client_secret',
                'sf_refresh_token',
                'sf_access_token',
                'sf_token_expires_at',
                'sf_connected_at',
                'sf_field_mapping',
            ]);
        });
    }
};
