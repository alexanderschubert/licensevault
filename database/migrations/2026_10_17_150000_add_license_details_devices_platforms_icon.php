<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $t) {
            $t->string('customer_number', 100)->nullable();
            $t->string('support_contact', 190)->nullable();
            $t->text('recovery_codes_ciphertext')->nullable();
            $t->string('icon_path')->nullable();
            $t->json('platforms')->nullable();
        });

        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('device_license', function (Blueprint $t) {
            $t->foreignId('license_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->primary(['license_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_license');
        Schema::dropIfExists('devices');
        Schema::table('licenses', fn (Blueprint $t) => $t->dropColumn(['customer_number', 'support_contact', 'recovery_codes_ciphertext', 'icon_path', 'platforms']));
    }
};
