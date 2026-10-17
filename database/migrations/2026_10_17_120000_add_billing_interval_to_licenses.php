<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $t) {
            $t->string('billing_interval', 10)->default('once')->after('currency');
        });

        // Bisherige Abos wurden als Jahrespreis geführt.
        DB::table('licenses')->where('license_type', 'subscription')->update(['billing_interval' => 'yearly']);
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $t) => $t->dropColumn('billing_interval'));
    }
};
