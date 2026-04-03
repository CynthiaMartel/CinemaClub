<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->dropColumn('individualRate');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            $table->float('individualRate', 3, 1)->default(0)->after('vote_average');
        });
    }
};
