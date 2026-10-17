<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Prüft den Inhalt eines Bild-Uploads unabhängig von Dateiendung und Client-Angaben:
 * - PNG/JPEG/WebP müssen für PHP lesbare Bilder sein und das Pixel-Limit einhalten,
 * - HEIC/HEIF (von PHP nicht lesbar) müssen eine ISO-BMFF-Kennung ("ftyp") tragen; hier begrenzt die Dateigröße.
 */
class MaxImagePixels implements ValidationRule
{
    private const HEIF_BRANDS = ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'];

    public function __construct(private int $width, private int $height, private string $message) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }
        $path = $value->getRealPath();

        if (in_array(strtolower($value->getClientOriginalExtension()), ['heic', 'heif'], true)) {
            $head = (string) @file_get_contents($path, false, null, 0, 12);
            if (substr($head, 4, 4) !== 'ftyp' || ! in_array(substr($head, 8, 4), self::HEIF_BRANDS, true)) {
                $fail('Die Datei ist kein gültiges HEIC-Bild.');
            }

            return;
        }

        $size = @getimagesize($path);
        if (! is_array($size)) {
            $fail('Die Datei ist kein gültiges Bild.');
        } elseif ($size[0] > $this->width || $size[1] > $this->height) {
            $fail($this->message);
        }
    }
}
