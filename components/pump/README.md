# Outbox Pump コンポーネント

Transactional Outbox パターンの Pump プロセス。BEAR.Sunday に依存しない独立コンポーネント。

Redis SUBSCRIBE で通知を受け取り、DB から未配信メッセージを順番に読み出して Consumer に配信する。

## 環境変数

| 変数名 | 説明 | デフォルト |
|--------|------|-----------|
| `DB_HOST` | MySQL ホスト | `db` |
| `DB_NAME` | データベース名 | `outbox_demo` |
| `DB_USER` | DB ユーザー | `app` |
| `DB_PASSWORD` | DB パスワード | `secret` |
| `REDIS_HOST` | Redis ホスト | `redis` |
| `REDIS_PORT` | Redis ポート | `6379` |
| `OUTBOX_CHANNEL` | Redis Pub/Sub チャネル名 | `outbox:notify` |
| `CONSUMER_ENDPOINT` | Consumer の HTTP エンドポイント | `http://consumer:8081` |

## 起動方法

```bash
# ローカル実行
php bin/pump.php

# Docker
docker build -t outbox-pump -f Dockerfile .
docker run --rm outbox-pump
```

## テスト

```bash
composer install
./vendor/bin/phpunit
```
