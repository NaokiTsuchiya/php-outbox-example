<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Resource\App;

use BEAR\Resource\ResourceObject;
use MyVendor\OutboxConsumer\Event\EventProcessor;

/**
 * イベント受信リソース
 *
 * ALPS: alps/consumer.json
 */
class Events extends ResourceObject
{
    public function __construct(
        private EventProcessor $processor,
    ) {}

    public function onPost(string $id, string $type, array $payload): static
    {
        $this->processor->process($id, $type, $payload);
        $this->body = ['status' => 'accepted'];

        return $this;
    }
}
