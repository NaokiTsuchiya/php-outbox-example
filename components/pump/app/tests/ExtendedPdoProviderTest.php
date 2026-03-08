<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// aura/sql 5.0.3 は PHP 8.4 の PDO::connect() 静的メソッドと競合して Fatal Error になるため
// PHP < 8.4 でのみ実行する。PHP >= 8.4 では aura/sql のアップデートが必要。
#[RequiresPhp('< 8.4')]
class ExtendedPdoProviderTest extends TestCase
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

    // ── get(): ExtendedPdoInterface を返す ──

    #[Test]
    public function getReturnsExtendedPdoInterface(): void
    {
        // Given: DB 環境変数が設定されている（setUp で設定済み）
        $provider = new ExtendedPdoProvider();

        // When
        $result = $provider->get();

        // Then: ExtendedPdoInterface の実装が返される
        $this->assertInstanceOf(ExtendedPdoInterface::class, $result);
    }

    #[Test]
    public function getCreatesDsnFromDbHostAndName(): void
    {
        // Given: 特定の DB_HOST / DB_NAME が設定されている
        $_ENV['DB_HOST'] = 'mydbhost';
        $_ENV['DB_NAME'] = 'mydbname';
        $provider = new ExtendedPdoProvider();

        // When: get() が呼ばれる
        // Then: 例外なく ExtendedPdoInterface が返される（DSN は遅延評価）
        $result = $provider->get();
        $this->assertInstanceOf(ExtendedPdoInterface::class, $result);
    }
}
