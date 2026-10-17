<?php

namespace App\Services;

use App\Models\License;
use App\Models\Reminder;
use Illuminate\Support\Collection;

/**
 * Erzeugt Erinnerungen für Ablauf- und Verlängerungsdaten.
 * Pro Stichtag und überschrittener Vorlaufzeit entsteht genau eine Erinnerung
 * (idempotent, Unique-Index); veraltete offene Erinnerungen werden entfernt.
 */
class ReminderGenerator
{
    /** @var list<Reminder> */
    private array $created = [];

    private const KINDS = ['expires' => 'expires_at', 'renewal' => 'renewal_date'];

    /** @return int Anzahl neu angelegter Erinnerungen */
    public function run(): int
    {
        $leads = collect(config('licensevault.reminder_days'))->sort()->values();
        $overdue = (int) config('licensevault.reminder_overdue_days');
        $today = today();
        $created = 0;
        $this->created = [];

        if ($leads->isEmpty()) {
            return 0;
        }

        foreach (self::KINDS as $kind => $column) {
            License::query()
                ->whereDate($column, '>=', $today->copy()->subDays($overdue))
                ->whereDate($column, '<=', $today->copy()->addDays($leads->max()))
                ->get()
                ->each(function (License $license) use ($kind, $column, $leads, $today, &$created) {
                    $due = $license->{$column};
                    $days = (int) $today->diffInDays($due, false);
                    $lead = $days <= 0 ? 0 : $leads->first(fn ($l) => $days <= $l);

                    if ($lead === null) {
                        return;
                    }

                    $exists = Reminder::where('license_id', $license->id)->where('kind', $kind)
                        ->where('lead_days', $lead)->whereDate('due_on', $due->toDateString())->exists();
                    if (! $exists) {
                        $this->created[] = Reminder::create(['license_id' => $license->id, 'kind' => $kind, 'due_on' => $due->toDateString(), 'lead_days' => $lead]);
                        $created++;
                    }
                });
        }

        $this->removeStale();

        return $created;
    }

    /** @return Collection<int, Reminder> Beim letzten Lauf neu angelegte Erinnerungen */
    public function created(): Collection
    {
        return collect($this->created);
    }

    private function removeStale(): void
    {
        Reminder::open()->with('license')->get()->each(function (Reminder $r) {
            $current = $r->license?->{self::KINDS[$r->kind]}?->toDateString();
            if ($current !== $r->due_on->toDateString()) {
                $r->delete();
            }
        });
    }
}
