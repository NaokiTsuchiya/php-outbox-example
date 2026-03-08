<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqsConsumerTest extends TestCase
{
    private const QUEUE_URL = 'http://localhost:9324/queue/outbox.fifo';

    private function createClient(MockHandler $mock): SqsClient
    {
        return new SqsClient([
            'region'      => 'elasticmq',
            'version'     => 'latest',
            'endpoint'    => 'http://localhost:9324',
            'handler'     => $mock,
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ]);
    }

    // ── send(): SQS にメッセージを送信する ──

    #[Test]
    public function sendPublishesMessageToSqs(): void
    {
        // Given: 成功レスポンスをセットした SqsConsumer
        $mock = new MockHandler([new Result(['MessageId' => 'msg-id-001'])]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When
        $consumer->send('event-001', 'ORDER_CREATED', '{"orderId":"abc"}');

        // Then: SendMessage コマンドが呼ばれた
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('SendMessage', $lastCommand->getName());
    }

    #[Test]
    public function sendUsesCorrectQueueUrl(): void
    {
        // Given
        $mock = new MockHandler([new Result(['MessageId' => 'msg-id-002'])]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When
        $consumer->send('event-002', 'ORDER_CREATED', '{}');

        // Then: QueueUrl が正しいキューを指している
        $lastCommand = $mock->getLastCommand();
        $this->assertSame(self::QUEUE_URL, $lastCommand['QueueUrl']);
    }

    #[Test]
    public function sendEncodesMessageBodyAsJson(): void
    {
        // Given
        $mock = new MockHandler([new Result(['MessageId' => 'msg-id-003'])]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When
        $consumer->send('event-003', 'ORDER_CREATED', '{"orderId":"abc","amount":3000}');

        // Then: MessageBody に id / type / payload を含む JSON が設定される
        $lastCommand = $mock->getLastCommand();
        $body = json_decode($lastCommand['MessageBody'], true);
        $this->assertSame('event-003', $body['id']);
        $this->assertSame('ORDER_CREATED', $body['type']);
        $this->assertSame(['orderId' => 'abc', 'amount' => 3000], $body['payload']);
    }

    #[Test]
    public function sendSetsMessageGroupIdToOutbox(): void
    {
        // Given
        $mock = new MockHandler([new Result(['MessageId' => 'msg-id-004'])]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When
        $consumer->send('event-004', 'ORDER_CREATED', '{}');

        // Then: FIFO 順序保証のため MessageGroupId = 'outbox' が設定される
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('outbox', $lastCommand['MessageGroupId']);
    }

    #[Test]
    public function sendSetsMessageDeduplicationIdToEventId(): void
    {
        // Given
        $mock = new MockHandler([new Result(['MessageId' => 'msg-id-005'])]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When: イベント ID を DeduplicationId に使用（冪等配信保証）
        $consumer->send('event-005', 'ORDER_CREATED', '{}');

        // Then: MessageDeduplicationId = イベント ID
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('event-005', $lastCommand['MessageDeduplicationId']);
    }

    #[Test]
    public function sendThrowsOnInvalidPayloadJson(): void
    {
        // Given
        $mock = new MockHandler([]);
        $consumer = new SqsConsumer($this->createClient($mock), self::QUEUE_URL);

        // When / Then: 無効な JSON ペイロードでは例外が発生する（サイレント失敗禁止）
        $this->expectException(\JsonException::class);
        $consumer->send('event-err', 'ORDER_CREATED', 'invalid-json');
    }
}
