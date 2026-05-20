# paysera-assignment

Create a secure API for transferring funds between accounts. This system will demonstrate your ability to develop scalable and reliable financial applications that can handle high loads.

## Local setup

From the repository root:

```bash
docker compose up -d
```

API: http://localhost:8080  
RabbitMQ management UI: http://localhost:15672 (user `paysera`, password `paysera`)

### Messenger / RabbitMQ

Async messages use RabbitMQ. The default DSN in `backend-api/.env` uses host `rabbitmq` (Docker network).

- **Inside Docker** (recommended): run console commands in the `php` service. `MESSENGER_TRANSPORT_DSN` is set in `docker-compose.yml` so the broker host is always `rabbitmq`.

  ```bash
  docker compose exec php php bin/console messenger:setup-transports
  docker compose exec php composer messenger:consume:async
  ```

- **On the host** (WSL): copy `backend-api/.env.local.example` to `backend-api/.env.local` so the DSN points at `127.0.0.1:5672`. You also need the PHP `amqp` extension (`php -m | grep amqp`).

### Troubleshooting AMQP

1. Ensure RabbitMQ is up: `docker compose ps rabbitmq`
2. Check health: `docker compose exec rabbitmq rabbitmq-diagnostics ping`
3. Match DSN host to where PHP runs: `rabbitmq` in containers, `127.0.0.1` on the host

## Testing

PHPUnit runs inside the `php` container against an isolated `*_test` database and Lexik JWT keys under `test/fixtures/jwt/`.

```bash
# Generate JWT keys for the test environment (first time only)
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction --env=test

# Install dev dependencies (phpunit/phpunit, phpunit-bridge, browser-kit)
docker compose exec php composer update --no-interaction --dev

# Create/migrate test DB and run all suites
docker compose exec php composer test

# Run individual suites
docker compose exec php composer test:unit
docker compose exec php composer test:functional
```

**Suites**

| Suite | Path | Purpose |
|-------|------|---------|
| `unit` | `test/Unit/` | Fast tests with mocks (no HTTP, no real JWT) |
| `functional` | `test/Integration/Api/` | HTTP tests: login → Bearer token → protected routes |
| `integration` | `test/Integration/Repository/` | Doctrine repository tests with real DB |
