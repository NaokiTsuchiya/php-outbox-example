<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aws\Sqs\SqsClient;
use Ray\Di\Di\Named;

class SqsConsumer implements ConsumerInterface
{
    public function __construct(
        private SqsClient $sqs,
        #[Named('sqs_queue_url')] private string $queueUrl,
    ) {}

    public function send(string $id, string $type, string $payload): void
    {
        // JSON_THROW_ON_ERROR で不正ペイロードを早期検出
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $this->sqs->sendMessage([
            'QueueUrl'               => $this->queueUrl,
            'MessageBody'            => json_encode([
                'id'      => $id,
                'type'    => $type,
                'payload' => $decoded,
            ]),
            'MessageGroupId'         => 'outbox',
            // イベントIDで重複排除（at-least-once 配信への対応）
            'MessageDeduplicationId' => $id,
        ]);
    }
}
