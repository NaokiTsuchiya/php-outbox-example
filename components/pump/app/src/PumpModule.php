<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\Sqs\SqsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

class PumpModule extends AbstractModule
{
    protected function configure(): void
    {
        // ── DB ─────────────────────────────────────────────
        $this->bind(ExtendedPdoInterface::class)->toInstance(
            new ExtendedPdo(
                sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $_ENV['DB_HOST'],
                    $_ENV['DB_NAME']
                ),
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
            )
        );

        // ── Redis（通常用: Singleton）──────────────────────
        $this->bind(\Redis::class)
            ->toProvider(RedisProvider::class)
            ->in(Scope::SINGLETON);

        // ── Redis（SUBSCRIBE用: 都度新規）──────────────────
        $this->bind(\Redis::class)
            ->annotatedWith(Subscriber::class)
            ->toProvider(RedisProvider::class);

        // ── チャネル名 ──────────────────────────────────────
        $this->bind()
            ->annotatedWith('outbox_channel')
            ->toInstance($_ENV['OUTBOX_CHANNEL']);

        // ── SQS クライアント ────────────────────────────────
        // Swoole コルーチン内では CurlMultiHandler がハンドルを int にキャストして失敗するため
        // CurlHandler（シングルリクエスト）を使用する
        $this->bind(SqsClient::class)->toInstance(new SqsClient([
            'region'       => 'elasticmq',
            'version'      => 'latest',
            'endpoint'     => $_ENV['SQS_ENDPOINT'],
            'credentials'  => ['key' => 'dummy', 'secret' => 'dummy'],
            'http_handler' => new GuzzleHandler(new Client([
                'handler' => HandlerStack::create(new CurlHandler()),
            ])),
        ]));

        // ── SQS キュー URL ──────────────────────────────────
        $this->bind()
            ->annotatedWith('sqs_queue_url')
            ->toInstance($_ENV['SQS_QUEUE_URL']);

        // ── Consumer ────────────────────────────────────────
        $this->bind(ConsumerInterface::class)
            ->to(SqsConsumer::class);

        // ── Position ──────────────────────────────────────────
        $this->bind(ConsumedPositionRepository::class);

        // ── Logger ──────────────────────────────────────────
        $this->bind(LoggerInterface::class)->toInstance(
            new Logger('pump', [new StreamHandler('php://stderr')])
        );
    }
}
