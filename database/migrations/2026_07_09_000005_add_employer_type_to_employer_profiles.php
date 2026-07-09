<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->string('employer_type')->default('company')->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employer_profiles', fn (Blueprint $table) => $table->dropColumn('employer_type'));
    }
};
