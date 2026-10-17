<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    public static function bootHasDocuments(): void
    {
        // Anhänge samt Dateien mit dem Eintrag entfernen
        static::deleting(fn ($model) => $model->documents()->get()->each->delete());
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'attachable')->latest();
    }
}
