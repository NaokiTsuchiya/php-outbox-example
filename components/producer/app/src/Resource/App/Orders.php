<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;
use MyVendor\OutboxDemo\Outbox\OutboxSenderInterface;
use Aura\Sql\ExtendedPdoInterface;

/**
 * 注文リソース
 *
 * ALPS: alps/orders.json
 */
class Orders extends ResourceObject
{
    public function __construct(
        private ExtendedPdoInterface $pdo,
        private OrderCommand $command,
        private OutboxSenderInterface $sender,
    ) {}

    #[Link(rel: 'do-create-order', href: '/orders')]
    public function onGet(): static
    {
        $this->body = [
            'orders' => $this->pdo->fetchAll('SELECT id, user_id, amount, created_at FROM orders ORDER BY created_at DESC'),
        ];

        return $this;
    }

    #[Link(rel: 'go-orders', href: '/orders')]
    public function onPost(string $userId, int $amount): static
    {
        $orderId = $this->command->create($userId, $amount);

        // COMMIT 後に Pump へ通知（ベストエフォート）
        $this->sender->notify();

        $this->code = 201;
        $this->body = ['order_id' => $orderId];

        return $this;
    }
}
