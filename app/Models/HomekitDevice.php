<?php

namespace App\Models;

use App\Enums\DeviceProtocol;
use App\Enums\HomekitStatus;
use App\Models\Concerns\HasDocuments;
use App\Support\SecretVault;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomekitDevice extends Model
{
    use HasDocuments, HasFactory;

    protected $fillable = ['name', 'manufacturer', 'model', 'room', 'status', 'protocol', 'setup_code', 'qr_payload', 'notes'];

    /** Ciphertexte und Bildpfad verlassen das Modell nie (JSON, Logs). */
    protected $hidden = ['setup_code_ciphertext', 'qr_payload_ciphertext', 'qr_image_path', 'photo_path'];

    protected static function booted(): void
    {
        static::deleted(function (HomekitDevice $d) {
            if ($d->photo_path) {
                Storage::disk('local')->delete($d->photo_path);
            }
        });
    }

    public function hasPhoto(): bool
    {
        return ! empty($this->attributes['photo_path']);
    }

    protected function casts(): array
    {
        return ['status' => HomekitStatus::class, 'protocol' => DeviceProtocol::class];
    }

    protected function setupCode(): Attribute
    {
        return Attribute::make(
            get: fn ($v, array $a) => ($a['setup_code_ciphertext'] ?? null) ? SecretVault::decrypt($a['setup_code_ciphertext']) : null,
            set: fn ($v) => ['setup_code_ciphertext' => ($v === null || $v === '') ? null : SecretVault::encrypt($v)],
        );
    }

    protected function qrPayload(): Attribute
    {
        return Attribute::make(
            get: fn ($v, array $a) => ($a['qr_payload_ciphertext'] ?? null) ? SecretVault::decrypt($a['qr_payload_ciphertext']) : null,
            set: fn ($v) => ['qr_payload_ciphertext' => ($v === null || $v === '') ? null : SecretVault::encrypt($v)],
        );
    }

    public function hasSetupCode(): bool
    {
        return ! empty($this->attributes['setup_code_ciphertext']);
    }

    public function hasQrPayload(): bool
    {
        return ! empty($this->attributes['qr_payload_ciphertext']);
    }

    public function hasQrImage(): bool
    {
        return ! empty($this->attributes['qr_image_path']);
    }

    public function hasQr(): bool
    {
        return $this->hasQrPayload() || $this->hasQrImage();
    }

    public function imagePath(): ?string
    {
        return $this->attributes['qr_image_path'] ?? null;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! filled($term)) {
            return $q;
        }
        $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $q->where(fn ($w) => $w
            ->where('name', $op, $like)->orWhere('manufacturer', $op, $like)
            ->orWhere('model', $op, $like)->orWhere('room', $op, $like)->orWhere('notes', $op, $like));
    }
}
