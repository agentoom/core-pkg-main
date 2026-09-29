<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support dropping foreign keys by name
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropForeign('pipeline_steps_agent_id_foreign');
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // SQLite does not support dropping foreign keys by name
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('pipeline_steps', function (Blueprint $table) {
            $table->dropForeign('pipeline_steps_agent_id_foreign');
            $table->foreign('agent_id')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade');
        });
    }
};
