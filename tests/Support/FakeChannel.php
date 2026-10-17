<?php

namespace Tests\Support;

use App\Notifications\Channels\NotificationChannel;
use App\Notifications\PushMessage;

class FakeChannel implements NotificationChannel
{
    /** @var list<PushMessage> */
    public array $sent = [];

    public function __construct(private bool $configured = true) {}

    public function name(): string
    {
        return 'Fake';
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function send(PushMessage $message): int
    {
        $this->sent[] = $message;

        return 1;
    }
}
