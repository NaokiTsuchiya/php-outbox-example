# プロジェクト構造変更 指示書

## 概要

現在のモノリシックな構成を、3つの独立コンポーネント（producer / pump / consumer）に分割する。

### 設計方針

- **Pump は BEAR.Sunday 非依存**: Ray.Di + aura-sql のみで構成する
- **共有コードは持たない**: 各コンポーネントが独立した composer パッケージ。共通定数（チャネル名等）は環境変数で渡す
- **namespace は各コンポーネントで変更**: Producer / Pump / Consumer それぞれ固有の namespace を使う

### namespace 設計

| コンポーネント | namespace |
|--------------|-----------|
| Producer | `MyVendor\OutboxProducer\` |
| Pump | `MyVendor\OutboxPump\` |
| Consumer | namespace 不要（単一ファイル） |

---

## 現在の構造

```
(root)
  bin/pump.php
  public/index.php
  consumer/server.php
  src/
    Bootstrap.php              ← BEAR.Sunday ブートストラップ
    FlakeId.php                ← ID生成
    Module/
      App.php                  ← BEAR.Sunday App クラス
      AppModule.php            ← DI 設定（全バインディング混在）
      RedisProvider.php        ← Redis 接続
    Outbox/
      OutboxChannel.php        ← チャネル名定数
      OutboxSenderInterface.php
      OutboxSender.php         ← DB INSERT + Redis PUBLISH
      OutboxPump.php           ← Pump 本体
      Subscriber.php           ← Qualifier アトリビュート
      ConsumerInterface.php
      HttpConsumer.php         ← HTTP 配信
      ConsumedPositionRepository.php
    Resource/App/
      Orders.php               ← BEAR リソース
      OrderCommand.php         ← #[Transactional]
  tests/
  composer.json
  Dockerfile.app / .pump / .consumer
  compose.yaml
  nginx.conf
  sql/schema.sql
  alps/orders.json
```

## 目標構造

```
components/
  consumer/
    app/
      public/
        index.php
      composer.json
    Dockerfile
    README.md
  producer/
    app/
      public/
        index.php
      src/
        Bootstrap.php
        FlakeId.php
        Module/
          App.php
          AppModule.php
          RedisProvider.php
        Outbox/
          OutboxSenderInterface.php
          OutboxSender.php
        Resource/App/
          Orders.php
          OrderCommand.php
      tests/
      alps/
        orders.json
      composer.json
      phpunit.xml.dist
      README.md
    Dockerfile
    nginx.conf
  pump/
    app/
      bin/
        pump.php
      src/
        PumpModule.php
        RedisProvider.php
        OutboxPump.php
        Subscriber.php
        ConsumerInterface.php
        HttpConsumer.php
        ConsumedPositionRepository.php
      tests/
      composer.json
      phpunit.xml.dist
      README.md
    Dockerfile
e2e/
  test.sh
sql/
  schema.sql
docs/
compose.yaml
README.md
```

---

## Phase 1: Consumer の分離

最も単純なコンポーネント。依存なし。

### `components/consumer/app/public/index.php`

`consumer/server.php` をそのまま移動。変更不要。

### `components/consumer/app/composer.json`

```json
{
    "name": "myvendor/outbox-demo-consumer",
    "require": {
        "php": "^8.3"
    }
}
```

### `components/consumer/Dockerfile`

```dockerfile
FROM php:8.3-cli

WORKDIR /app
COPY app/public/index.php public/index.php

EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public", "public/index.php"]
```

---

## Phase 2: Producer の分離

BEAR.Sunday アプリ。注文 API + Outbox 書き込み。

### ファイル配置

| 移動先 (producer/app/) | 元ファイル | namespace 変更 |
|----------------------|----------|---------------|
| `public/index.php` | `public/index.php` | `MyVendor\OutboxProducer` に変更 |
| `src/Bootstrap.php` | `src/Bootstrap.php` | `MyVendor\OutboxProducer` に変更、`getInjector()` 削除 |
| `src/FlakeId.php` | `src/FlakeId.php` | `MyVendor\OutboxProducer` |
| `src/Module/App.php` | `src/Module/App.php` | `MyVendor\OutboxProducer\Module` |
| `src/Module/AppModule.php` | `src/Module/AppModule.php` | **大幅変更** (後述) |
| `src/Module/RedisProvider.php` | `src/Module/RedisProvider.php` | `MyVendor\OutboxProducer\Module` |
| `src/Outbox/OutboxSenderInterface.php` | `src/Outbox/OutboxSenderInterface.php` | `MyVendor\OutboxProducer\Outbox` |
| `src/Outbox/OutboxSender.php` | `src/Outbox/OutboxSender.php` | `MyVendor\OutboxProducer\Outbox` |
| `src/Resource/App/Orders.php` | `src/Resource/App/Orders.php` | `MyVendor\OutboxProducer\Resource\App` |
| `src/Resource/App/OrderCommand.php` | `src/Resource/App/OrderCommand.php` | `MyVendor\OutboxProducer\Resource\App` |
| `alps/orders.json` | `alps/orders.json` | - |

### Producer `AppModule.php` の変更

Pump 関連のバインディングをすべて削除。チャネル名は環境変数から注入。

```php
namespace MyVendor\OutboxProducer\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use MyVendor\OutboxProducer\Outbox\OutboxSender;
use MyVendor\OutboxProducer\Outbox\OutboxSenderInterface;
use MyVendor\OutboxProducer\Resource\App\OrderCommand;
use Ray\AuraSqlModule\AuraSqlModule;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        // DB
        $this->install(new AuraSqlModule(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'] ?? 'db',
                $_ENV['DB_NAME'] ?? 'outbox_demo'
            ),
            $_ENV['DB_USER'] ?? 'app',
            $_ENV['DB_PASSWORD'] ?? 'secret',
        ));

        // Redis
        $this->bind(\Redis::class)->toProvider(RedisProvider::class);

        // Outbox（チャネル名を環境変数で注入）
        $this->bind()->annotatedWith('outbox_channel')
            ->toInstance($_ENV['OUTBOX_CHANNEL'] ?? 'outbox:notify');
        $this->bind(OutboxSenderInterface::class)->to(OutboxSender::class);
        $this->bind(OrderCommand::class);

        $this->install(new PackageModule());
    }
}
```

### Producer `OutboxSender.php` の変更

`OutboxChannel::NOTIFY` 定数の代わりに `#[Named('outbox_channel')]` で注入。

```php
namespace MyVendor\OutboxProducer\Outbox;

use Aura\Sql\ExtendedPdoInterface;
use MyVendor\OutboxProducer\FlakeId;
use Ray\Di\Di\Named;

class OutboxSender implements OutboxSenderInterface
{
    public function __construct(
        private ExtendedPdoInterface $pdo,
        private \Redis $redis,
        #[Named('outbox_channel')] private string $channel,
    ) {}

    public function send(string $type, array $payload): void
    {
        $id = FlakeId::generate();
        $this->pdo->perform(
            'INSERT INTO produced_zero (id, type, message) VALUES (:id, :type, :message)',
            ['id' => $id, 'type' => $type, 'message' => json_encode($payload)]
        );
    }

    public function notify(): void
    {
        $this->redis->publish($this->channel, 'notify');
    }
}
```

### Producer `Bootstrap.php` の変更

`getInjector()` メソッドを削除（Pump でのみ使用されていた）。

```php
namespace MyVendor\OutboxProducer;

use BEAR\Package\Injector;
// ... (残りは同じ、__NAMESPACE__ が変わるだけ)
```

### Producer `composer.json`

```json
{
    "name": "myvendor/outbox-demo-producer",
    "require": {
        "php": "^8.3",
        "bear/package": "^1.13",
        "ray/aura-sql-module": "^1.13",
        "monolog/monolog": "^3.0",
        "doctrine/cache": "^1.0",
        "koriym/param-reader": "^1.1",
        "nikic/php-parser": "^5.7",
        "koriym/attributes": "^1.0",
        "ext-redis": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\OutboxProducer\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "MyVendor\\OutboxProducer\\": "tests/"
        }
    },
    "extra": {
        "bear-app": {
            "name": "MyVendor\\OutboxProducer"
        }
    }
}
```

注: `ext-swoole` は不要（Producer は Redis PUBLISH のみ）。

### Producer Dockerfile (`components/producer/Dockerfile`)

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .
```

### `components/producer/nginx.conf`

`nginx.conf` をそのまま移動。変更不要。

---

## Phase 3: Pump の分離（BEAR.Sunday 非依存）

Ray.Di + aura-sql のみで構成。フラットな `src/` 構造。

### ファイル配置

| 移動先 (pump/app/) | 元ファイル | 変更内容 |
|-------------------|----------|---------|
| `bin/pump.php` | `bin/pump.php` | **書き直し**: `Bootstrap::getInjector()` → `Ray\Di\Injector` 直接生成 |
| `src/PumpModule.php` | `src/Module/AppModule.php` | **書き直し**: `AbstractAppModule` → `Ray\Di\AbstractModule` |
| `src/RedisProvider.php` | `src/Module/RedisProvider.php` | namespace 変更のみ |
| `src/OutboxPump.php` | `src/Outbox/OutboxPump.php` | namespace 変更 + チャネル名を環境変数注入 |
| `src/Subscriber.php` | `src/Outbox/Subscriber.php` | namespace 変更 |
| `src/ConsumerInterface.php` | `src/Outbox/ConsumerInterface.php` | namespace 変更 |
| `src/HttpConsumer.php` | `src/Outbox/HttpConsumer.php` | namespace 変更 |
| `src/ConsumedPositionRepository.php` | `src/Outbox/ConsumedPositionRepository.php` | namespace 変更 |

### Pump `bin/pump.php` の書き直し

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use MyVendor\OutboxPump\PumpModule;
use MyVendor\OutboxPump\OutboxPump;
use Psr\Log\LoggerInterface;
use Ray\Di\Injector;
use Swoole\Coroutine;

Coroutine\run(function () {
    $injector = new Injector(new PumpModule());
    $logger = $injector->getInstance(LoggerInterface::class);
    $logger->info('OutboxPump worker starting');
    $pump = $injector->getInstance(OutboxPump::class);
    $pump->run();
});
```

### Pump `PumpModule.php` の新規作成

```php
<?php

declare(strict_types=1);

namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdo;
use Aura\Sql\ExtendedPdoInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

class PumpModule extends AbstractModule
{
    protected function configure(): void
    {
        // DB
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'db',
            $_ENV['DB_NAME'] ?? 'outbox_demo'
        );
        $this->bind(ExtendedPdoInterface::class)
            ->toInstance(new ExtendedPdo(
                $dsn,
                $_ENV['DB_USER'] ?? 'app',
                $_ENV['DB_PASSWORD'] ?? 'secret'
            ));

        // Redis（通常用: Singleton）
        $this->bind(\Redis::class)
            ->toProvider(RedisProvider::class)
            ->in(Scope::SINGLETON);

        // Redis（SUBSCRIBE用: 都度新規接続）
        $this->bind(\Redis::class)
            ->annotatedWith(Subscriber::class)
            ->toProvider(RedisProvider::class);

        // Outbox チャネル名（環境変数）
        $this->bind()->annotatedWith('outbox_channel')
            ->toInstance($_ENV['OUTBOX_CHANNEL'] ?? 'outbox:notify');

        // Consumer エンドポイント
        $this->bind()->annotatedWith('consumer_endpoint')
            ->toInstance($_ENV['CONSUMER_ENDPOINT'] ?? 'http://consumer:8081');

        $this->bind(ConsumerInterface::class)->to(HttpConsumer::class);

        // Logger
        $this->bind(LoggerInterface::class)->toInstance(
            new Logger('pump', [new StreamHandler('php://stderr')])
        );
    }
}
```

### Pump `OutboxPump.php` の変更

`OutboxChannel::NOTIFY` → `#[Named('outbox_channel')]` で注入。

```php
namespace MyVendor\OutboxPump;

use Aura\Sql\ExtendedPdoInterface;
use Psr\Log\LoggerInterface;
use Ray\Di\Di\Named;
use Swoole\Coroutine;

class OutboxPump
{
    private string $lastId;

    public function __construct(
        private ExtendedPdoInterface $pdo,
        #[Subscriber] private \Redis $subscriber,
        private ConsumerInterface $consumer,
        private ConsumedPositionRepository $position,
        private LoggerInterface $logger,
        #[Named('outbox_channel')] private string $channel,
        private int $batchSize = 10,
        private int $pollIntervalSec = 10,
    ) {}

    public function run(): void
    {
        $this->lastId = $this->position->get();
        $this->logger->info('OutboxPump started', ['last_id' => $this->lastId]);

        $swooleChannel = new \Swoole\Coroutine\Channel(1);

        // コルーチン1: Redis SUBSCRIBE
        Coroutine::create(function () use ($swooleChannel) {
            while (true) {
                try {
                    $this->subscriber->subscribe([$this->channel], function (\Redis $redis, string $ch, string $msg) use ($swooleChannel) {
                        $swooleChannel->push(true);
                        $redis->unsubscribe([$this->channel]);
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
        Coroutine::create(function () use ($swooleChannel) {
            while (true) {
                Coroutine::sleep($this->pollIntervalSec);
                $swooleChannel->push(true);
            }
        });

        // メイン: relay
        $this->relay();
        while (true) {
            $swooleChannel->pop();
            try {
                $this->relay();
            } catch (\Throwable $e) {
                $this->logger->error('Relay failed', ['message' => $e->getMessage()]);
                Coroutine::sleep(1);
            }
        }
    }

    // relay() は変更なし（namespace 変更のみ）
}
```

### Pump `composer.json`

```json
{
    "name": "myvendor/outbox-demo-pump",
    "require": {
        "php": "^8.3",
        "ray/di": "^2.17",
        "aura/sql": "^5.0",
        "monolog/monolog": "^3.0",
        "ext-swoole": "*",
        "ext-redis": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\OutboxPump\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "MyVendor\\OutboxPump\\": "tests/"
        }
    }
}
```

注: `bear/package` 不要。`ray/aura-sql-module` も不要（`#[Transactional]` AOP を使わないため、`aura/sql` 直接依存で十分）。

### Pump Dockerfile (`components/pump/Dockerfile`)

```dockerfile
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install redis && docker-php-ext-enable redis \
    && pecl install swoole && docker-php-ext-enable swoole \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY app/ .

CMD ["php", "bin/pump.php"]
```

---

## Phase 4: compose.yaml の更新

```yaml
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: outbox_demo
      MYSQL_USER: app
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./sql/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-proot"]
      interval: 5s
      timeout: 3s
      retries: 10

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  app:
    build:
      context: components/producer
      dockerfile: Dockerfile
    environment:
      DB_HOST: db
      DB_NAME: outbox_demo
      DB_USER: app
      DB_PASSWORD: secret
      REDIS_HOST: redis
      OUTBOX_CHANNEL: "outbox:notify"
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy

  nginx:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./components/producer/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  pump:
    build:
      context: components/pump
      dockerfile: Dockerfile
    environment:
      DB_HOST: db
      DB_NAME: outbox_demo
      DB_USER: app
      DB_PASSWORD: secret
      REDIS_HOST: redis
      OUTBOX_CHANNEL: "outbox:notify"
      CONSUMER_ENDPOINT: http://consumer:8081
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
      consumer:
        condition: service_started
    restart: unless-stopped

  consumer:
    build:
      context: components/consumer
      dockerfile: Dockerfile
    ports:
      - "8081:8081"

volumes:
  db_data:
```

変更点:
- `OUTBOX_CHANNEL` 環境変数を app / pump に追加（共有定数の代替）
- `build.context` を各コンポーネントディレクトリに変更
- ソースマウント（`- .:/app`）を削除
- nginx の設定ファイルパスを変更

---

## Phase 5: その他のファイル移動

| 元の場所 | 移動先                          | 備考 |
|---------|------------------------------|------|
| `test.sh` | `e2e/test.sh`                | |
| `nginx.conf` | `etc/nginx.conf`             | |
| `sql/schema.sql` | `sql/schema.sql`（root に残す）   | 複数コンポーネントが共有する DB スキーマ |
| `alps/orders.json` | `alps/orders.json`(root に残す) | |
| `docs/` | `docs/`（root に残す）            | |
| `.claude/` | `.claude/`（root に残す）         | |

---

## Phase 6: テストの分割

### Producer テスト (`components/producer/app/tests/`)

- `tests/Outbox/OutboxChannelTest.php` → 削除（`OutboxChannel` 定数クラス自体が廃止されるため）
  - 代わりに `OutboxSender` がチャネル名を正しく使うことをテストに含める
- `tests/bootstrap.php` → Producer 用に調整（namespace 変更を反映）

### Pump テスト (`components/pump/app/tests/`)

- `tests/Outbox/OutboxPumpTest.php` → `tests/OutboxPumpTest.php` に移動（フラット構造）
- `tests/Stub/redis_stub.php` → 必要に応じて移動
- `tests/bootstrap.php` → Pump 用に新規作成

### E2E テスト (`e2e/test.sh`)

- root から `bash e2e/test.sh` で実行（`docker compose` が root の `compose.yaml` を参照）
- 内容の変更は不要

---

## Phase 7: 旧ファイルの削除

すべての動作確認後に削除するファイル:

```
bin/                     ← pump に移動済み
public/                  ← producer に移動済み
consumer/                ← consumer に移動済み
src/                     ← producer / pump に分割済み
tests/                   ← producer / pump に分割済み
composer.json            ← 各コンポーネントに分割済み
composer.lock
vendor/
Dockerfile.app           ← components/producer/Dockerfile に移動済み
Dockerfile.pump          ← components/pump/Dockerfile に移動済み
Dockerfile.consumer      ← components/consumer/Dockerfile に移動済み
nginx.conf               ← components/producer/nginx.conf に移動済み
alps/                    ← components/producer/app/alps/ に移動済み
phpunit.xml.dist         ← 各コンポーネントに分割済み
test.sh                  ← e2e/test.sh に移動済み
var/                     ← BEAR.Sunday キャッシュ（不要）
```

---

## 実行順序チェックリスト

### Consumer
1. [ ] `components/consumer/app/public/` ディレクトリ作成
2. [ ] `consumer/server.php` → `components/consumer/app/public/index.php` に移動
3. [ ] `components/consumer/app/composer.json` 作成
4. [ ] `components/consumer/Dockerfile` 作成
5. [ ] `components/consumer/README.md` 作成

### Producer
6. [ ] `components/producer/app/src/` 以下のディレクトリ構造作成
7. [ ] Producer 関連の全ソースファイルを移動し namespace を `MyVendor\OutboxProducer` に変更
8. [ ] `OutboxSender` を変更: `OutboxChannel::NOTIFY` → `#[Named('outbox_channel')]` 注入
9. [ ] `AppModule` を変更: Pump 関連バインディング削除、`outbox_channel` バインディング追加
10. [ ] `Bootstrap.php` から `getInjector()` を削除、namespace 変更
11. [ ] `public/index.php` の namespace 変更
12. [ ] `components/producer/app/composer.json` 作成（`ext-swoole` なし）
13. [ ] `components/producer/app/phpunit.xml.dist` 作成
14. [ ] `components/producer/Dockerfile` 作成
15. [ ] `nginx.conf` → `components/producer/nginx.conf` に移動
16. [ ] `alps/` → `components/producer/app/alps/` に移動
17. [ ] テストファイル移動・namespace 変更

### Pump
18. [ ] `components/pump/app/src/` ディレクトリ作成
19. [ ] Pump 関連ソースを移動し namespace を `MyVendor\OutboxPump` に変更（フラット構造）
20. [ ] `PumpModule.php` 新規作成（`Ray\Di\AbstractModule` ベース）
21. [ ] `bin/pump.php` 書き直し（`Ray\Di\Injector` 直接使用）
22. [ ] `OutboxPump` を変更: `OutboxChannel::NOTIFY` → `#[Named('outbox_channel')]` 注入
23. [ ] `components/pump/app/composer.json` 作成（`bear/package` なし）
24. [ ] `components/pump/app/phpunit.xml.dist` 作成
25. [ ] `components/pump/Dockerfile` 作成
26. [ ] テストファイル移動・namespace 変更

### 統合
27. [ ] `compose.yaml` 更新（`OUTBOX_CHANNEL` 環境変数追加）
28. [ ] `test.sh` → `e2e/test.sh` に移動
29. [ ] `docker compose up --build` で動作確認
30. [ ] `bash e2e/test.sh` で E2E テスト実行
31. [ ] 旧ファイル削除
32. [ ] `.claude/CLAUDE.md` を新構造に合わせて更新