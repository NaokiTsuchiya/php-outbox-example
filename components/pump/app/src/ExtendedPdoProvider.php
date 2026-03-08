<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use Ray\Di\ProviderInterface;

/** 環境変数から DB 接続情報を読み取り ExtendedPdo を提供する */
class ExtendedPdoProvider implements ProviderInterface
{
    public function get(): ExtendedPdoInterface
    {
        return new ExtendedPdo(
            sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            ),
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
        );
    }
}
