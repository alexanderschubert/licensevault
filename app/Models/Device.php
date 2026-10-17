<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Gerät (z. B. "MacBook Pro"), auf dem Lizenzen installiert sind. */
class Device extends Model
{
    protected $fillable = ['name'];

    public function licenses(): BelongsToMany
    {
        return $this->belongsToMany(License::class);
    }
}
