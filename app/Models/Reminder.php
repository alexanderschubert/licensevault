<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = ['license_id', 'kind', 'due_on', 'lead_days', 'dismissed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'dismissed_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('dismissed_at');
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->due_on, false);
    }

    public function message(): string
    {
        $d = $this->daysLeft();
        $what = $this->kind === 'renewal' ? ['Verlängerung', 'fällig'] : ['Lizenz', 'läuft ab'];

        return match (true) {
            $d < 0 => $what[0].' seit '.abs($d).' '.(abs($d) === 1 ? 'Tag' : 'Tagen').' '.($this->kind === 'renewal' ? 'überfällig' : 'abgelaufen'),
            $d === 0 => $what[0].' heute '.$what[1],
            default => $what[0].' '.$what[1].' in '.$d.' '.($d === 1 ? 'Tag' : 'Tagen'),
        };
    }

    public function severity(): string
    {
        return $this->daysLeft() <= 0 ? 'pill-er' : ($this->daysLeft() <= 7 ? 'pill-wa' : 'pill-ac');
    }
}
