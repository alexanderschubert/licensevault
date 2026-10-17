<?php

namespace App\Support;

/**
 * Matter-Kopplungsdaten: QR-Payload ("MT:" + Base38) und manueller Kopplungscode (11/21 Ziffern, Verhoeff-Prüfziffer).
 * Referenz: Matter Core Spec, Abschnitt "Onboarding Payload". Geprüft u. a. gegen den SDK-Testvektor
 * MT:-24J0AFN00KA0648G00 (Passcode 20202021, Discriminator 3840) <-> 34970112332.
 */
class Matter
{
    private const BASE38 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-.';

    private const D = [[0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6], [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8], [5, 9, 8, 7, 6, 0, 4, 3, 2, 1], [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4], [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]];

    private const P = [[0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2], [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0], [4, 2, 8, 6, 5, 7, 3, 9, 0, 1], [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]];

    private const INV = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];

    /** @return array{version:int,vendorId:int,productId:int,flow:int,capabilities:int,discriminator:int,passcode:int}|null */
    public static function parseQr(string $qr): ?array
    {
        if (! preg_match('/^MT:([0-9A-Z.\-]{19,})$/', strtoupper(trim($qr)), $m)) {
            return null;
        }

        // Die ersten 19 Zeichen tragen die 11 Basis-Bytes (5+5+5+4 Zeichen -> 3+3+3+2 Bytes); danach folgt optional TLV.
        $bytes = [];
        foreach ([5, 5, 5, 4] as $i => $len) {
            $chunk = substr($m[1], array_sum(array_slice([5, 5, 5, 4], 0, $i)), $len);
            $v = 0;
            for ($k = $len - 1; $k >= 0; $k--) {
                $v = $v * 38 + strpos(self::BASE38, $chunk[$k]);
            }
            $nBytes = $len === 5 ? 3 : 2;
            if ($v >= (1 << (8 * $nBytes))) {
                return null;
            }
            for ($b = 0; $b < $nBytes; $b++) {
                $bytes[] = ($v >> (8 * $b)) & 0xFF;
            }
        }

        $bits = fn (int $lo, int $w) => (function () use ($bytes, $lo, $w) {
            $r = 0;
            for ($i = 0; $i < $w; $i++) {
                $pos = $lo + $i;
                $r |= ((($bytes[intdiv($pos, 8)] >> ($pos % 8)) & 1) << $i);
            }

            return $r;
        })();

        return [
            'version' => $bits(0, 3), 'vendorId' => $bits(3, 16), 'productId' => $bits(19, 16), 'flow' => $bits(35, 2),
            'capabilities' => $bits(37, 8), 'discriminator' => $bits(45, 12), 'passcode' => $bits(57, 27),
        ];
    }

    public static function validQr(string $qr): bool
    {
        $p = self::parseQr($qr);

        return $p !== null && $p['version'] === 0 && $p['passcode'] > 0 && $p['passcode'] <= 99999998;
    }

    /** Manueller 11-stelliger Code (Standard-Flow); null bei Flows mit Vendor/Product-ID. */
    public static function manualCode(int $discriminator, int $passcode, int $flow = 0): ?string
    {
        if ($flow !== 0) {
            return null;
        }
        $s = sprintf('%d%05d%04d', ($discriminator >> 10) & 3, ((($discriminator >> 8) & 3) << 14) | ($passcode & 0x3FFF), $passcode >> 14);

        return $s.self::checkDigit($s);
    }

    public static function validManualCode(string $input): bool
    {
        $d = preg_replace('/\D/', '', $input);
        if (! in_array(strlen($d), [11, 21], true) || preg_match('/\D/', preg_replace('/[\s-]/', '', $input))) {
            return false;
        }

        return self::checkDigit(substr($d, 0, -1)) === substr($d, -1);
    }

    public static function formatManual(string $input): string
    {
        $d = preg_replace('/\D/', '', $input);

        return match (strlen($d)) {
            11 => substr($d, 0, 4).'-'.substr($d, 4, 3).'-'.substr($d, 7),
            21 => substr($d, 0, 4).'-'.substr($d, 4, 3).'-'.substr($d, 7, 4).'-'.substr($d, 11, 5).'-'.substr($d, 16),
            default => $input,
        };
    }

    private static function checkDigit(string $digits): string
    {
        $c = 0;
        foreach (array_reverse(str_split($digits)) as $i => $ch) {
            $c = self::D[$c][self::P[($i + 1) % 8][(int) $ch]];
        }

        return (string) self::INV[$c];
    }
}
