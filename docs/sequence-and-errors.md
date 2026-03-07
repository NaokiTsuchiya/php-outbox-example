# Outbox Pattern シーケンスとエラー発生箇所

## 全体シーケンス

```
Client          App(Orders)       OutboxSender        DB              Redis           Pump(OutboxPump)    HttpConsumer      Consumer
  |                 |                  |                |                |                  |                  |                |
  |-- POST /orders →|                  |                |                |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |--- BEGIN tx -----|--------------->|                |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |           [E1]  |-- INSERT orders ----------------->|                |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |-- send() ------->|                |                |                  |                  |                |
  |           [E2]  |                  |-- INSERT produced_zero ------->|                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |--- COMMIT tx ----|--------------->|                |                  |                  |                |
  |           [E3]  |                  |                |                |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |           [E4]  |                  |-- PUBLISH outbox:notify ------>|                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |<-- 201 --------|                  |                |                |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |                  |                |                |-- SUBSCRIBE ---->|                  |                |
  |                 |                  |                |                |   (通知 or       |                  |                |
  |                 |                  |                |                |    タイムアウト)  |                  |                |
  |                 |                  |                |           [E5] |                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |                  |           [E6] |<-- SELECT produced_zero ---------|                  |                |
  |                 |                  |                |-- rows ------>|                  |                  |                |
  |                 |                  |                |                |                  |                  |                |
  |                 |                  |                |                |           [E7]   |-- POST /events ->|                |
  |                 |                  |                |                |                  |                  |-- log event -->|
  |                 |                  |                |                |                  |<---- 200 --------|                |
  |                 |                  |                |                |                  |                  |                |
  |                 |                  |           [E8] |<-- UPSERT consumed_zero ---------|                  |                |
  |                 |                  |                |                |                  |                  |                |
```

## エラー発生箇所

### E1: INSERT orders 失敗

- **原因**: DB接続断、制約違反、ディスクフル等
- **影響**: トランザクション全体がロールバック。produced_zero への INSERT も行われない
- **データ整合性**: 問題なし（何も書き込まれない）

### E2: INSERT produced_zero 失敗

- **原因**: DB接続断、スキーマ不整合等
- **影響**: トランザクション全体がロールバック。orders への INSERT も取り消される
- **データ整合性**: 問題なし（何も書き込まれない）

### E3: COMMIT 失敗

- **原因**: DB接続断、デッドロック等
- **影響**: トランザクション全体がロールバック
- **データ整合性**: 問題なし

### E4: Redis PUBLISH 失敗

- **原因**: Redis接続断、Redis ダウン
- **影響**: Pump への即時通知が届かない
- **データ整合性**: **問題なし**。orders と produced_zero は既に COMMIT 済み。Pump の定期ポーリング（10秒）が安全網として機能し、メッセージは必ず配信される
- **現状の課題**: `OutboxSender::send()` 内で PUBLISH が例外を投げると、`#[Transactional]` の外側でのハンドリングに依存する。COMMIT 後の PUBLISH 失敗なのか、COMMIT 前なのかはフレームワークの `#[Transactional]` AOP の実装に依存する

### E5: Redis SUBSCRIBE タイムアウト / 接続エラー

- **原因**:
  - **タイムアウト**: `OPT_READ_TIMEOUT`（10秒）による正常な切断。`\RedisException` が発生し、メッセージは `"read error on connection"` を含む
  - **接続エラー**: Redis ダウン、ネットワーク障害等。同じく `\RedisException` だがメッセージが異なる
- **影響**: タイムアウトの場合はポーリングとして正常動作。接続エラーの場合は再接続が必要
- **データ整合性**: 問題なし（読み取り側のため）
- **現状の課題**: phpredis の `subscribe()` はタイムアウトも接続エラーも同じ `\RedisException` を投げる。メッセージ文字列の `str_contains` でしか判別できない

### E6: SELECT produced_zero 失敗

- **原因**: DB接続断、DB ダウン
- **影響**: 未配信メッセージの取得ができない。次回のループで再試行される
- **データ整合性**: 問題なし（読み取りのみで、produced_zero のデータは残っている）
- **現状の課題**: `relay()` 内で例外が投げられると、`run()` の `while(true)` ループ外に伝播してプロセスが終了する。`restart: unless-stopped` による再起動で回復するが、ログにスタックトレースが出力される

### E7: HTTP POST /events 失敗（Consumer への配信失敗）

- **原因**: Consumer ダウン、ネットワーク障害、タイムアウト（5秒）
- **影響**: `HttpConsumer::send()` が `RuntimeException` を投げる。`consumed_zero` の `last_id` は更新されないため、次回再試行時に同じメッセージから配信が再開される
- **データ整合性**: **at-least-once 配信**。Consumer 側が処理済みだがレスポンスが返る前に失敗した場合、同じメッセージが再配信される可能性がある
- **現状の課題**: E6 と同様、例外が `run()` のループ外に伝播してプロセスが終了する

### E8: UPSERT consumed_zero 失敗

- **原因**: DB接続断
- **影響**: Consumer への配信は成功しているが、`last_id` の更新に失敗。次回起動時に同じメッセージが再配信される
- **データ整合性**: **at-least-once 配信**。Consumer 側で冪等性の担保が必要

## 設計上の制約: phpredis の SUBSCRIBE はブロッキング

phpredis の `subscribe()` はブロッキングコールであり、制御が戻る手段は2つしかない:

1. **コールバック内で `unsubscribe()`** — PUBLISH 受信時
2. **`OPT_READ_TIMEOUT` による例外** — タイムアウト時

タイムアウト（2）は `\RedisException` を発生させ、接続が壊れるため再接続が必要になる。
ノンブロッキングな SUBSCRIBE API は phpredis に存在しないため、「通知を待ちながら定期的に relay() する」ことはできない。

このため、タイムアウトをポーリングの安全網として使う場合、都度再接続は避けられない。

### ポーリングの必要性

E4 の改善（PUBLISH を COMMIT 後に移動）により、race condition は解消される。
ポーリングが必要なのは「PUBLISH 自体が失敗した場合」のみだが、Redis が落ちていれば SUBSCRIBE 側も壊れているため、タイムアウトポーリングでも救えない。
PUBLISH 失敗が意味を持つのは Redis の瞬断（PUBLISH 時は落ちていたが SUBSCRIBE 時には復旧）という稀なケースに限られる。

### 設計の選択肢

| 方式 | 即時性 | PUBLISH失敗時 | 再接続 |
|------|--------|--------------|--------|
| SUBSCRIBE + 短いタイムアウト | ○ | 短時間で回復 | タイムアウトごと |
| SUBSCRIBE + 長いタイムアウト | ○ | 長めの遅延で回復 | タイムアウトごと |
| SUBSCRIBE タイムアウトなし | ○ | 次のPUBLISHまで滞留 | エラー時のみ |
| 純粋なポーリング（SUBSCRIBEなし） | △ | ポーリング間隔で回復 | 不要 |

## エラーハンドリングの改善ポイント

### 1. E4: PUBLISH を COMMIT 後に移動

現状、`OutboxSender::send()` 内の PUBLISH は `#[Transactional]` のスコープ内で実行されるため、COMMIT 前に Pump が起床して空振りする race condition がある。

PUBLISH を COMMIT 後に移動することで:
- Pump は通知受信時に必ずデータが見える
- PUBLISH 失敗時もトランザクションに影響しない
- タイムアウトポーリングの主な必要性がなくなる

### 2. E5: SUBSCRIBE のタイムアウト判別

phpredis の制約により `str_contains` での判別しかできない。E4 を修正しタイムアウトなしの SUBSCRIBE に変更すれば、`\RedisException` は本当の接続エラーのみになり、分類自体が不要になる。

### 3. E6, E7, E8: relay() 内のエラーハンドリング

現状、`relay()` 内の例外はプロセスを終了させる。`relay()` を try-catch で囲み、エラー時はログ出力と sleep 後に次のループに進む設計が考えられる。

```
while (true) {
    try {
        $this->relay();
    } catch (\Throwable $e) {
        $this->logger->error('Relay failed', ['message' => $e->getMessage()]);
        sleep(1);
    }
    // ... SUBSCRIBE ...
}
```
