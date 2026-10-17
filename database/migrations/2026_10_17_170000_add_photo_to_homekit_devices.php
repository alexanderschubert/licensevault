<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homekit_devices', function (Blueprint $t) {
            $t->string('photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('homekit_devices', fn (Blueprint $t) => $t->dropColumn('photo_path'));
    }
};
