<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

use Ray\Di\Di\Named;

class HttpConsumer implements ConsumerInterface
{
    public function __construct(
        #[Named('consumer_endpoint')] private string $endpoint,
    ) {}

    public function send(string $type, string $payload): void
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'type'    => $type,
                    'payload' => json_decode($payload, true),
                ]),
                'timeout' => 5,
            ],
        ]);

        $result = file_get_contents("{$this->endpoint}/events", false, $context);

        if ($result === false) {
            throw new \RuntimeException("HttpConsumer failed: type={$type}");
        }
    }
}
