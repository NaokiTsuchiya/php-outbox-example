<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Module;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use MyVendor\OutboxDemo\Outbox\ConsumedPositionRepository;
use MyVendor\OutboxDemo\Outbox\ConsumerInterface;
use MyVendor\OutboxDemo\Outbox\HttpConsumer;
use MyVendor\OutboxDemo\Outbox\OutboxPump;
use MyVendor\OutboxDemo\Outbox\OutboxSender;
use MyVendor\OutboxDemo\Outbox\OutboxSenderInterface;
use MyVendor\OutboxDemo\Outbox\Subscriber;
use MyVendor\OutboxDemo\Resource\App\OrderCommand;
use Ray\AuraSqlModule\AuraSqlModule;
use Ray\Di\Scope;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->bind(AbstractAppMeta::class)->toInstance($this->appMeta);
        // ── DB ─────────────────────────────────────────────
        // AuraSqlModule が ExtendedPdoInterface をバインドする
        // #[Transactional] AOP はこのモジュールが提供する
        $this->install(new AuraSqlModule(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'db',
                $_ENV['DB_NAME'] ?? 'outbox_demo'
            ),
            $_ENV['DB_USER'] ?? 'app',
            $_ENV['DB_PASSWORD'] ?? 'secret',
        ));

        // ── Redis ──────────────────────────────────────────
        $this->bind(\Redis::class)
            ->toProvider(RedisProvider::class);

        // SUBSCRIBE用: 専用接続（Singletonにしない）
        $this->bind(\Redis::class)
            ->annotatedWith(Subscriber::class)
            ->toProvider(RedisProvider::class);

        // ── Outbox ─────────────────────────────────────────
        $this->bind(OutboxSenderInterface::class)
            ->to(OutboxSender::class);

        $this->bind(ConsumerInterface::class)
            ->to(HttpConsumer::class);

        // Consumer エンドポイント（#[Named('consumer_endpoint')] で注入）
        $this->bind()
            ->annotatedWith('consumer_endpoint')
            ->toInstance($_ENV['CONSUMER_ENDPOINT'] ?? 'http://consumer:8081');

        $this->bind(OrderCommand::class);
        $this->bind(ConsumedPositionRepository::class);
        $this->bind(OutboxPump::class);

        // ── BEAR.Sunday コアモジュール ─────────────────────
        $this->install(new PackageModule());
    }
}
