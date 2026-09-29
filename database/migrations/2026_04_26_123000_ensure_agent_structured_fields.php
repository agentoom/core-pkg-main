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
        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'role')) {
                $table->text('role')->nullable();
            }
            if (! Schema::hasColumn('agents', 'objective')) {
                $table->text('objective')->nullable();
            }
            if (! Schema::hasColumn('agents', 'operational_protocol')) {
                $table->text('operational_protocol')->nullable();
            }
            if (! Schema::hasColumn('agents', 'error_handling')) {
                $table->text('error_handling')->nullable();
            }
            if (! Schema::hasColumn('agents', 'prioritize')) {
                $table->json('prioritize')->nullable();
            }
            if (! Schema::hasColumn('agents', 'constraints')) {
                $table->json('constraints')->nullable();
            }
            if (! Schema::hasColumn('agents', 'is_cautious_constraint_enabled')) {
                $table->boolean('is_cautious_constraint_enabled')->default(true);
            }
            if (! Schema::hasColumn('agents', 'conditional_rules')) {
                $table->json('conditional_rules')->nullable();
            }
            if (! Schema::hasColumn('agents', 'output_format')) {
                $table->json('output_format')->nullable();
            }
            if (! Schema::hasColumn('agents', 'examples')) {
                $table->json('examples')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed as this is a safety/cleanup migration
    }
};
