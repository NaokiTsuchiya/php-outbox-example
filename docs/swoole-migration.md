# OutboxPump の Swoole コルーチン移行指示書

## 目的

phpredis の `subscribe()` がブロッキングであるため、現状は SUBSCRIBE のタイムアウトをポーリングとして兼用し、タイムアウトごとに再接続が発生する。
Swoole のコルーチンと Channel を導入し、SUBSCRIBE とポーリングを独立したコルーチンに分離することで、この問題を解消する。

## 現状の構造

```
while (true) {
    relay();
    subscribe();  ← ブロッキング。タイムアウト時に例外 → 再接続
}
```

- SUBSCRIBE がタイムアウト兼ポーリングを担い、タイムアウトごとに再接続が必要
- タイムアウトと接続エラーが同じ `\RedisException` で区別が `str_contains` 依存
- `relay()` が SUBSCRIBE のブロッキングと結合している

## 移行後の構造

```
Channel（wake-up シグナル）

コルーチン1: Redis SUBSCRIBE → Channel に push
コルーチン2: Timer → Channel に push
メイン:      Channel から pop → relay()
```

### 各コルーチンの責務

| コルーチン | 役割 | Channel への push |
|-----------|------|------------------|
| SUBSCRIBE | Redis PUBLISH の受信 | 通知受信時 |
| Timer | 定期ポーリング（PUBLISH 失敗の安全網） | 一定間隔ごと |
| メイン | Channel から pop して relay() を実行 | - |

### 設計のポイント

- **Channel は wake-up シグナルのみ**: push する値に意味はない。relay() は常に DB の `lastId` 以降を読むため冪等
- **relay() の直列実行が保証される**: メインコルーチンが `pop → relay → pop → relay` と処理するため、並行実行は起きない
- **SUBSCRIBE のタイムアウト設定は不要**: タイムアウトなしでブロックし続ける。ポーリングは Timer コルーチンが担う
- **SUBSCRIBE の再接続は接続エラー時のみ**: タイムアウトによる再接続は不要になる

## 変更対象ファイル

### 1. `composer.json`

Swoole 拡張の要求を追加:

```json
{
  "require": {
    "ext-swoole": "*"
  }
}
```

### 2. `Dockerfile.pump`

Swoole 拡張をインストール:

```dockerfile
RUN pecl install swoole && docker-php-ext-enable swoole
```

### 3. `bin/pump.php`

エントリーポイントを `Swoole\Coroutine\run()` で囲む:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MyVendor\OutboxDemo\Bootstrap;
use MyVendor\OutboxDemo\Outbox\OutboxPump;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;

Coroutine\run(function () {
    $injector = Bootstrap::getInjector('cli-app');
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('OutboxPump worker starting');
    $pump = $injector->getInstance(OutboxPump::class);
    $pump->run();
});
```

### 4. `src/Outbox/OutboxPump.php`

`run()` メソッドを以下の構造に書き換える:

```php
public function run(): void
{
    $this->lastId = $this->position->get();
    $this->logger->info('OutboxPump started', ['last_id' => $this->lastId]);

    $channel = new \Swoole\Coroutine\Channel(1);

    // コルーチン1: Redis SUBSCRIBE
    Coroutine::create(function () use ($channel) {
        while (true) {
            try {
                $this->subscriber->subscribe([self::CHANNEL], function (\Redis $redis, string $ch, string $msg) use ($channel) {
                    $channel->push(true);
                    $redis->unsubscribe([self::CHANNEL]);
                });
            } catch (\RedisException $e) {
                $this->logger->error('Redis subscribe error, reconnecting', ['message' => $e->getMessage()]);
                Coroutine::sleep(1);
                $this->subscriber->connect(
                    $this->subscriber->getHost(),
                    $this->subscriber->getPort()
                );
            }
        }
    });

    // コルーチン2: 定期ポーリング
    Coroutine::create(function () use ($channel) {
        while (true) {
            Coroutine::sleep($this->pollIntervalSec);
            $channel->push(true);
        }
    });

    // メイン: Channel から pop して relay
    $this->relay(); // 起動時キャッチアップ
    while (true) {
        $channel->pop();
        try {
            $this->relay();
        } catch (\Throwable $e) {
            $this->logger->error('Relay failed', ['message' => $e->getMessage()]);
            Coroutine::sleep(1);
        }
    }
}
```

#### コンストラクタの変更

- `$pollTimeoutSec` → `$pollIntervalSec` にリネーム（意味がタイムアウトからインターバルに変わるため）
- `OPT_READ_TIMEOUT` の設定は削除（SUBSCRIBE にタイムアウトを設定しない）

### 5. `src/Module/AppModule.php`

変更なし。Redis のバインディングはそのまま使用する。

### 6. `src/Outbox/Subscriber.php`

変更なし。

## 変更しないもの

- **Redis PUBLISH/SUBSCRIBE の仕組み自体**: Web app と Pump は別プロセスなので、プロセス間通知には引き続き Redis を使用する
- **OutboxSender**: PUBLISH のロジックは変更なし
- **relay() の内部ロジック**: DB からの読み取り・Consumer への配信・position 更新はそのまま
- **ConsumerInterface / HttpConsumer**: 変更なし
- **ConsumedPositionRepository**: 変更なし

## E5 の改善効果

移行後、E5 のエラーハンドリングは以下のように改善される:

| 項目 | 移行前 | 移行後 |
|------|--------|--------|
| タイムアウト | `\RedisException` → `str_contains` で判別 → 再接続 | 発生しない（タイムアウト設定なし） |
| 接続エラー | `\RedisException` → `str_contains` で判別 → 再接続 | `\RedisException` → 再接続（本当のエラーのみ） |
| ポーリング | SUBSCRIBE タイムアウトで兼用 | Timer コルーチンが独立して担当 |
| 再接続頻度 | タイムアウトごと（デフォルト10秒） | 接続エラー時のみ |

## 注意事項

- Swoole の `Coroutine::sleep()` はコルーチンを yield するだけで、プロセスをブロックしない
- phpredis の `subscribe()` は Swoole コルーチン環境でもブロッキングとして動作する。ただしコルーチン1 内でブロックしても、コルーチン2 とメインには影響しない
- Swoole の Hook（`Swoole\Runtime::enableCoroutine()`）を有効にすると、phpredis のソケット操作がコルーチン対応になる可能性がある。動作確認が必要
