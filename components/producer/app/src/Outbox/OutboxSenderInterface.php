<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

interface OutboxSenderInterface
{
    public function send(string $type, array $payload): void;

    public function notify(): void;
}
