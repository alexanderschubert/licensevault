<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $t) {
            $t->string('account_login', 190)->nullable()->after('secret_ciphertext');
            $t->text('account_secret_ciphertext')->nullable()->after('account_login');
            $t->string('account_url', 255)->nullable()->after('account_secret_ciphertext');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $t) => $t->dropColumn(['account_login', 'account_secret_ciphertext', 'account_url']));
    }
};
