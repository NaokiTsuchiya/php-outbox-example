# Outbox Demo (BEAR.Sunday + Redis)

## Project
- PHP 8.3+ / Transactional Outbox pattern with Redis Pub/Sub
- 3 independent components under `components/`
  - **Producer**: BEAR.Sunday app (`MyVendor\OutboxDemo`) — `components/producer/`
  - **Pump**: Swoole worker (`MyVendor\OutboxPump`) — `components/pump/app/`
  - **Consumer**: SQS ポーリングワーカー — `components/consumer/`

## Commands
- `docker compose up --build` - start all services
- `cd components/producer/app && ./vendor/bin/phpunit` - Producer tests (PHPUnit 11)
- `cd components/pump/app && ./vendor/bin/phpunit` - Pump tests (PHPUnit 11)
- `bash e2e/test.sh` - E2E test (requires docker compose up)
- `php components/producer/app/bin/app.php post /orders '{"userId":"u1","amount":3000}'` - CLI resource access

## Architecture
- `components/producer/app/src/Module/AppModule.php` - Ray.Di bindings (BEAR.Sunday)
- `components/producer/app/src/Resource/App/` - BEAR.Sunday resources (REST endpoints)
- `components/producer/app/src/Outbox/` - OutboxSender (tx INSERT + Redis PUBLISH)
- `components/pump/app/src/PumpModule.php` - Ray.Di bindings (standalone, no BEAR.Sunday)
- `components/pump/app/src/OutboxPump.php` - SUBSCRIBE + SELECT + deliver + ACK
- `alps/` - ALPS profiles
- `sql/schema.sql` - DDL (orders / produced_zero / consumed_zero)

## Style
- Japanese comments/docs are expected

## Workflow
- After completing work, always run: build → component tests → E2E test
  1. `docker compose up --build -d`
  2. `cd components/producer/app && ./vendor/bin/phpunit`
  3. `cd components/pump/app && ./vendor/bin/phpunit`
  4. `bash e2e/test.sh`

## Gotchas
- Local `composer install` requires `--ignore-platform-reqs` (ext-swoole, ext-redis are Docker-only)
- Producer needs `var/` directory (BEAR.Sunday cache); already included via `.gitkeep`