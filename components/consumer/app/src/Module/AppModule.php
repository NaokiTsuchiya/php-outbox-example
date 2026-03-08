<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\Sqs\SqsClient;
use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyVendor\OutboxConsumer\Event\EventProcessor;
use MyVendor\OutboxConsumer\Event\ProcessedEventRepository;
use MyVendor\OutboxConsumer\Mail\LogMailer;
use MyVendor\OutboxConsumer\Mail\MailerInterface;
use Psr\Log\LoggerInterface;
use Ray\AuraSqlModule\AuraSqlModule;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->bind(AbstractAppMeta::class)->toInstance($this->appMeta);

        // ── DB ─────────────────────────────────────────────
        $this->install(new AuraSqlModule(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            ),
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
        ));

        // ── Mail ───────────────────────────────────────────
        $this->bind(MailerInterface::class)
            ->to(LogMailer::class);

        $this->bind(EventProcessor::class);
        $this->bind(ProcessedEventRepository::class);

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

        // ── Logger（標準エラー出力）──────────────────────────
        $this->bind(LoggerInterface::class)->toInstance(
            new Logger('consumer', [new StreamHandler('php://stderr')])
        );

        // ── BEAR.Sunday コアモジュール ─────────────────────
        $this->install(new PackageModule());
    }
}
