<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jobs', 'contact_phone')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->string('contact_phone')->nullable()->after('salary_max');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jobs', 'contact_phone')) {
            Schema::table('jobs', function (Blueprint $table) {
                $table->dropColumn('contact_phone');
            });
        }
    }
};
