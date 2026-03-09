<?php

declare(strict_types=1);

namespace MyVendor\OutboxDemo\Module;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;

class DatabaseModuleTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        // テスト用 DB 接続情報を設定（実際の接続は行わない）
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    // ── DatabaseModule: AbstractModule のサブクラスとしてインスタンス化できる ──

    #[Test]
    public function databaseModuleCanBeInstantiatedWithEnvVars(): void
    {
        // Given: DB 環境変数が設定されている (setUp で設定済み)

        // When: インスタンスを生成する
        $module = new DatabaseModule();

        // Then: AbstractModule を継承したインスタンスが生成できる
        $this->assertInstanceOf(AbstractModule::class, $module);
    }
}
