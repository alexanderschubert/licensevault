<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->nullableMorphs('attachable');
            $t->timestamps();
        });

        Schema::create('reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('license_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10); // expires | renewal
            $t->date('due_on');
            $t->unsignedSmallInteger('lead_days');
            $t->timestamp('dismissed_at')->nullable()->index();
            $t->timestamps();
            $t->unique(['license_id', 'kind', 'due_on', 'lead_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('documents');
    }
};
