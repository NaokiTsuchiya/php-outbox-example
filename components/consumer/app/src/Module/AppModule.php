<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use MyVendor\OutboxConsumer\Event\EventProcessor;
use MyVendor\OutboxConsumer\Event\ProcessedEventRepository;
use MyVendor\OutboxConsumer\Mail\LogMailer;
use MyVendor\OutboxConsumer\Mail\MailerInterface;
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
                $_ENV['DB_HOST'] ?? 'db',
                $_ENV['DB_NAME'] ?? 'outbox_demo'
            ),
            $_ENV['DB_USER'] ?? 'app',
            $_ENV['DB_PASSWORD'] ?? 'secret',
        ));

        // ── Mail ───────────────────────────────────────────
        $this->bind(MailerInterface::class)
            ->to(LogMailer::class);

        $this->bind(EventProcessor::class);
        $this->bind(ProcessedEventRepository::class);

        // ── BEAR.Sunday コアモジュール ─────────────────────
        $this->install(new PackageModule());
    }
}
