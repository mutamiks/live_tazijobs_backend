<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::create('sms_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 30)->index();
            $table->string('purpose', 40)->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['phone', 'purpose', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_verification_codes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone_verified_at'));
    }
};
