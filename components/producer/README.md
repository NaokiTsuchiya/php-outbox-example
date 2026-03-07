# Producer

Transactional Outbox パターンの Producer 側。BEAR.Sunday Web アプリケーションと Outbox Pump で構成される。

## サービス

- **app** — PHP-FPM で動作する BEAR.Sunday Web アプリケーション
- **pump** — Swoole CLI で動作する Outbox Pump（Redis Pub/Sub 経由でイベントを配信）

## エンドポイント

- `GET /orders` — 注文一覧を取得
- `POST /orders` — 注文を作成（Outbox テーブルにイベントを同時挿入）

## ビルド

```bash
docker build -f Dockerfile.app -t outbox-producer-app .
docker build -f Dockerfile.pump -t outbox-producer-pump .
```

## ポート

- 9000 (PHP-FPM)
