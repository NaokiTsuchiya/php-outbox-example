<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Event;

use Aura\Sql\ExtendedPdoInterface;

class ProcessedEventRepository
{
    public function __construct(
        private ExtendedPdoInterface $pdo,
    ) {}

    public function isProcessed(string $eventId): bool
    {
        $row = $this->pdo->fetchOne(
            'SELECT event_id FROM processed_events WHERE event_id = :event_id',
            ['event_id' => $eventId]
        );

        return $row !== false;
    }

    public function markProcessed(string $eventId, string $type): void
    {
        $this->pdo->perform(
            'INSERT INTO processed_events (event_id, type) VALUES (:event_id, :type)',
            ['event_id' => $eventId, 'type' => $type]
        );
    }
}
