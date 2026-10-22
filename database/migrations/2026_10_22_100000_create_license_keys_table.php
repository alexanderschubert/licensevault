<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('license_id')->constrained()->cascadeOnDelete();
            $t->string('label', 100)->nullable();
            $t->text('secret_ciphertext');
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        // Bestehende Einzelschlüssel übernehmen (Ciphertext unverändert).
        DB::table('licenses')->whereNotNull('secret_ciphertext')->orderBy('id')->each(function ($l) {
            DB::table('license_keys')->insert([
                'license_id' => $l->id, 'label' => null, 'secret_ciphertext' => $l->secret_ciphertext,
                'position' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        Schema::table('licenses', fn (Blueprint $t) => $t->dropColumn('secret_ciphertext'));
    }

    public function down(): void
    {
        Schema::table('licenses', fn (Blueprint $t) => $t->text('secret_ciphertext')->nullable());
        Schema::dropIfExists('license_keys');
    }
};
