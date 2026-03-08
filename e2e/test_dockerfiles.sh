#!/bin/bash
# Dockerfile 移行検証テスト
# Alpine Linux 3.23 + PHP 8.4 への移行が正しく実装されているかを確認する

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

PASS=0
FAIL=0

ok() { echo "  ✓ $1"; PASS=$((PASS+1)); }
ng() { echo "  ✗ $1"; FAIL=$((FAIL+1)); }

assert_contains() {
    local file="$1"
    local pattern="$2"
    local description="$3"
    if grep -qE "$pattern" "$file"; then
        ok "$description"
    else
        ng "$description (pattern: $pattern)"
    fi
}

assert_not_contains() {
    local file="$1"
    local pattern="$2"
    local description="$3"
    if ! grep -qE "$pattern" "$file"; then
        ok "$description"
    else
        ng "$description (unexpected pattern found: $pattern)"
    fi
}

echo "=== Dockerfile 移行検証テスト (Alpine 3.23 + PHP 8.4) ==="
echo ""

# ========== Producer Dockerfile ==========
PRODUCER_DOCKERFILE="$PROJECT_ROOT/components/producer/Dockerfile"
echo "[producer] $PRODUCER_DOCKERFILE"

# Given: Dockerfile が存在する
# When: ベースイメージを確認する
# Then: alpine:3.23 を使用している
assert_contains "$PRODUCER_DOCKERFILE" "^FROM alpine:3\.23$" "ベースイメージが alpine:3.23"

# Then: 旧ベースイメージ (php:8.3-*) を使用していない
assert_not_contains "$PRODUCER_DOCKERFILE" "FROM php:8\.3" "旧ベースイメージ (php:8.3-*) を使用していない"

# When: パッケージインストールを確認する
# Then: apk を使用している (apt-get は使用しない)
assert_contains "$PRODUCER_DOCKERFILE" "apk add" "apk でパッケージをインストールしている"
assert_not_contains "$PRODUCER_DOCKERFILE" "apt-get" "apt-get を使用していない"

# Then: pecl を使用していない
assert_not_contains "$PRODUCER_DOCKERFILE" "pecl install" "pecl install を使用していない"

# Then: PHP 8.4 パッケージがインストールされている
assert_contains "$PRODUCER_DOCKERFILE" "php84" "PHP 8.4 パッケージ (php84) を使用している"
assert_contains "$PRODUCER_DOCKERFILE" "php84-fpm" "php84-fpm をインストールしている"
assert_contains "$PRODUCER_DOCKERFILE" "php84-pdo_mysql" "php84-pdo_mysql をインストールしている"
assert_contains "$PRODUCER_DOCKERFILE" "php84-pecl-redis" "php84-pecl-redis をインストールしている"

# Then: php-fpm の listen アドレスが 0.0.0.0:9000 に設定されている (Docker間通信のため)
assert_contains "$PRODUCER_DOCKERFILE" "0\.0\.0\.0:9000" "php-fpm の listen アドレスを 0.0.0.0:9000 に設定している"

# Then: PHP-FPM をフォアグラウンドで起動する CMD が設定されている
assert_contains "$PRODUCER_DOCKERFILE" 'CMD \["php-fpm84".*"-F"\]' "CMD で php-fpm84 -F を指定している"

# Then: Composer が維持されている
assert_contains "$PRODUCER_DOCKERFILE" "COPY --from=composer:2" "Composer イメージからのコピーを維持している"

echo ""

# ========== Pump Dockerfile ==========
PUMP_DOCKERFILE="$PROJECT_ROOT/components/pump/Dockerfile"
echo "[pump] $PUMP_DOCKERFILE"

# Given: Dockerfile が存在する
# When: ベースイメージを確認する
# Then: alpine:3.23 を使用している
assert_contains "$PUMP_DOCKERFILE" "^FROM alpine:3\.23$" "ベースイメージが alpine:3.23"

# Then: 旧ベースイメージ (php:8.3-*) を使用していない
assert_not_contains "$PUMP_DOCKERFILE" "FROM php:8\.3" "旧ベースイメージ (php:8.3-*) を使用していない"

# When: パッケージインストールを確認する
# Then: apk を使用している (apt-get は使用しない)
assert_contains "$PUMP_DOCKERFILE" "apk add" "apk でパッケージをインストールしている"
assert_not_contains "$PUMP_DOCKERFILE" "apt-get" "apt-get を使用していない"

# Then: pecl を使用していない
assert_not_contains "$PUMP_DOCKERFILE" "pecl install" "pecl install を使用していない"

# Then: PHP 8.4 パッケージがインストールされている
assert_contains "$PUMP_DOCKERFILE" "php84" "PHP 8.4 パッケージ (php84) を使用している"
assert_contains "$PUMP_DOCKERFILE" "php84-pecl-redis" "php84-pecl-redis をインストールしている"
assert_contains "$PUMP_DOCKERFILE" "php84-pecl-swoole" "php84-pecl-swoole をインストールしている"
assert_contains "$PUMP_DOCKERFILE" "php84-pdo_mysql" "php84-pdo_mysql をインストールしている"
assert_contains "$PUMP_DOCKERFILE" "php84-simplexml" "php84-simplexml をインストールしている (aws/aws-sdk-php 要件)"

# Then: php84-fpm は不要 (CLI のみ)
assert_not_contains "$PUMP_DOCKERFILE" "php84-fpm" "php84-fpm を含まない (CLI コンポーネント)"

# Then: CMD が維持されている
assert_contains "$PUMP_DOCKERFILE" 'CMD \["php", "bin/pump\.php"\]' "CMD で php bin/pump.php を指定している"

# Then: Composer が維持されている
assert_contains "$PUMP_DOCKERFILE" "COPY --from=composer:2" "Composer イメージからのコピーを維持している"

echo ""

# ========== Consumer Dockerfile ==========
CONSUMER_DOCKERFILE="$PROJECT_ROOT/components/consumer/Dockerfile"
echo "[consumer] $CONSUMER_DOCKERFILE"

# Given: Dockerfile が存在する
# When: ベースイメージを確認する
# Then: alpine:3.23 を使用している
assert_contains "$CONSUMER_DOCKERFILE" "^FROM alpine:3\.23$" "ベースイメージが alpine:3.23"

# Then: 旧ベースイメージ (php:8.3-*) を使用していない
assert_not_contains "$CONSUMER_DOCKERFILE" "FROM php:8\.3" "旧ベースイメージ (php:8.3-*) を使用していない"

# When: パッケージインストールを確認する
# Then: apk を使用している (apt-get は使用しない)
assert_contains "$CONSUMER_DOCKERFILE" "apk add" "apk でパッケージをインストールしている"
assert_not_contains "$CONSUMER_DOCKERFILE" "apt-get" "apt-get を使用していない"

# Then: pecl を使用していない
assert_not_contains "$CONSUMER_DOCKERFILE" "pecl install" "pecl install を使用していない"

# Then: PHP 8.4 パッケージがインストールされている
assert_contains "$CONSUMER_DOCKERFILE" "php84" "PHP 8.4 パッケージ (php84) を使用している"
assert_contains "$CONSUMER_DOCKERFILE" "php84-pecl-swoole" "php84-pecl-swoole をインストールしている"
assert_contains "$CONSUMER_DOCKERFILE" "php84-pdo_mysql" "php84-pdo_mysql をインストールしている"
assert_contains "$CONSUMER_DOCKERFILE" "php84-simplexml" "php84-simplexml をインストールしている (aws/aws-sdk-php 要件)"
assert_contains "$CONSUMER_DOCKERFILE" "php84-fileinfo" "php84-fileinfo をインストールしている (bear/package 要件)"

# Then: redis 拡張は不要 (consumer は redis を使用しない)
assert_not_contains "$CONSUMER_DOCKERFILE" "php84-pecl-redis" "php84-pecl-redis を含まない (consumer は redis 不要)"

# Then: php84-fpm は不要 (CLI のみ)
assert_not_contains "$CONSUMER_DOCKERFILE" "php84-fpm" "php84-fpm を含まない (CLI コンポーネント)"

# Then: --ignore-platform-req=ext-swoole が除去されている (apk で swoole インストール済みのため不要)
assert_not_contains "$CONSUMER_DOCKERFILE" "\-\-ignore-platform-req=ext-swoole" "--ignore-platform-req=ext-swoole を使用していない"

# Then: CMD が維持されている
assert_contains "$CONSUMER_DOCKERFILE" 'CMD \["php", "bin/worker\.php"\]' "CMD で php bin/worker.php を指定している"

# Then: Composer が維持されている
assert_contains "$CONSUMER_DOCKERFILE" "COPY --from=composer:2" "Composer イメージからのコピーを維持している"

echo ""

# ========== 共通チェック ==========
echo "[共通] apk --no-cache の使用"
for dockerfile in "$PRODUCER_DOCKERFILE" "$PUMP_DOCKERFILE" "$CONSUMER_DOCKERFILE"; do
    component=$(basename "$(dirname "$dockerfile")")
    # Then: apk add --no-cache を使用している (キャッシュ残留防止)
    assert_contains "$dockerfile" "apk add --no-cache" "[$component] apk add --no-cache を使用している"
done

# 結果
echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
