<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resource_groups', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Convert existing resource_types name to JSON
        DB::table('resource_types')->get()->each(function ($type) {
            DB::table('resource_types')->where('id', $type->id)->update([
                'name' => json_encode(['en' => $type->name]),
            ]);
        });

        Schema::table('resource_types', function (Blueprint $table) {
            $table->foreignId('resource_group_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Also handle project_resource_types
        if (Schema::hasTable('project_resource_types')) {
            DB::table('project_resource_types')->get()->each(function ($type) {
                DB::table('project_resource_types')->where('id', $type->id)->update([
                    'name' => json_encode(['en' => $type->name]),
                ]);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE resource_types ALTER COLUMN name TYPE JSON USING name::json');
            if (Schema::hasTable('project_resource_types')) {
                DB::statement('ALTER TABLE project_resource_types ALTER COLUMN name TYPE JSON USING name::json');
            }
        } else {
            Schema::table('resource_types', function (Blueprint $table) {
                $table->json('name')->change();
            });

            if (Schema::hasTable('project_resource_types')) {
                Schema::table('project_resource_types', function (Blueprint $table) {
                    $table->json('name')->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_types', function (Blueprint $table) {
            $table->dropForeign(['resource_group_id']);
            $table->dropColumn('resource_group_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE resource_types ALTER COLUMN name TYPE VARCHAR(255) USING name::text');
            if (Schema::hasTable('project_resource_types')) {
                DB::statement('ALTER TABLE project_resource_types ALTER COLUMN name TYPE VARCHAR(255) USING name::text');
            }
        } else {
            Schema::table('resource_types', function (Blueprint $table) {
                $table->string('name')->change();
            });

            if (Schema::hasTable('project_resource_types')) {
                Schema::table('project_resource_types', function (Blueprint $table) {
                    $table->string('name')->change();
                });
            }
        }

        Schema::dropIfExists('resource_groups');
    }
};
