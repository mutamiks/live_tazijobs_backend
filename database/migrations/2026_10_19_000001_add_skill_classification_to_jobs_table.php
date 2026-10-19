<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('skill_classification')->default('unskilled')->index()->after('job_category_id');
        });

        DB::table('job_categories')
            ->select(['id', 'skill_level'])
            ->get()
            ->each(function (object $category): void {
                DB::table('jobs')
                    ->where('job_category_id', $category->id)
                    ->update(['skill_classification' => $category->skill_level]);
            });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('skill_classification');
        });
    }
};
