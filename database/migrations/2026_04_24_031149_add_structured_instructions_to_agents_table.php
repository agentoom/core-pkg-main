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
            $table->text('role')->nullable()->after('description');
            $table->text('objective')->nullable()->after('role');
            $table->json('prioritize')->nullable()->after('instructions');
            $table->json('constraints')->nullable()->after('prioritize');
            $table->json('conditional_rules')->nullable()->after('constraints');
            $table->json('output_format')->nullable()->after('conditional_rules');
            $table->json('examples')->nullable()->after('output_format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'objective',
                'prioritize',
                'constraints',
                'conditional_rules',
                'output_format',
                'examples',
            ]);
        });
    }
};
