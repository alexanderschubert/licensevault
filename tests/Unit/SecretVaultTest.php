<?php

namespace Tests\Unit;

use App\Support\SecretVault;
use Tests\TestCase;

class SecretVaultTest extends TestCase
{
    public function test_roundtrip_is_versioned_and_not_plaintext(): void
    {
        $stored = SecretVault::encrypt('GEHEIMER-SCHLUESSEL');

        $this->assertStringStartsWith('v1:', $stored);
        $this->assertStringNotContainsString('GEHEIMER', $stored);
        $this->assertSame('GEHEIMER-SCHLUESSEL', SecretVault::decrypt($stored));
    }

    public function test_each_encryption_is_unique(): void
    {
        $this->assertNotSame(SecretVault::encrypt('gleich'), SecretVault::encrypt('gleich'));
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        SecretVault::decrypt('v9:irgendwas');
    }
}
