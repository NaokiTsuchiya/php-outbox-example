<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo;

class FlakeId
{
    public static function generate(): string
    {
        $timestamp = (int)(microtime(true) * 1000);
        $random    = bin2hex(random_bytes(8));
        return sprintf('%016x%s', $timestamp, $random);
    }
}
