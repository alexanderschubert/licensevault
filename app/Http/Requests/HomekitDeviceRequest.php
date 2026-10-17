<?php

namespace App\Http\Requests;

use App\Enums\DeviceProtocol;
use App\Enums\HomekitStatus;
use App\Rules\MaxImagePixels;
use App\Support\Matter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomekitDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'protocol' => $this->input('protocol', 'homekit'),
            'qr_payload' => filled($this->qr_payload) ? strtoupper(trim($this->qr_payload)) : null,
            'setup_code' => filled($this->setup_code) ? trim($this->setup_code) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::enum(HomekitStatus::class)],
            'protocol' => ['required', Rule::enum(DeviceProtocol::class)],
            'setup_code' => ['nullable', 'string', 'max:40', function ($attr, $value, $fail) {
                if ($this->input('protocol') === 'both') {
                    (Matter::validManualCode($value) || preg_match('/^\d{3}-?\d{2}-?\d{3}$/', $value)) || $fail('Code: HomeKit 123-45-678 (8 Ziffern) oder Matter 3497-011-2332 (11 Ziffern).');
                } elseif ($this->input('protocol') === 'matter') {
                    Matter::validManualCode($value) || $fail('Matter: 11- oder 21-stelliger Kopplungscode (z. B. 3497-011-2332) – Prüfziffer ungültig oder Format falsch.');
                } elseif (! preg_match('/^\d{3}-?\d{2}-?\d{3}$/', $value)) {
                    $fail('Format: 123-45-678 (8 Ziffern).');
                }
            }],
            'qr_payload' => ['nullable', 'string', 'max:300', function ($attr, $value, $fail) {
                if ($this->input('protocol') === 'both') {
                    (Matter::validQr($value) || preg_match('/^X-HM:\/\/[0-9A-Z]{13}$/', $value)) || $fail('QR-Payload: X-HM://… (HomeKit) oder MT:… (Matter).');
                } elseif ($this->input('protocol') === 'matter') {
                    Matter::validQr($value) || $fail('Matter-QR-Payload: MT: gefolgt vom Base38-Code (mind. 19 Zeichen, gültige Daten).');
                } elseif (! preg_match('/^X-HM:\/\/[0-9A-Z]{13}$/', $value)) {
                    $fail('Format: X-HM:// gefolgt von 13 Zeichen (A–Z, 0–9).');
                }
            }],
            'photo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,heic,heif', 'max:4096', new MaxImagePixels(8000, 8000, 'Das Foto ist zu groß (max. 8000 × 8000 Pixel).')],
            'clear_photo' => ['nullable', 'boolean'],
            'qr_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,heic,heif', 'max:2048', new MaxImagePixels(8000, 8000, 'Das QR-Bild ist zu groß (max. 8000 × 8000 Pixel).')],
            'clear_setup_code' => ['nullable', 'boolean'],
            'clear_qr' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Dieses Feld ist erforderlich.',
            'max' => 'Der Wert ist zu lang bzw. die Datei zu groß (max. 2 MB).',
            'mimes' => 'Erlaubt sind PNG, JPG, WebP und HEIC.',
            'photo.max' => 'Das Foto ist zu groß (max. 4 MB).',
            'enum' => 'Ungültige Auswahl.',
        ];
    }
}
