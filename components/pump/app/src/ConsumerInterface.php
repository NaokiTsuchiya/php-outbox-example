<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

interface ConsumerInterface
{
    public function send(string $id, string $type, string $payload): void;
}
