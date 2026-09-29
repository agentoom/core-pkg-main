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
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->text('disclosure_text')->nullable()->after('disclosed_at');
            $table->string('disclosure_version')->nullable()->after('disclosure_text');
            $table->string('disclosure_language')->nullable()->after('disclosure_version');
            $table->timestamp('accepted_disclosure_at')->nullable()->after('disclosure_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropColumn(['disclosure_text', 'disclosure_version', 'disclosure_language', 'accepted_disclosure_at']);
        });
    }
};
