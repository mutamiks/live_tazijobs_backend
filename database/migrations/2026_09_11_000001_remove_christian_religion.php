<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('religions')->whereRaw('LOWER(name) = ?', ['christian'])->delete();
    }

    public function down(): void
    {
        DB::table('religions')->updateOrInsert(
            ['name' => 'Christian'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }
};
