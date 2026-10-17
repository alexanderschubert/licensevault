<?php

namespace App\Enums;

enum Platform: string
{
    case MacOS = 'macos';
    case Windows = 'windows';
    case Linux = 'linux';
    case iOS = 'ios';
    case Android = 'android';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::MacOS => 'macOS',
            self::Windows => 'Windows',
            self::Linux => 'Linux',
            self::iOS => 'iOS / iPadOS',
            self::Android => 'Android',
            self::Web => 'Web',
        };
    }
}
