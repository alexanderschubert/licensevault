<?php

namespace App\Backup;

use RuntimeException;

/**
 * Authentifizierte Dateiverschlüsselung (libsodium secretstream, XChaCha20-Poly1305)
 * mit Schlüsselableitung aus einer Passphrase (Argon2id). Streaming in 1-MB-Blöcken.
 *
 * Format: "LVBK1" | salt(16) | stream-header(24) | { len:uint32 | chunk }*
 * Manipulierte oder abgeschnittene Dateien werden erkannt (Final-Tag).
 */
class BackupCrypto
{
    private const MAGIC = 'LVBK1';

    private const CHUNK = 1048576;

    public static function encryptFile(string $in, string $out, string $passphrase): void
    {
        self::assertPassphrase($passphrase);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = self::key($passphrase, $salt);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        $src = fopen($in, 'rb');
        $dst = fopen($out, 'wb');
        if (! $src || ! $dst) {
            throw new RuntimeException('Datei kann nicht geöffnet werden.');
        }
        fwrite($dst, self::MAGIC.$salt.$header);

        do {
            $chunk = (string) fread($src, self::CHUNK);
            $last = feof($src);
            $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $enc = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
            fwrite($dst, pack('N', strlen($enc)).$enc);
        } while (! $last);

        fclose($src);
        fclose($dst);
        sodium_memzero($key);
    }

    public static function decryptFile(string $in, string $out, string $passphrase): void
    {
        $src = fopen($in, 'rb');
        $dst = fopen($out, 'wb');
        if (! $src || ! $dst) {
            throw new RuntimeException('Datei kann nicht geöffnet werden.');
        }

        $head = (string) fread($src, strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if (! str_starts_with($head, self::MAGIC) || strlen($head) < 45) {
            throw new RuntimeException('Keine gültige LicenseVault-Sicherung.');
        }
        $salt = substr($head, 5, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $header = substr($head, 5 + SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = self::key($passphrase, $salt);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        $sawFinal = false;

        while (! feof($src)) {
            $lenRaw = (string) fread($src, 4);
            if ($lenRaw === '') {
                break;
            }
            if (strlen($lenRaw) !== 4 || $sawFinal) {
                throw new RuntimeException('Sicherung ist beschädigt.');
            }
            $len = unpack('N', $lenRaw)[1];
            if ($len > self::CHUNK + 64) {
                throw new RuntimeException('Sicherung ist beschädigt.');
            }
            $enc = (string) fread($src, $len);
            $res = strlen($enc) === $len ? sodium_crypto_secretstream_xchacha20poly1305_pull($state, $enc) : false;
            if ($res === false) {
                throw new RuntimeException('Entschlüsselung fehlgeschlagen: falsche Passphrase oder beschädigte Datei.');
            }
            [$plain, $tag] = $res;
            fwrite($dst, $plain);
            $sawFinal = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }

        fclose($src);
        fclose($dst);
        sodium_memzero($key);

        if (! $sawFinal) {
            throw new RuntimeException('Sicherung ist unvollständig (abgeschnitten).');
        }
    }

    private static function key(string $passphrase, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
    }

    private static function assertPassphrase(string $passphrase): void
    {
        if (mb_strlen($passphrase) < 12) {
            throw new RuntimeException('BACKUP_PASSPHRASE fehlt oder ist kürzer als 12 Zeichen.');
        }
    }
}
