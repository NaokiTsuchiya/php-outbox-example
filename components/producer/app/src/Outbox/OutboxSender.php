<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Outbox;

use Aura\Sql\ExtendedPdoInterface;
use MyVendor\OutboxDemo\FlakeId;

/**
 * strong-zeroの Sender#send() + updated() に相当
 *
 * send(): produced_zero INSERT。呼び出し元のトランザクション内で実行する。
 * notify(): Redis PUBLISH。COMMIT 後に呼び出す。
 */
class OutboxSender implements OutboxSenderInterface
{
    public function __construct(
        private ExtendedPdoInterface $pdo,
        private \Redis $redis,
    ) {}

    public function send(string $type, array $payload): void
    {
        $id = FlakeId::generate();

        $this->pdo->perform(
            'INSERT INTO produced_zero (id, type, message) VALUES (:id, :type, :message)',
            ['id' => $id, 'type' => $type, 'message' => json_encode($payload)]
        );
    }

    public function notify(): void
    {
        $this->redis->publish(OutboxChannel::NOTIFY, 'notify');
    }
}
