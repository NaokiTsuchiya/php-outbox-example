# Transactional Outbox Demo

BEAR.Sunday + Swoole + Redis Pub/Sub による Transactional Outbox パターンの実装デモ。

## 概要

注文作成時にビジネスデータとイベントメッセージを**同一トランザクション**で DB に書き込み、別プロセス（Pump）が非同期で外部サービスへ配信する。メッセージブローカー不要で at-least-once 配信を実現する。

## アーキテクチャ

```
[Producer (BEAR.Sunday + PHP-FPM)]
  POST /orders
    OrderCommand#create() ← #[Transactional] AOP
      INSERT orders
      INSERT produced_zero  ← OutboxSender::send()（同一 tx 内）
    COMMIT
    Redis PUBLISH outbox:notify  ← OutboxSender::notify()（ベストエフォート通知）

[Pump (Swoole コルーチン / BEAR.Sunday 非依存)]
  Ray.Di で PumpModule を使用
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
| フレームワーク | BEAR.Sunday (PHP 8.3+) — Producer のみ |
| DI | Ray.Di（Producer: `AppModule` / Pump: `PumpModule`） |
| DB | MySQL 8.0 (AuraSql) |
| メッセージ通知 | Redis 7 Pub/Sub |
| Pump 非同期処理 | Swoole コルーチン |
| ID 生成 | Flake ID（タイムスタンプ + ランダム） |

## プロジェクト構成

プロジェクトは 3 つの独立コンポーネントに分割されている。共有コードは持たず、共通設定（チャネル名等）は環境変数で渡す。

```
components/
  consumer/                  イベント受信モックサーバー
    Dockerfile
    README.md
    app/
      public/index.php       PHP ビルトインサーバーのエントリポイント
      composer.json
  producer/                  BEAR.Sunday Web アプリ（注文 API + Outbox 書き込み）
    Dockerfile
    README.md
    composer.json
    phpunit.xml.dist
    bin/app.php              BEAR.Sunday CLI エントリ
    public/index.php         Web エントリ（hal-api-app コンテキスト）
    src/
      Bootstrap.php
      FlakeId.php
      Module/
        App.php              BEAR.Sunday App クラス
        AppModule.php        DI 設定
        RedisProvider.php    Redis 接続プロバイダ
      Outbox/
        OutboxSenderInterface.php
        OutboxSender.php     INSERT produced_zero + Redis PUBLISH
      Resource/App/
        Orders.php           注文リソース（GET/POST）
        OrderCommand.php     注文作成コマンド（#[Transactional]）
    tests/
  pump/                      Outbox Pump（BEAR.Sunday 非依存）
    Dockerfile
    README.md
    app/
      bin/pump.php           Swoole コルーチンで起動
      composer.json
      phpunit.xml.dist
      src/
        PumpModule.php       Ray.Di モジュール（AbstractModule ベース）
        RedisProvider.php
        OutboxPump.php       SUBSCRIBE + SELECT + 配信 + ACK
        Subscriber.php       SUBSCRIBE 用 Redis Qualifier
        ConsumerInterface.php
        HttpConsumer.php     HTTP POST で外部サービスへ配信
        ConsumedPositionRepository.php
      tests/
alps/
  orders.json                ALPS プロファイル
docs/
  restructure-plan.md        コンポーネント分割の設計書
  sequence-and-errors.md     シーケンス図・障害パターン
  swoole-migration.md        Swoole 移行メモ
e2e/
  test.sh                    E2E テストスクリプト
etc/
  nginx.conf                 Nginx 設定
sql/
  schema.sql                 DDL（orders / produced_zero / consumed_zero）
compose.yaml                 Docker Compose 定義
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
| `app` | Producer (PHP-FPM) | - |
| `pump` | Outbox Pump (Swoole) | - |
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
# Producer ユニットテスト
cd components/producer && ./vendor/bin/phpunit

# Pump ユニットテスト
cd components/pump/app && ./vendor/bin/phpunit

# E2E テスト（docker compose up 状態で実行）
bash e2e/test.sh
```

## ログ確認

```bash
docker compose logs -f pump      # Pump の配信ログ
docker compose logs -f consumer  # Consumer の受信ログ
```