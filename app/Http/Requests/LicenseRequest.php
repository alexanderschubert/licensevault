<?php

namespace App\Http\Requests;

use App\Enums\BillingInterval;
use App\Enums\LicenseType;
use App\Enums\Platform;
use App\Rules\MaxImagePixels;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'product_version' => ['nullable', 'string', 'max:50'],
            'license_type' => ['required', Rule::enum(LicenseType::class)],
            'secret' => ['nullable', 'string', 'max:2000'],
            'clear_secret' => ['nullable', 'boolean'],
            'account_login' => ['nullable', 'string', 'max:190'],
            'account_password' => ['nullable', 'string', 'max:500'],
            'clear_account_password' => ['nullable', 'boolean'],
            'account_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
            'expires_at' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'device_limit' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'category' => ['nullable', 'string', 'max:60'],
            'tags' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_number' => ['nullable', 'string', 'max:100'],
            'support_contact' => ['nullable', 'string', 'max:190'],
            'recovery_codes' => ['nullable', 'string', 'max:5000'],
            'clear_recovery_codes' => ['nullable', 'boolean'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => [Rule::enum(Platform::class)],
            'devices' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,heic,heif', 'max:512', new MaxImagePixels(2048, 2048, 'Das Icon darf höchstens 2048 × 2048 Pixel groß sein.')],
            'clear_icon' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Dieses Feld ist erforderlich.',
            'max' => 'Der Wert ist zu lang bzw. zu groß.',
            'date' => 'Bitte ein gültiges Datum angeben.',
            'numeric' => 'Bitte eine Zahl angeben.',
            'integer' => 'Bitte eine ganze Zahl angeben.',
            'min' => 'Der Wert ist zu klein.',
            'enum' => 'Ungültige Auswahl.',
            'icon.max' => 'Das Icon ist zu groß (max. 512 KB).',
            'icon.mimes' => 'Erlaubt sind PNG, JPG, WebP und HEIC.',
            'size' => 'Bitte einen 3-stelligen Währungscode angeben (z. B. EUR).',
        ];
    }

    /** @return list<string> */
    public function tagNames(): array
    {
        return collect(explode(',', (string) $this->input('tags')))
            ->map(fn ($t) => mb_substr(trim($t), 0, 30))
            ->filter()->unique(fn ($t) => mb_strtolower($t))->take(10)->values()->all();
    }

    /** @return list<string> */
    public function deviceNames(): array
    {
        return collect(explode(',', (string) $this->input('devices')))
            ->map(fn ($t) => mb_substr(trim($t), 0, 60))
            ->filter()->unique(fn ($t) => mb_strtolower($t))->take(20)->values()->all();
    }
}
