<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use Ray\AuraSqlModule\AuraSqlModule;
use Ray\Di\AbstractModule;

/** DB 接続設定を環境変数から読み取り AuraSqlModule をインストールするモジュール */
class DatabaseModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new AuraSqlModule(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            ),
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
        ));
    }
}
