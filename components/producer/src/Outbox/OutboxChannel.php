<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

final class OutboxChannel
{
    public const NOTIFY = 'outbox:notify';
}
