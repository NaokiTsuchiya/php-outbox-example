<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Resource\App;

use Aura\Sql\ExtendedPdoInterface;
use MyVendor\OutboxDemo\Outbox\OutboxSenderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderCommandTest extends TestCase
{
    private ExtendedPdoInterface&MockObject $pdo;
    private OutboxSenderInterface&MockObject $sender;
    private OrderCommand $command;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(ExtendedPdoInterface::class);
        $this->sender = $this->createMock(OutboxSenderInterface::class);
        $this->command = new OrderCommand($this->pdo, $this->sender);
    }

    #[Test]
    public function createInsertsOrderAndSendsOutboxEvent(): void
    {
        // Then: orders テーブルに INSERT される
        $this->pdo->expects($this->once())
            ->method('perform')
            ->with(
                $this->stringContains('INSERT INTO orders'),
                $this->callback(function (array $params): bool {
                    return $params['user_id'] === 'user-1'
                        && $params['amount'] === 3000
                        && strlen($params['id']) > 0;
                })
            );

        // Then: Outbox にイベントが送信される
        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'ORDER_CREATED',
                $this->callback(function (array $payload): bool {
                    return $payload['user_id'] === 'user-1'
                        && $payload['amount'] === 3000
                        && strlen($payload['order_id']) > 0;
                })
            );

        // When
        $orderId = $this->command->create('user-1', 3000);

        // Then: orderId が返される
        $this->assertNotEmpty($orderId);
    }
}