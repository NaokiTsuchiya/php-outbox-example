<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Worker;

use Aws\Sqs\SqsClient;
use MyVendor\OutboxConsumer\Event\EventProcessor;
use Psr\Log\LoggerInterface;
use Ray\Di\Di\Named;
use Swoole\Coroutine;

class SqsWorker
{
    private const WAIT_TIME_SECONDS = 20;

    public function __construct(
        private SqsClient $sqs,
        #[Named('sqs_queue_url')] private string $queueUrl,
        private EventProcessor $processor,
        private LoggerInterface $logger,
    ) {}

    /**
     * SQS からメッセージをポーリングして処理する（1バッチ分）
     */
    public function poll(): void
    {
        $result = $this->sqs->receiveMessage([
            'QueueUrl'            => $this->queueUrl,
            'MaxNumberOfMessages' => 10,
            'WaitTimeSeconds'     => self::WAIT_TIME_SECONDS,
        ]);

        $messages = $result['Messages'] ?? [];

        foreach ($messages as $message) {
            $this->processMessage($message);
        }
    }

    /**
     * 無限ループで継続ポーリング（本番エントリーポイント用）
     *
     * トランスポート層（SQS）の一時的な障害は catch して 1 秒バックオフ後にリトライ。
     * OutboxPump::run() と同パターン。
     */
    public function run(): void
    {
        $this->logger->info('SqsWorker started', ['queue' => $this->queueUrl]);

        while (true) {
            try {
                $this->poll();
            } catch (\Throwable $e) {
                $this->logger->error('Poll failed, retrying', ['error' => $e->getMessage()]);
                Coroutine::sleep(1);
            }
        }
    }

    private function processMessage(array $message): void
    {
        try {
            // JSON_THROW_ON_ERROR で不正 Body を早期検出
            $body = json_decode($message['Body'], true, 512, JSON_THROW_ON_ERROR);

            $this->processor->process($body['id'], $body['type'], $body['payload']);

            $this->sqs->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            $this->logger->info('Processed', ['id' => $body['id'], 'type' => $body['type']]);
        } catch (\Throwable $e) {
            // 失敗時は VisibilityTimeout 後に SQS が再配信するため、削除しない
            $this->logger->error('Failed to process message', [
                'receiptHandle' => $message['ReceiptHandle'],
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
