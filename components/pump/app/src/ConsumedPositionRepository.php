<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;

class ConsumedPositionRepository
{
    private const PRODUCER_ID = 'producer1';

    public function __construct(
        private ExtendedPdoInterface $pdo,
    ) {}

    public function get(): string
    {
        $row = $this->pdo->fetchOne(
            'SELECT last_id FROM consumed_zero WHERE producer_id = :producer_id',
            ['producer_id' => self::PRODUCER_ID]
        );
        return $row ? $row['last_id'] : '0';
    }

    public function update(string $id): void
    {
        $this->pdo->perform(
            'INSERT INTO consumed_zero (producer_id, last_id) VALUES (:producer_id, :last_id)
             ON DUPLICATE KEY UPDATE last_id = :last_id',
            ['producer_id' => self::PRODUCER_ID, 'last_id' => $id]
        );
    }
}
