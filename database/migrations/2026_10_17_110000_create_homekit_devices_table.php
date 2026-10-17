<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homekit_devices', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('manufacturer')->nullable();
            $t->string('model')->nullable();
            $t->string('room')->nullable()->index();
            $t->string('status', 20)->default('active');
            $t->text('setup_code_ciphertext')->nullable();
            $t->text('qr_payload_ciphertext')->nullable();
            $t->string('qr_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homekit_devices');
    }
};
