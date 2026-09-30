<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employers')) {
            DB::statement("UPDATE employers SET status = CASE
                WHEN status IN ('active', 'approved', 'working') THEN 'working'
                WHEN status = 'pending' THEN 'pending'
                ELSE 'potential'
            END");

            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE employers MODIFY COLUMN status ENUM('potential', 'pending', 'working') NOT NULL DEFAULT 'potential'");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employers')) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE employers MODIFY COLUMN status ENUM('active', 'pending', 'inactive') NOT NULL DEFAULT 'pending'");
            }
        }
    }
};
