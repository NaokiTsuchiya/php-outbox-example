# Consumer

イベント受信用モックサーバー。Outbox パターンの Consumer 側をシミュレートする。

## エンドポイント

- `POST /events` — イベントを受信し、標準エラー出力にログを出力する

## ビルドと実行

```bash
docker build -t outbox-consumer .
docker run -p 8081:8081 outbox-consumer
```

## ポート

- 8081
