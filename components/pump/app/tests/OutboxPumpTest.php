<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OutboxPumpTest extends TestCase
{
    private ExtendedPdoInterface&MockObject $pdo;
    private \Redis&MockObject $subscriber;
    private ConsumerInterface&MockObject $consumer;
    private ConsumedPositionRepository&MockObject $position;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(ExtendedPdoInterface::class);
        $this->subscriber = $this->createMock(\Redis::class);
        $this->consumer = $this->createMock(ConsumerInterface::class);
        $this->position = $this->createMock(ConsumedPositionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createPump(int $pollIntervalSec = 10, int $batchSize = 10): OutboxPump
    {
        return new OutboxPump(
            $this->pdo,
            $this->subscriber,
            $this->consumer,
            $this->position,
            $this->logger,
            'outbox:notify',
            $batchSize,
            $pollIntervalSec,
        );
    }

    private function invokeRelay(OutboxPump $pump): void
    {
        $method = new \ReflectionMethod($pump, 'relay');
        $method->invoke($pump);
    }

    private function setLastId(OutboxPump $pump, string $lastId): void
    {
        $property = new \ReflectionProperty($pump, 'lastId');
        $property->setValue($pump, $lastId);
    }

    private function getLastId(OutboxPump $pump): string
    {
        $property = new \ReflectionProperty($pump, 'lastId');
        return $property->getValue($pump);
    }

    // ── relay(): 未配信メッセージなし ──

    #[Test]
    public function relayDoesNothingWhenNoRowsExist(): void
    {
        // Given: DB に未配信メッセージがない
        $pump = $this->createPump();
        $this->setLastId($pump, '0');

        $this->pdo->method('fetchAll')->willReturn([]);

        // Then: consumer は呼ばれない
        $this->consumer->expects($this->never())->method('send');
        $this->position->expects($this->never())->method('update');

        // When
        $this->invokeRelay($pump);
    }

    // ── relay(): 単一メッセージの配信 ──

    #[Test]
    public function relayDeliversSingleRow(): void
    {
        // Given: DB に 1 件の未配信メッセージ
        $pump = $this->createPump();
        $this->setLastId($pump, '0');

        $rows = [
            ['id' => '100', 'type' => 'order.created', 'message' => '{"orderId":"abc"}'],
        ];
        $this->pdo->method('fetchAll')->willReturn($rows);

        // Then: consumer に 1 件送信され、position が更新される
        $this->consumer->expects($this->once())
            ->method('send')
            ->with('100', 'order.created', '{"orderId":"abc"}');

        $this->position->expects($this->once())
            ->method('update')
            ->with('100');

        // When
        $this->invokeRelay($pump);

        // Then: lastId が更新される
        $this->assertSame('100', $this->getLastId($pump));
    }

    // ── relay(): 複数メッセージの順序保証 ──

    #[Test]
    public function relayDeliversMultipleRowsInOrder(): void
    {
        // Given: DB に 3 件の未配信メッセージ
        $pump = $this->createPump();
        $this->setLastId($pump, '0');

        $rows = [
            ['id' => '100', 'type' => 'order.created', 'message' => '{"orderId":"a"}'],
            ['id' => '200', 'type' => 'order.created', 'message' => '{"orderId":"b"}'],
            ['id' => '300', 'type' => 'order.shipped', 'message' => '{"orderId":"c"}'],
        ];
        $this->pdo->method('fetchAll')->willReturn($rows);

        // Then: consumer に順番通り送信される
        $callOrder = [];
        $this->consumer->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(function (string $id, string $type, string $payload) use (&$callOrder) {
                $callOrder[] = $payload;
            });

        // Then: position も順番通り更新される
        $positionUpdates = [];
        $this->position->expects($this->exactly(3))
            ->method('update')
            ->willReturnCallback(function (string $id) use (&$positionUpdates) {
                $positionUpdates[] = $id;
            });

        // When
        $this->invokeRelay($pump);

        // Then
        $this->assertSame(['{"orderId":"a"}', '{"orderId":"b"}', '{"orderId":"c"}'], $callOrder);
        $this->assertSame(['100', '200', '300'], $positionUpdates);
        $this->assertSame('300', $this->getLastId($pump));
    }

    // ── relay(): position は各行ごとに更新される ──

    #[Test]
    public function relayUpdatesPositionAfterEachDelivery(): void
    {
        // Given
        $pump = $this->createPump();
        $this->setLastId($pump, '50');

        $rows = [
            ['id' => '100', 'type' => 'order.created', 'message' => '{"orderId":"x"}'],
            ['id' => '200', 'type' => 'order.created', 'message' => '{"orderId":"y"}'],
        ];
        $this->pdo->method('fetchAll')->willReturn($rows);

        // Then: position.update → consumer.send の呼び出し順を検証
        $sequence = [];
        $this->consumer->method('send')
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'send';
            });
        $this->position->method('update')
            ->willReturnCallback(function () use (&$sequence) {
                $sequence[] = 'update';
            });

        // When
        $this->invokeRelay($pump);

        // Then: send → update が各行で繰り返される
        $this->assertSame(['send', 'update', 'send', 'update'], $sequence);
    }

    // ── relay(): lastId から後のメッセージのみ取得 ──

    #[Test]
    public function relayQueriesFromLastId(): void
    {
        // Given: lastId が 500 の状態
        $pump = $this->createPump(batchSize: 5);
        $this->setLastId($pump, '500');

        // Then: fetchAll が lastId=500, limit=5 で呼ばれる
        $this->pdo->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    return $params['last_id'] === '500' && $params['limit'] === 5;
                })
            )
            ->willReturn([]);

        // When
        $this->invokeRelay($pump);
    }

    // ── relay(): ログ出力の検証 ──

    #[Test]
    public function relayLogsDeliveryForEachRow(): void
    {
        // Given
        $pump = $this->createPump();
        $this->setLastId($pump, '0');

        $rows = [
            ['id' => '100', 'type' => 'order.created', 'message' => '{"orderId":"a"}'],
        ];
        $this->pdo->method('fetchAll')->willReturn($rows);

        // Then: info ログが呼ばれる
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Delivered', ['id' => '100', 'type' => 'order.created']);

        // When
        $this->invokeRelay($pump);
    }

    #[Test]
    public function relayLogsDebugWithCountAndFromId(): void
    {
        // Given
        $pump = $this->createPump();
        $this->setLastId($pump, '50');

        $rows = [
            ['id' => '100', 'type' => 'order.created', 'message' => '{}'],
            ['id' => '200', 'type' => 'order.shipped', 'message' => '{}'],
        ];
        $this->pdo->method('fetchAll')->willReturn($rows);

        // Then: debug ログに count と from_id が含まれる
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Relaying', ['count' => 2, 'from_id' => '50']);

        // When
        $this->invokeRelay($pump);
    }

    #[Test]
    public function relayDoesNotLogWhenNoRows(): void
    {
        // Given
        $pump = $this->createPump();
        $this->setLastId($pump, '0');

        $this->pdo->method('fetchAll')->willReturn([]);

        // Then: debug/info ログは呼ばれない
        $this->logger->expects($this->never())->method('debug');
        $this->logger->expects($this->never())->method('info');

        // When
        $this->invokeRelay($pump);
    }

    // ── コンストラクタ: パラメータの検証 ──

    #[Test]
    public function constructorAcceptsPollIntervalSecParameter(): void
    {
        // Given/When: 名前付き引数で pollIntervalSec を渡してインスタンス化
        $pump = new OutboxPump(
            pdo: $this->pdo,
            subscriber: $this->subscriber,
            consumer: $this->consumer,
            position: $this->position,
            logger: $this->logger,
            channel: 'outbox:notify',
            batchSize: 10,
            pollIntervalSec: 30,
        );

        // Then: インスタンスが生成できる
        $this->assertInstanceOf(OutboxPump::class, $pump);
    }

    #[Test]
    public function constructorDefaultPollIntervalSecIsTen(): void
    {
        // Given/When: pollIntervalSec を省略してインスタンス化
        $pump = new OutboxPump(
            pdo: $this->pdo,
            subscriber: $this->subscriber,
            consumer: $this->consumer,
            position: $this->position,
            logger: $this->logger,
            channel: 'outbox:notify',
        );

        // Then: デフォルト値 10 が設定される
        $property = new \ReflectionProperty($pump, 'pollIntervalSec');
        $this->assertSame(10, $property->getValue($pump));
    }

    #[Test]
    public function constructorAcceptsChannelParameter(): void
    {
        // Given/When: カスタムチャネル名でインスタンス化
        $pump = new OutboxPump(
            pdo: $this->pdo,
            subscriber: $this->subscriber,
            consumer: $this->consumer,
            position: $this->position,
            logger: $this->logger,
            channel: 'custom:channel',
        );

        // Then: チャネル名が設定される
        $property = new \ReflectionProperty($pump, 'channel');
        $this->assertSame('custom:channel', $property->getValue($pump));
    }
}
