<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = ['title', 'original_name', 'path', 'mime', 'size'];

    protected $hidden = ['path'];

    protected static function booted(): void
    {
        static::deleted(fn (Document $d) => Storage::disk('local')->delete($d->path));
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1, ',', '.').' MB'
            : number_format(max(1, $this->size / 1024), 0, ',', '.').' KB';
    }
}
