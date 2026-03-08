<?php

declare(strict_types=1);

namespace MyVendor\OutboxConsumer\Module;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;

class DatabaseModuleTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        // テスト用 DB 接続情報を設定（ExtendedPdo は遅延接続のため実際の接続は行わない）
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
    }

    // ── DatabaseModule: AbstractModule のサブクラスである ──

    #[Test]
    public function databaseModuleExtendsAbstractModule(): void
    {
        // Given / When: クラス定義のみを参照（インスタンス化は行わない）
        // aura/sql 5.0.3 は PHP 8.4 の PDO::connect() 静的メソッドと競合するため
        // new DatabaseModule() はコンストラクタで configure() を呼び出し
        // AuraSqlModule が ReflectionClass 経由で ExtendedPdo をロードして Fatal Error になる
        // Then: Ray\Di AbstractModule を継承している
        $this->assertTrue(is_subclass_of(DatabaseModule::class, AbstractModule::class));
    }

    // ── DatabaseModule: 環境変数が設定されていれば例外なくインスタンス化できる ──

    // aura/sql 5.0.3 が PHP 8.4 の PDO::connect() と競合するため PHP < 8.4 でのみ実行
    #[Test]
    #[RequiresPhp('< 8.4')]
    public function databaseModuleCanBeInstantiatedWithEnvVars(): void
    {
        // Given: DB 環境変数が設定されている (setUp で設定済み)

        // When / Then: 例外なくインスタンスが生成できる
        $module = new DatabaseModule();
        $this->assertInstanceOf(DatabaseModule::class, $module);
    }
}
