# Transactional Outbox Demo

BEAR.Sunday + Swoole + Redis Pub/Sub による Transactional Outbox パターンの実装デモ。

## 概要

注文作成時にビジネスデータとイベントメッセージを**同一トランザクション**で DB に書き込み、別プロセス（Pump）が非同期で外部サービスへ配信する。メッセージブローカー不要で at-least-once 配信を実現する。

## アーキテクチャ

```
[Web アプリ (BEAR.Sunday + PHP-FPM x N)]
  POST /orders
    OrderCommand#create() ← #[Transactional] AOP
      INSERT orders
      INSERT produced_zero  ← OutboxSender::send()（同一 tx 内）
    COMMIT
    Redis PUBLISH outbox:notify  ← OutboxSender::notify()（ベストエフォート通知）

[Pump (Swoole コルーチン x 1)]
  Ray.Di で AppModule を共有
  コルーチン 1: Redis SUBSCRIBE outbox:notify  ← 即時起床
  コルーチン 2: 10 秒タイムアウト              ← ポーリング（安全網）
  メインループ:
    SELECT produced_zero WHERE id > last_id ORDER BY id
    -> POST http://consumer:8081/events  ← HttpConsumer
    -> UPDATE consumed_zero（ACK）

[Consumer (PHP ビルトインサーバー)]
  POST /events を受信してログ出力（モック）
```

## 技術スタック

| 項目 | 技術 |
|------|------|
| フレームワーク | BEAR.Sunday (PHP 8.3+) |
| DI | Ray.Di (`AppModule` を Web/CLI で共有) |
| DB | MySQL 8.0 (AuraSql) |
| メッセージ通知 | Redis 7 Pub/Sub |
| Pump 非同期処理 | Swoole コルーチン |
| ID 生成 | Flake ID（タイムスタンプ + ランダム） |

## ファイル構成

```
src/
  Bootstrap.php              Web/CLI 共通ブートストラップ
  FlakeId.php                Flake ID 生成（時系列ソート可能）
  Module/
    AppModule.php            Ray.Di バインディング（Web/CLI 共有）
    App.php                  アプリケーションメタ
    RedisProvider.php        Redis 接続プロバイダ
  Outbox/
    OutboxSenderInterface    イベント送信インターフェース
    OutboxSender             INSERT produced_zero + Redis PUBLISH
    OutboxPump               Swoole コルーチンで SUBSCRIBE + SELECT + 配信 + ACK
    OutboxChannel            Redis チャンネル名定数
    Subscriber               SUBSCRIBE 用 Redis 接続の Qualifier
    ConsumerInterface        外部配信インターフェース
    HttpConsumer             HTTP POST で外部サービスへ配信
    ConsumedPositionRepository  consumed_zero の last_id 永続化
  Resource/App/
    Orders.php               注文リソース（GET/POST + ALPS Link）
    OrderCommand.php         注文作成コマンド（#[Transactional]）
alps/
  orders.json                ALPS プロファイル
bin/
  app.php                    BEAR.Sunday 標準 CLI エントリ
  pump.php                   OutboxPump ワーカー（Swoole コルーチン）
consumer/
  server.php                 モックコンシューマ（PHP ビルトインサーバー）
public/
  index.php                  Web エントリ（hal-api-app コンテキスト）
sql/
  schema.sql                 DDL（orders / produced_zero / consumed_zero）
docs/
  sequence-and-errors.md     シーケンス図・障害パターン
  swoole-migration.md        Swoole 移行メモ
```

## DB スキーマ

| テーブル | 役割 |
|----------|------|
| `orders` | 注文データ（ビジネステーブル） |
| `produced_zero` | Outbox メッセージ（tx 内で INSERT） |
| `consumed_zero` | 配信済み位置（last_id）の記録 |

## 起動

```bash
docker compose up --build
```

サービス一覧:

| サービス | 説明 | ポート |
|----------|------|--------|
| `nginx` | リバースプロキシ | 8080 |
| `app` | BEAR.Sunday (PHP-FPM) | - |
| `pump` | OutboxPump (Swoole) | - |
| `consumer` | モックコンシューマ | 8081 |
| `db` | MySQL 8.0 | 3306 |
| `redis` | Redis 7 | 6379 |

## リクエスト例

```bash
# 注文作成（HAL JSON レスポンス）
curl -X POST http://localhost:8080/orders \
  -H 'Content-Type: application/json' \
  -d '{"userId":"user-123","amount":5000}'

# 注文一覧
curl http://localhost:8080/orders
```

## CLI

```bash
# BEAR.Sunday 標準 CLI（Web と同じリソースクラスを使用）
php bin/app.php post /orders '{"userId":"user-1","amount":3000}'
php bin/app.php get /orders
```

## テスト

```bash
# ユニットテスト
./vendor/bin/phpunit

# E2E テスト（docker compose up 状態で実行）
./test.sh
```

## ログ確認

```bash
docker compose logs -f pump      # Pump の配信ログ
docker compose logs -f consumer  # Consumer の受信ログ
```
