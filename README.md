# Paysera Assignment — Fund Transfer API

Secure REST API for transferring funds between accounts. This is **not** a full payment system — it demonstrates production-oriented patterns: transaction integrity under concurrency, JWT authentication, Redis-backed caching, async side effects via RabbitMQ, structured errors, and Docker-based local development.

**Repository:** `https://github.com/YOUR_USERNAME/paysera-assignment` *(replace with your public GitHub URL before submission)*

---

## Table of contents

1. [Overview](#overview)
2. [Tech stack](#tech-stack)
3. [Architecture and design decisions](#architecture-and-design-decisions)
4. [API reference](#api-reference)
5. [Prerequisites](#prerequisites)
6. [Install and run](#install-and-run)
7. [Demo data](#demo-data)
8. [Testing and quality](#testing-and-quality)
9. [Security](#security)
10. [High-load considerations](#high-load-considerations)
11. [Future improvements](#future-improvements)
12. [Time spent](#time-spent)
13. [AI-assisted development](#ai-assisted-development)

---

## Overview

The application exposes a small, focused HTTP API built with **Symfony 7.4** and **PHP 8.3**. The core capability is **atomic fund transfers** between bank-style accounts stored in **MySQL**, with:

- **Pessimistic row locking** so concurrent transfers cannot corrupt balances
- **JWT authentication** so only the owner of the debit account can initiate a transfer
- **Redis** for Symfony and Doctrine cache pools (production profile)
- **RabbitMQ + Symfony Messenger** for post-transfer email notifications (decoupled from the request path)

Supporting endpoints include health check, login, and user profile lookup by ID.

---

## Tech stack


| Layer               | Technology                          |
| ------------------- | ----------------------------------- |
| Runtime             | PHP 8.3 (FPM)                       |
| Framework           | Symfony 7.4                         |
| Database            | MySQL 8                             |
| Cache               | Redis (`cache.app`)                 |
| Async messaging     | RabbitMQ + Symfony Messenger (AMQP) |
| Auth                | Lexik JWT Authentication Bundle     |
| HTTP front          | nginx                               |
| Local orchestration | Docker Compose                      |


Application code lives in `[backend-api/](backend-api/)`.

---

## Architecture

![System architecture](paysera-architecture.png)


### Data model


---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose v2
- **Optional (host PHP):** PHP 8.2+, Composer, `amqp` extension if running console on the host against local RabbitMQ

The recommended path is **everything inside Docker** (`php` service).

---

## Install and run

From the repository root:

```bash
# Start stack (PHP, nginx, MySQL, Redis, RabbitMQ)
docker compose up -d --build

# Install dependencies
docker compose exec php composer install

# Generate JWT keypair (dev; files go to backend-api/config/jwt/, gitignored)
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

# Apply schema
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Load demo users and accounts
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### Service URLs


| Service                | URL / port                                                                      |
| ---------------------- | ------------------------------------------------------------------------------- |
| API                    | [http://localhost:8080](http://localhost:8080)                                  |
| MySQL                  | `localhost:3307` (user `paysera` / password `paysera`, DB `paysera-assignment`) |
| Redis                  | `localhost:6379`                                                                |
| RabbitMQ AMQP          | `localhost:5672`                                                                |
| RabbitMQ management UI | [http://localhost:15672](http://localhost:15672) (`paysera` / `paysera`)        |


### Async worker (email notifications)

Transfers commit synchronously; emails are sent asynchronously.

```bash
docker compose exec php php bin/console messenger:setup-transports
docker compose exec php composer messenger:consume:async
```

**Messenger DSN**

- **Inside Docker (default):** `MESSENGER_TRANSPORT_DSN` is set in `[docker-compose.yml](docker-compose.yml)` to `amqp://paysera:paysera@rabbitmq:5672/%2f/messages`.
- **On the host (WSL):** copy `[backend-api/.env.local.example](backend-api/.env.local.example)` to `backend-api/.env.local` and use `127.0.0.1` instead of `rabbitmq`. Requires the PHP `amqp` extension.

**Troubleshooting AMQP**

1. `docker compose ps rabbitmq` — container running
2. `docker compose exec rabbitmq rabbitmq-diagnostics ping`
3. Match broker host to where PHP runs: `rabbitmq` in containers, `127.0.0.1` on the host

### Environment variables

See `[backend-api/.env](backend-api/.env)`. Important keys:


| Variable                                 | Purpose                                                                                  |
| ---------------------------------------- | ---------------------------------------------------------------------------------------- |
| `DATABASE_URL`                           | MySQL connection (Docker host: `mysql`)                                                  |
| `REDIS_URL`                              | Redis connection (Docker host: `redis`) — cache, rate limiting, and transfer idempotency |
| `IDEMPOTENCY_TTL_SECONDS`                | How long completed/failed idempotency records are kept (default `86400`)                 |
| `IDEMPOTENCY_PROCESSING_TIMEOUT_SECONDS` | When a `processing` record is considered stale and may be reclaimed (default `30`)       |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY`      | Paths to PEM files under `config/jwt/`                                                   |
| `MESSENGER_TRANSPORT_DSN`                | AMQP (set in Compose for `php` service)                                                  |
| `MAILER_DSN`                             | Default `null://null` (no real mail in dev)                                              |


---

## Demo data

Loaded by `[UserFixtures](backend-api/src/DataFixtures/UserFixtures.php)` and `[AccountFixtures](backend-api/src/DataFixtures/AccountFixtures.php)`. Password for all demo users: `**password`**.


| User           | Email                    | Accounts | Balance each | Currency |
| -------------- | ------------------------ | -------- | ------------ | -------- |
| Amit Sharma    | `mohangade118@gmail.com` | 2        | 100.0        | INR      |
| Priya Kulkarni | `mohangade08@gmail.com`  | 1        | 100.0        | INR      |


Account **IDs are assigned at fixture load** (typically `1`, `2` for user 1 and `3` for user 2). Query the database or use the SQL command in the [curl example](#example-full-transfer-with-curl) before calling the transfer endpoint.

To reset demo data:

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

---

## Testing and quality

### Automated tests (current status)

The project is structured for three PHPUnit suites (see `[backend-api/composer.json](backend-api/composer.json)` scripts), but `**backend-api/test/` is not yet populated** and `**phpunit.dist.xml` is not committed** in this repository. A clean clone cannot run `composer test` until those assets are added.

**Planned coverage** (when implemented):


| Suite         | Path                           | Purpose                                         |
| ------------- | ------------------------------ | ----------------------------------------------- |
| `unit`        | `test/Unit/`                   | Services, DTO validation, subscribers (mocked)  |
| `functional`  | `test/Integration/Api/`        | HTTP: login → JWT → protected routes, transfers |
| `integration` | `test/Integration/Repository/` | Doctrine repositories with real test DB         |


**Intended commands** (after `phpunit.dist.xml` and tests are added):

```bash
# One-time: JWT keys for test env
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction --env=test

docker compose exec php composer update --no-interaction --dev
docker compose exec php composer test
docker compose exec php composer test:unit
docker compose exec php composer test:functional
```

Test database name: `paysera-assignment_test` (Doctrine `dbname_suffix` in test env). Messenger uses `sync://` in tests so handlers run inline.

### Static analysis and style (available now)

```bash
docker compose exec php composer phpstan
docker compose exec php composer cs-check
docker compose exec php composer cs-fix   # apply fixes
```

---

## Security


| Control              | Implementation                                                                                                                                                                                                                                               |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Authentication**   | Stateless JWT on `^/api` firewall (`[security.yaml](backend-api/config/packages/security.yaml)`); `POST /api/login` with `json_login`                                                                                                                        |
| **Authorization**    | `IS_AUTHENTICATED_FULLY` for protected routes; `[TransactionService](backend-api/src/Services/TransactionService.php)` enforces from-account ownership                                                                                                       |
| **Input validation** | Symfony Validator constraints on `[CreateTransactionRequest](backend-api/src/Dto/CreateTransactionRequest.php)`                                                                                                                                              |
| **Login throttling** | 2 attempts / 15 minutes (Symfony `login_throttling`)                                                                                                                                                                                                         |
| **Rate limiting**    | Global API bucket (60/min) and stricter transfer bucket (5 burst, 10/min refill) in `[rate_limiter.yaml](backend-api/config/packages/rate_limiter.yaml)`; enforced by `[ApiRateLimitSubscriber](backend-api/src/EventSubscriber/ApiRateLimitSubscriber.php)` |
| **Logging**          | `transaction_transfer_requested` / `_succeeded` / `_failed` in controller; Monolog request context processor                                                                                                                                                 |


---

## AI-assisted development

**Tools used:** Cursor (Agent), and similar LLM assistants for reference and scaffolding.

**Used for:**

- Symfony project structure, Docker Compose, and service wiring
- Transfer service design (locking, transactions, messenger dispatch)
- Event subscribers (exceptions, rate limiting, request lifecycle)
- README and documentation structure
- Planned test layout (not yet committed)

**Example prompts:**

- *"Create a secure API for transferring funds between accounts using Symfony, MySQL, and Redis with pessimistic locking and JWT auth."*
- *"Document Docker setup, API curl examples, and architecture decisions for a Paysera assignment README."*
- *"Add ApiExceptionSubscriber for consistent JSON errors on /api routes."*

All generated code was reviewed and adjusted for correctness and ownership before submission. I can explain any part of the implementation in a technical interview.

---

## Project layout

```
paysera-assignment/
├── backend-api/          # Symfony application
│   ├── config/
│   ├── migrations/
│   ├── public/
│   ├── src/
│   └── ...
├── docs/
│   └── architecture/
│       ├── paysera-architecture.png   # System architecture (shown in README)
│       ├── system-components.drawio   # Editable diagram (draw.io)
│       └── system-components.svg      # Exported diagram
├── docker/
│   ├── nginx/
│   └── php/
├── docker-compose.yml
└── README.md
```

---

## License

Proprietary — submission for Paysera technical evaluation.