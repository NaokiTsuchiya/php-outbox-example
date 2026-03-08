<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use Aws\Sqs\SqsClient;
use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use MyVendor\OutboxConsumer\Event\EventProcessor;
use MyVendor\OutboxConsumer\Event\ProcessedEventRepository;
use MyVendor\OutboxConsumer\Mail\LogMailer;
use MyVendor\OutboxConsumer\Mail\MailerInterface;
use Psr\Log\LoggerInterface;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->bind(AbstractAppMeta::class)->toInstance($this->appMeta);

        // ── DB ─────────────────────────────────────────────
        $this->install(new DatabaseModule());

        // ── Mail ───────────────────────────────────────────
        $this->bind(MailerInterface::class)
            ->to(LogMailer::class);

        $this->bind(EventProcessor::class);
        $this->bind(ProcessedEventRepository::class);

        // ── SQS クライアント ────────────────────────────────
        $this->bind(SqsClient::class)
            ->toProvider(SqsClientProvider::class);

        // ── SQS キュー URL ──────────────────────────────────
        $this->bind()
            ->annotatedWith('sqs_queue_url')
            ->toProvider(SqsQueueUrlProvider::class);

        // ── Logger（標準エラー出力）──────────────────────────
        $this->bind(LoggerInterface::class)
            ->toProvider(LoggerProvider::class);

        // ── BEAR.Sunday コアモジュール ─────────────────────
        $this->install(new PackageModule());
    }
}
