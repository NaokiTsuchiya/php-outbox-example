<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

interface ConsumerInterface
{
    public function send(string $type, string $payload): void;
}
