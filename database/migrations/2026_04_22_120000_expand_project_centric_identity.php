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
        Schema::table('pipelines', function (Blueprint $table) {
            if (! Schema::hasColumn('pipelines', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('endpoints', function (Blueprint $table) {
            if (! Schema::hasColumn('endpoints', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            }
        });

        Schema::table('pipeline_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('pipeline_runs', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
