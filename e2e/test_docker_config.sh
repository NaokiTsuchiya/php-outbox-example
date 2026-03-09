#!/bin/bash
# Docker 設定ファイルの正確性を検証するテスト
# - 実装前: Dockerfile に設定が欠落 / .dockerignore が存在しない → FAIL
# - 実装後: すべての設定が揃う → PASS
set -uo pipefail

PASS=0
FAIL=0
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

ok() { echo "  ✓ $1"; PASS=$((PASS+1)); }
ng() { echo "  ✗ $1"; FAIL=$((FAIL+1)); }

echo "=== Docker Config Test ==="
echo ""

# ── [1] producer/Dockerfile: variables_order = EGPCS ──
echo "[1] producer/Dockerfile に variables_order = EGPCS が設定されているか"
PRODUCER_DOCKERFILE="$ROOT/components/producer/Dockerfile"

if [ ! -f "$PRODUCER_DOCKERFILE" ]; then
  ng "producer/Dockerfile が存在しない"
else
  grep -q "variables_order = EGPCS" "$PRODUCER_DOCKERFILE" \
    && ok "variables_order = EGPCS が設定されている" \
    || ng "variables_order = EGPCS が設定されていない（\$_ENV に環境変数が展開されない）"
fi

# ── [2] producer/Dockerfile: clear_env = no ──
echo "[2] producer/Dockerfile に clear_env = no が設定されているか"
if [ ! -f "$PRODUCER_DOCKERFILE" ]; then
  ng "producer/Dockerfile が存在しない"
else
  grep -q "clear_env" "$PRODUCER_DOCKERFILE" \
    && ok "clear_env の設定が含まれている" \
    || ng "clear_env = no が設定されていない（php-fpm がワーカー生成時に環境変数をクリアする）"
fi

# ── [3] producer/.dockerignore: app/vendor/ が除外されているか ──
echo "[3] producer/.dockerignore に app/vendor/ の除外が設定されているか"
PRODUCER_DOCKERIGNORE="$ROOT/components/producer/.dockerignore"

if [ ! -f "$PRODUCER_DOCKERIGNORE" ]; then
  ng "producer/.dockerignore が存在しない（ローカル vendor/ が Docker ビルドに混入する）"
else
  grep -q "app/vendor/" "$PRODUCER_DOCKERIGNORE" \
    && ok "app/vendor/ が .dockerignore に設定されている" \
    || ng "app/vendor/ が .dockerignore に設定されていない"
fi

# ── [4] pump/.dockerignore: app/vendor/ が除外されているか ──
echo "[4] pump/.dockerignore に app/vendor/ の除外が設定されているか"
PUMP_DOCKERIGNORE="$ROOT/components/pump/.dockerignore"

if [ ! -f "$PUMP_DOCKERIGNORE" ]; then
  ng "pump/.dockerignore が存在しない（ローカル vendor/ が Docker ビルドに混入し SqsClient の autoload が壊れる）"
else
  grep -q "app/vendor/" "$PUMP_DOCKERIGNORE" \
    && ok "app/vendor/ が .dockerignore に設定されている" \
    || ng "app/vendor/ が .dockerignore に設定されていない"
fi

# ── [5] consumer/.dockerignore: app/vendor/ が除外されているか ──
echo "[5] consumer/.dockerignore に app/vendor/ の除外が設定されているか"
CONSUMER_DOCKERIGNORE="$ROOT/components/consumer/.dockerignore"

if [ ! -f "$CONSUMER_DOCKERIGNORE" ]; then
  ng "consumer/.dockerignore が存在しない（ローカルの aura/sql 5.0.3 が Docker の 6.0.1 を上書きし PHP 8.4 互換エラーが発生する）"
else
  grep -q "app/vendor/" "$CONSUMER_DOCKERIGNORE" \
    && ok "app/vendor/ が .dockerignore に設定されている" \
    || ng "app/vendor/ が .dockerignore に設定されていない"
fi

# ── [6] pump/Dockerfile と consumer/Dockerfile には variables_order = EGPCS が既存であること ──
echo "[6] pump/Dockerfile と consumer/Dockerfile に variables_order = EGPCS が設定されているか（既存確認）"
for COMPONENT in pump consumer; do
  DF="$ROOT/components/$COMPONENT/Dockerfile"
  if [ ! -f "$DF" ]; then
    ng "$COMPONENT/Dockerfile が存在しない"
  else
    grep -q "variables_order = EGPCS" "$DF" \
      && ok "$COMPONENT/Dockerfile: variables_order = EGPCS あり" \
      || ng "$COMPONENT/Dockerfile: variables_order = EGPCS なし"
  fi
done

# ── 結果 ──
echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
