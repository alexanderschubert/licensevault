<?php

namespace App\Models;

use App\Support\SecretVault;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Lizenzschlüssel/eine Lizenznummer einer Lizenz (z. B. je Modul oder Funktionsumfang). */
class LicenseKey extends Model
{
    protected $fillable = ['label', 'secret', 'position'];

    /** Der Ciphertext verlässt das Modell nie. */
    protected $hidden = ['secret_ciphertext'];

    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn ($v, array $a) => ($a['secret_ciphertext'] ?? null) ? SecretVault::decrypt($a['secret_ciphertext']) : null,
            set: fn ($v) => ['secret_ciphertext' => SecretVault::encrypt((string) $v)],
        );
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
