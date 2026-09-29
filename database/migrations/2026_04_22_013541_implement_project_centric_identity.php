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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('password');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('system_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('scrape_targets', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('scraped_data', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_user');

        Schema::table('scraped_data', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('scrape_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('system_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
