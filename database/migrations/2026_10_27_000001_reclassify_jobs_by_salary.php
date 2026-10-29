<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jobs')->update([
            'skill_classification' => DB::raw(
                "CASE WHEN COALESCE(salary_max, salary_min) > 350000 THEN 'skilled' ELSE 'unskilled' END"
            ),
        ]);
    }

    public function down(): void
    {
        DB::table('jobs')->update(['skill_classification' => 'unskilled']);

        DB::table('job_categories')
            ->select(['id', 'skill_level'])
            ->orderBy('id')
            ->get()
            ->each(function (object $category): void {
                DB::table('jobs')
                    ->where('job_category_id', $category->id)
                    ->update(['skill_classification' => $category->skill_level]);
            });
    }
};
