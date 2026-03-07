# Outbox Demo (BEAR.Sunday + Redis)

## Project
- PHP 8.3+ / BEAR.Sunday framework / Ray.Di DI container
- Namespace: `MyVendor\OutboxDemo` → `src/`
- Transactional Outbox pattern with Redis Pub/Sub

## Commands
- `docker compose up --build` - start all services
- `./vendor/bin/phpunit` - run tests (PHPUnit 11, bootstrap: `tests/bootstrap.php`)
- `php bin/app.php post /orders '{"userId":"u1","amount":3000}'` - CLI resource access

## Architecture
- `src/Module/AppModule.php` - Ray.Di bindings (shared Web/CLI)
- `src/Resource/App/` - BEAR.Sunday resources (REST endpoints)
- `src/Outbox/` - Outbox pattern: Sender (tx INSERT) → Pump (SUBSCRIBE + deliver) → Consumer (HTTP)
- `alps/` - ALPS profiles

## Style
- Japanese comments/docs are expected