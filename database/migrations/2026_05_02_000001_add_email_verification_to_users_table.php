<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_token', 64)->nullable()->unique()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('verification_token');
        });

        // Usuarios ya registrados antes del sistema de verificación → se marcan como verificados
        DB::table('users')->whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'email_verified_at']);
        });
    }
};
