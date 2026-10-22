<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\LicenseType;
use App\Enums\Platform;
use App\Models\Concerns\HasDocuments;
use App\Support\SecretVault;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class License extends Model
{
    use HasDocuments, HasFactory;

    public const SOON_DAYS = 60;

    protected $fillable = [
        'name', 'vendor', 'product_version', 'license_type', 'account_login', 'account_password', 'account_url',
        'purchase_date', 'purchase_price', 'currency', 'billing_interval', 'expires_at', 'renewal_date',
        'device_limit', 'category_id', 'notes', 'customer_number', 'support_contact', 'recovery_codes', 'platforms',
    ];

    /** Der Ciphertext verlässt das Modell nie (Serialisierung, JSON, Logs). */
    protected $hidden = ['account_secret_ciphertext', 'recovery_codes_ciphertext', 'icon_path'];

    protected function casts(): array
    {
        return [
            'license_type' => LicenseType::class,
            'billing_interval' => BillingInterval::class,
            'purchase_date' => 'date',
            'expires_at' => 'date',
            'renewal_date' => 'date',
            'purchase_price' => 'decimal:2',
            'platforms' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (License $l) {
            if ($l->icon_path) {
                Storage::disk('local')->delete($l->icon_path);
            }
        });
    }

    /** @return list<Platform> */
    public function platformEnums(): array
    {
        return collect($this->platforms ?? [])->map(fn ($p) => Platform::tryFrom($p))->filter()->values()->all();
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)->orderBy('name');
    }

    public function hasIcon(): bool
    {
        return ! empty($this->attributes['icon_path']);
    }

    /** Farbiger Platzhalter, wenn kein Icon hochgeladen ist. */
    public function avatarColor(): string
    {
        $palette = ['#5b5bd6', '#e0562f', '#1f9d6b', '#2b6fe0', '#c43a7a', '#d9a21b', '#2a9fbf', '#7a4de0'];

        return $palette[crc32(mb_strtolower($this->name)) % count($palette)];
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $this->name) ?: '?', 0, 2));
    }

    /** Lizenzschlüssel/-nummern (verschlüsselt); Klartext nur über die Reveal-Route. */
    public function keys(): HasMany
    {
        return $this->hasMany(LicenseKey::class)->orderBy('position')->orderBy('id');
    }

    /** Jährliche Kosten (monatlich × 12); einmalige Zahlungen zählen nicht. */
    public function yearlyCost(): float
    {
        return (float) $this->purchase_price * ($this->billing_interval?->perYear() ?? 0);
    }

    /** z. B. "9,99 EUR / Monat" oder "—" ohne Preis. */
    public function priceLabel(): string
    {
        if ($this->purchase_price === null) {
            return '—';
        }

        return number_format((float) $this->purchase_price, 2, ',', '.').' '.$this->currency.($this->billing_interval?->suffix() ?? '');
    }

    /** Passwort eines Konto-Zugangs (statt oder zusätzlich zu einem Lizenzschlüssel). */
    protected function accountPassword(): Attribute
    {
        return Attribute::make(
            get: fn ($v, array $a) => ($a['account_secret_ciphertext'] ?? null) ? SecretVault::decrypt($a['account_secret_ciphertext']) : null,
            set: fn ($v) => ['account_secret_ciphertext' => ($v === null || $v === '') ? null : SecretVault::encrypt($v)],
        );
    }

    /** 2FA-/Wiederherstellungscodes (mehrzeilig), verschlüsselt gespeichert. */
    protected function recoveryCodes(): Attribute
    {
        return Attribute::make(
            get: fn ($v, array $a) => ($a['recovery_codes_ciphertext'] ?? null) ? SecretVault::decrypt($a['recovery_codes_ciphertext']) : null,
            set: fn ($v) => ['recovery_codes_ciphertext' => ($v === null || $v === '') ? null : SecretVault::encrypt($v)],
        );
    }

    public function hasRecoveryCodes(): bool
    {
        return ! empty($this->attributes['recovery_codes_ciphertext']);
    }

    public function hasAccountPassword(): bool
    {
        return ! empty($this->attributes['account_secret_ciphertext']);
    }

    public function hasAccount(): bool
    {
        return filled($this->account_login) || $this->hasAccountPassword();
    }

    public function hasSecret(): bool
    {
        return $this->keys->isNotEmpty();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /** @return array{key: string, label: string, class: string} */
    public function expiryStatus(): array
    {
        if (! $this->expires_at) {
            return ['key' => 'none', 'label' => 'unbegrenzt', 'class' => ''];
        }
        $days = (int) today()->diffInDays($this->expires_at, false);

        return match (true) {
            $days < 0 => ['key' => 'expired', 'label' => 'abgelaufen', 'class' => 'pill-er'],
            $days <= self::SOON_DAYS => ['key' => 'soon', 'label' => $days === 0 ? 'heute' : "in {$days} Tagen", 'class' => 'pill-wa'],
            default => ['key' => 'ok', 'label' => $this->expires_at->format('d.m.Y'), 'class' => 'pill-ok'],
        };
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>=', today()));
    }

    /** Läuft in den nächsten SOON_DAYS Tagen ab ODER steht zur Verlängerung an. */
    public function scopeExpiringSoon(Builder $q): Builder
    {
        $range = [today(), today()->addDays(self::SOON_DAYS)];

        return $q->where(fn ($w) => $w->whereBetween('expires_at', $range)->orWhereBetween('renewal_date', $range));
    }

    /**
     * Fälligkeiten (Ablauf/Verlängerung) von "kürzlich überfällig" bis SOON_DAYS in der Zukunft –
     * dasselbe Fenster wie die Erinnerungen. Pro Lizenz zählt das nächstliegende Ereignis.
     *
     * @return Collection<int, array{license: License, kind: string, date: Carbon, days: int}>
     */
    public static function dueItems(): Collection
    {
        $from = today()->subDays((int) config('licensevault.reminder_overdue_days'));
        $to = today()->addDays(self::SOON_DAYS);

        return static::query()
            ->where(fn ($q) => $q->whereBetween('expires_at', [$from, $to])->orWhereBetween('renewal_date', [$from, $to]))
            ->get()
            ->flatMap(function (License $l) use ($from, $to) {
                $items = [];
                foreach (['expires' => 'expires_at', 'renewal' => 'renewal_date'] as $kind => $column) {
                    $date = $l->{$column};
                    if ($date && $date->between($from, $to)) {
                        $items[] = ['license' => $l, 'kind' => $kind, 'date' => $date, 'days' => (int) today()->diffInDays($date, false)];
                    }
                }

                return $items;
            })
            ->sortBy('days')
            ->unique(fn ($i) => $i['license']->id)
            ->values();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! filled($term)) {
            return $q;
        }
        $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $q->where(fn ($w) => $w
            ->where('name', $op, $like)->orWhere('vendor', $op, $like)->orWhere('account_login', $op, $like)->orWhere('customer_number', $op, $like)
            ->orWhere('notes', $op, $like)
            ->orWhereHas('tags', fn ($t) => $t->where('name', $op, $like))
            ->orWhereHas('devices', fn ($t) => $t->where('name', $op, $like)));
    }
}
