<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;
use Psr\Log\LoggerInterface;
use Ray\Di\Di\Named;
use Swoole\Coroutine;

/**
 * Pump：Redis SUBSCRIBEで起床しDBから順番に読んで配信・ACK
 *
 * Ray\Di ベースで動作する独立プロセス。
 * strong-zeroのPump(DEALER) + Consumer(REQ)を兼ねる。
 *
 * 通知 : Redis SUBSCRIBE（どのWebアプリインスタンスからでも届く）
 * キュー: produced_zeroテーブル（DBが永続化を担保）
 * 順序 : SELECT ORDER BY id（Flake IDで時系列ソート）
 * 再開 : consumed_zeroのlast_idから（再起動後も安全）
 */
class OutboxPump
{
    private string $lastId;

    public function __construct(
        private ExtendedPdoInterface $pdo,
        #[Subscriber] private \Redis $subscriber,
        private ConsumerInterface $consumer,
        private ConsumedPositionRepository $position,
        private LoggerInterface $logger,
        #[Named('outbox_channel')] private string $channel,
        private int $batchSize = 10,
        private int $pollIntervalSec = 10,
    ) {}

    public function run(): void
    {
        $this->lastId = $this->position->get();
        $this->logger->info('OutboxPump started', ['last_id' => $this->lastId]);

        $channel = new \Swoole\Coroutine\Channel(1);

        // コルーチン1: Redis SUBSCRIBE
        Coroutine::create(function () use ($channel) {
            while (true) {
                try {
                    $this->subscriber->subscribe([$this->channel], function (\Redis $redis, string $ch, string $msg) use ($channel) {
                        $channel->push(true);
                        $redis->unsubscribe([$this->channel]);
                    });
                } catch (\RedisException $e) {
                    $this->logger->error('Redis subscribe error, reconnecting', ['message' => $e->getMessage()]);
                    Coroutine::sleep(1);
                    $this->subscriber->connect(
                        $this->subscriber->getHost(),
                        $this->subscriber->getPort()
                    );
                }
            }
        });

        // コルーチン2: 定期ポーリング
        Coroutine::create(function () use ($channel) {
            while (true) {
                Coroutine::sleep($this->pollIntervalSec);
                $channel->push(true);
            }
        });

        // メイン: Channel から pop して relay
        $this->relay();
        while (true) {
            $channel->pop();
            try {
                $this->relay();
            } catch (\Throwable $e) {
                $this->logger->error('Relay failed', ['message' => $e->getMessage()]);
                Coroutine::sleep(1);
            }
        }
    }

    private function relay(): void
    {
        $rows = $this->pdo->fetchAll(
            'SELECT id, type, message FROM produced_zero
             WHERE id > :last_id ORDER BY id LIMIT :limit',
            ['last_id' => $this->lastId, 'limit' => $this->batchSize]
        );

        if (empty($rows)) {
            return;
        }

        $this->logger->debug('Relaying', ['count' => count($rows), 'from_id' => $this->lastId]);

        foreach ($rows as $row) {
            $this->consumer->send($row['id'], $row['type'], $row['message']);

            $this->position->update($row['id']);
            $this->lastId = $row['id'];

            $this->logger->info('Delivered', ['id' => $row['id'], 'type' => $row['type']]);
        }
    }
}
