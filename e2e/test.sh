#!/bin/bash
set -uo pipefail

BASE_URL="http://localhost:8080"
PASS=0
FAIL=0

ok() { echo "  ✓ $1"; PASS=$((PASS+1)); }
ng() { echo "  ✗ $1"; FAIL=$((FAIL+1)); }

echo "=== Outbox Demo E2E Test ==="
echo ""

# 1) GET /orders
echo "[1] GET /orders"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/orders")
[ "$STATUS" = "200" ] && ok "status 200" || ng "expected 200, got $STATUS"

# 2) POST /orders
echo "[2] POST /orders"
RESP=$(curl -s -w "\nHTTPCODE:%{http_code}" -X POST "$BASE_URL/orders" \
  -H 'Content-Type: application/json' \
  -d '{"userId":"test-user","amount":1234}')
STATUS=$(echo "$RESP" | grep "^HTTPCODE:" | cut -d: -f2)
[ "$STATUS" = "201" ] && ok "status 201" || ng "expected 201, got $STATUS"

ORDER_ID=$(echo "$RESP" | sed -n 's/.*"order_id": *"\([^"]*\)".*/\1/p')
[ -n "$ORDER_ID" ] && ok "order_id returned: $ORDER_ID" || ng "order_id missing"

# 3) GET /orders に注文が含まれるか
echo "[3] GET /orders (after create)"
BODY=$(curl -s "$BASE_URL/orders")
echo "$BODY" | grep -q "$ORDER_ID" && ok "order found in list" || ng "order not in list"

# 4) produced_zero にメッセージがあるか
echo "[4] produced_zero table"
PRODUCED=$(docker compose exec -T db mysql -uapp -psecret outbox_demo -N -e \
  "SELECT COUNT(*) FROM produced_zero WHERE message LIKE '%$ORDER_ID%';" 2>/dev/null | tr -d '[:space:]')
[ "$PRODUCED" -ge 1 ] 2>/dev/null && ok "outbox message exists" || ng "outbox message missing"

# 5) Pump 配信を待つ（最大20秒）
echo "[5] Pump delivery (waiting up to 20s...)"
OUTBOX_ID=$(docker compose exec -T db mysql -uapp -psecret outbox_demo -N -e \
  "SELECT id FROM produced_zero WHERE message LIKE '%$ORDER_ID%';" 2>/dev/null | tr -d '[:space:]')
DELIVERED=false
for i in $(seq 1 20); do
  LAST=$(docker compose exec -T db mysql -uapp -psecret outbox_demo -N -e \
    "SELECT last_id FROM consumed_zero WHERE producer_id='producer1';" 2>/dev/null | tr -d '[:space:]')
  if [ -n "$LAST" ] && [ -n "$OUTBOX_ID" ] && [[ ! "$LAST" < "$OUTBOX_ID" ]]; then
    DELIVERED=true
    break
  fi
  sleep 1
done
$DELIVERED && ok "message delivered by pump" || ng "message not delivered within 20s"

# 6) Consumer がイベントを処理したか（processed_events テーブル）
echo "[6] Consumer processed event"
PROCESSED=false
for i in $(seq 1 20); do
  COUNT=$(docker compose exec -T db mysql -uapp -psecret outbox_demo -N -e \
    "SELECT COUNT(*) FROM processed_events WHERE event_id='$OUTBOX_ID';" 2>/dev/null | tr -d '[:space:]')
  if [ "$COUNT" -ge 1 ] 2>/dev/null; then
    PROCESSED=true
    break
  fi
  sleep 1
done
$PROCESSED && ok "event processed by consumer" || ng "event not processed within 20s"

# 結果
echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
