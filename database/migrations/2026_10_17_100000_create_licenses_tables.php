<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('licenses', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('vendor')->nullable();
            $t->string('product_version')->nullable();
            $t->string('license_type', 20)->default('other');
            $t->text('secret_ciphertext')->nullable();
            $t->date('purchase_date')->nullable();
            $t->decimal('purchase_price', 10, 2)->nullable();
            $t->string('currency', 3)->default('EUR');
            $t->date('expires_at')->nullable()->index();
            $t->date('renewal_date')->nullable();
            $t->unsignedSmallInteger('device_limit')->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('license_type');
        });

        Schema::create('license_tag', function (Blueprint $t) {
            $t->foreignId('license_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['license_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_tag');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
