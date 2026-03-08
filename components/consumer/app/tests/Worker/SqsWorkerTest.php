<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Worker;

use Aws\MockHandler;
use Aws\Result;
use Aws\Sqs\SqsClient;
use MyVendor\OutboxConsumer\Event\EventProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SqsWorkerTest extends TestCase
{
    private const QUEUE_URL = 'http://localhost:9324/queue/outbox.fifo';

    private EventProcessor&MockObject $processor;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(EventProcessor::class);
        $this->logger    = $this->createMock(LoggerInterface::class);
    }

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

    private function createWorker(MockHandler $mock): SqsWorker
    {
        return new SqsWorker(
            $this->createClient($mock),
            self::QUEUE_URL,
            $this->processor,
            $this->logger,
        );
    }

    /** SQS メッセージ配列を生成するヘルパー */
    private function makeMessage(string $id, string $type, array $payload, string $receiptHandle): array
    {
        return [
            'MessageId'     => 'sqs-msg-' . $id,
            'Body'          => json_encode(['id' => $id, 'type' => $type, 'payload' => $payload]),
            'ReceiptHandle' => $receiptHandle,
        ];
    }

    // ── poll(): メッセージなし ──

    #[Test]
    public function pollDoesNothingWhenNoMessages(): void
    {
        // Given: SQS に未処理メッセージがない
        $mock = new MockHandler([new Result(['Messages' => []])]);

        // Then: EventProcessor は呼ばれない
        $this->processor->expects($this->never())->method('process');

        // When
        $this->createWorker($mock)->poll();
    }

    #[Test]
    public function pollDoesNothingWhenMessagesKeyAbsent(): void
    {
        // Given: SQS が Messages キーなしのレスポンスを返す（ロングポーリング空応答）
        $mock = new MockHandler([new Result([])]);

        // Then: EventProcessor は呼ばれない
        $this->processor->expects($this->never())->method('process');

        // When: 例外なく完了する
        $this->createWorker($mock)->poll();
    }

    // ── poll(): 1件処理 ──

    #[Test]
    public function pollPassesDecodedPayloadToProcessor(): void
    {
        // Given: JSON ペイロードを含む 1 件のメッセージ
        $payload = ['orderId' => 'abc', 'amount' => 3000];
        $message = $this->makeMessage('evt-001', 'ORDER_CREATED', $payload, 'receipt-001');
        $mock = new MockHandler([
            new Result(['Messages' => [$message]]),
            new Result([]), // deleteMessage レスポンス
        ]);

        // Then: EventProcessor::process が正しい引数で呼ばれる
        $this->processor->expects($this->once())
            ->method('process')
            ->with('evt-001', 'ORDER_CREATED', $payload);

        // When
        $this->createWorker($mock)->poll();
    }

    #[Test]
    public function pollDeletesMessageAfterSuccessfulProcessing(): void
    {
        // Given
        $message = $this->makeMessage('evt-002', 'ORDER_CREATED', [], 'receipt-002');
        $mock = new MockHandler([
            new Result(['Messages' => [$message]]),
            new Result([]), // deleteMessage
        ]);

        // When
        $this->createWorker($mock)->poll();

        // Then: DeleteMessage が ReceiptHandle と QueueUrl を使って呼ばれた
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('DeleteMessage', $lastCommand->getName());
        $this->assertSame('receipt-002', $lastCommand['ReceiptHandle']);
        $this->assertSame(self::QUEUE_URL, $lastCommand['QueueUrl']);
    }

    // ── poll(): バッチ処理 ──

    #[Test]
    public function pollProcessesMultipleMessagesInBatch(): void
    {
        // Given: 3 件のメッセージが届いている
        $messages = [
            $this->makeMessage('evt-010', 'ORDER_CREATED', ['orderId' => 'a'], 'receipt-010'),
            $this->makeMessage('evt-011', 'ORDER_CREATED', ['orderId' => 'b'], 'receipt-011'),
            $this->makeMessage('evt-012', 'ORDER_SHIPPED', ['orderId' => 'c'], 'receipt-012'),
        ];
        $mock = new MockHandler([
            new Result(['Messages' => $messages]),
            new Result([]), // delete 1
            new Result([]), // delete 2
            new Result([]), // delete 3
        ]);

        // Then: EventProcessor が 3 件全て処理する
        $this->processor->expects($this->exactly(3))->method('process');

        // When
        $this->createWorker($mock)->poll();
    }

    #[Test]
    public function pollDeletesEachMessageAfterProcessing(): void
    {
        // Given: 2 件のメッセージ
        $messages = [
            $this->makeMessage('evt-020', 'ORDER_CREATED', [], 'receipt-020'),
            $this->makeMessage('evt-021', 'ORDER_CREATED', [], 'receipt-021'),
        ];
        $mock = new MockHandler([
            new Result(['Messages' => $messages]),
            new Result([]), // delete evt-020
            new Result([]), // delete evt-021
        ]);

        // When
        $this->createWorker($mock)->poll();

        // Then: 最後のコマンドが DeleteMessage（2 件目の削除）
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('DeleteMessage', $lastCommand->getName());
        $this->assertSame('receipt-021', $lastCommand['ReceiptHandle']);
    }

    // ── poll(): 処理失敗時はメッセージを削除しない ──

    #[Test]
    public function pollDoesNotDeleteMessageOnProcessingFailure(): void
    {
        // Given: EventProcessor が例外をスロー
        $message = $this->makeMessage('evt-030', 'ORDER_CREATED', [], 'receipt-030');
        // deleteMessage のモックを積まない（呼ばれたら MockHandler が例外を投げる）
        $mock = new MockHandler([new Result(['Messages' => [$message]])]);

        $this->processor->method('process')
            ->willThrowException(new \RuntimeException('処理失敗'));

        // When: 失敗は握りつぶしてポーリングは継続する（VisibilityTimeout 後に再配信）
        $this->createWorker($mock)->poll();

        // Then: 最後のコマンドは ReceiveMessage のまま（DeleteMessage は呼ばれていない）
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('ReceiveMessage', $lastCommand->getName());
    }

    #[Test]
    public function pollLogsErrorOnProcessingFailure(): void
    {
        // Given: EventProcessor が例外をスロー
        $message = $this->makeMessage('evt-031', 'ORDER_CREATED', [], 'receipt-031');
        $mock = new MockHandler([new Result(['Messages' => [$message]])]);

        $this->processor->method('process')
            ->willThrowException(new \RuntimeException('処理失敗'));

        // Then: エラーログが出力される
        $this->logger->expects($this->once())->method('error');

        // When
        $this->createWorker($mock)->poll();
    }

    // ── poll(): ReceiveMessage のパラメータ検証 ──

    #[Test]
    public function pollRequestsTenMessagesWithLongPolling(): void
    {
        // Given
        $mock = new MockHandler([new Result(['Messages' => []])]);

        // When
        $this->createWorker($mock)->poll();

        // Then: ReceiveMessage のパラメータが正しい
        $lastCommand = $mock->getLastCommand();
        $this->assertSame('ReceiveMessage', $lastCommand->getName());
        $this->assertSame(10, $lastCommand['MaxNumberOfMessages']);
        $this->assertSame(20, $lastCommand['WaitTimeSeconds']);
        $this->assertSame(self::QUEUE_URL, $lastCommand['QueueUrl']);
    }

    // ── poll(): トランスポート層エラー（run() の try/catch が受け取る） ──

    #[Test]
    public function pollPropagatesReceiveMessageException(): void
    {
        // Given: SQS が一時的なネットワーク障害で例外をスロー
        // MockHandler に Exception を積むと AWS SDK が AwsException として伝播する
        $mock = new MockHandler([new \RuntimeException('ネットワーク障害')]);

        // When / Then: poll() はトランスポート層例外を握りつぶさず伝播する
        // → run() の try/catch が受け取れることを保証する再発防止テスト
        $this->expectException(\RuntimeException::class);

        $this->createWorker($mock)->poll();
    }

    // ── poll(): 不正 JSON ──

    #[Test]
    public function pollHandlesInvalidJsonBodyWithoutCrash(): void
    {
        // Given: Body が無効な JSON のメッセージ（配信ミス等）
        $mock = new MockHandler([
            new Result(['Messages' => [[
                'MessageId'     => 'bad-msg',
                'Body'          => 'not-valid-json',
                'ReceiptHandle' => 'receipt-bad',
            ]]]),
        ]);

        // Then: EventProcessor は呼ばれない
        $this->processor->expects($this->never())->method('process');

        // Then: エラーログが出力される
        $this->logger->expects($this->once())->method('error');

        // When: クラッシュせずに処理継続できる
        $this->createWorker($mock)->poll();
    }
}
