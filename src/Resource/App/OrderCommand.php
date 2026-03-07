<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Resource\App;

use Aura\Sql\ExtendedPdoInterface;
use MyVendor\OutboxDemo\FlakeId;
use MyVendor\OutboxDemo\Outbox\OutboxSenderInterface;
use Ray\AuraSqlModule\Annotation\Transactional;

class OrderCommand
{
    public function __construct(
        private ExtendedPdoInterface $pdo,
        private OutboxSenderInterface $sender,
    ) {}

    #[Transactional]
    public function create(string $userId, int $amount): string
    {
        $orderId = FlakeId::generate();

        $this->pdo->perform(
            'INSERT INTO orders (id, user_id, amount) VALUES (:id, :user_id, :amount)',
            ['id' => $orderId, 'user_id' => $userId, 'amount' => $amount]
        );

        $this->sender->send('ORDER_CREATED', [
            'order_id' => $orderId,
            'user_id'  => $userId,
            'amount'   => $amount,
        ]);

        return $orderId;
    }
}
