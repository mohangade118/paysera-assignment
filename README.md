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

| Layer | Technology |
|-------|------------|
| Runtime | PHP 8.3 (FPM) |
| Framework | Symfony 7.4 |
| Database | MySQL 8 |
| Cache | Redis (`cache.app`) |
| Async messaging | RabbitMQ + Symfony Messenger (AMQP) |
| Auth | Lexik JWT Authentication Bundle |
| HTTP front | nginx |
| Local orchestration | Docker Compose |

Application code lives in [`backend-api/`](backend-api/).

---

## Architecture and design decisions

### System components

![System components diagram](docs/architecture/system-components.svg)

*Editable source: [`docs/architecture/system-components.drawio`](docs/architecture/system-components.drawio) — open with [diagrams.net](https://app.diagrams.net) or the [Draw.io Integration](https://marketplace.visualstudio.com/items?itemName=hediet.vscode-drawio) extension in VS Code/Cursor.*

**How to edit**

1. Open `docs/architecture/system-components.drawio` in [diagrams.net](https://app.diagrams.net) (File → Open from → Device) or in Cursor via the Draw.io extension.
2. Adjust boxes, containers, and labeled connectors as needed.
3. Export: *File → Export as → SVG* (or PNG at 2×) and overwrite `docs/architecture/system-components.svg` if you change the diagram.

### Key decisions

| Decision | Rationale |
|----------|-----------|
| **Thin controller, domain service** | [`TransactionController`](backend-api/src/Controller/Api/V1/TransactionController.php) maps and validates input via [`CreateTransactionRequest`](backend-api/src/Dto/CreateTransactionRequest.php) (`MapRequestPayload`). Business rules live in [`TransactionService`](backend-api/src/Services/TransactionService.php). |
| **Explicit DB transaction + pessimistic locks** | `beginTransaction` / `commit` / `rollBack` on the DBAL connection. From-account loaded with `PESSIMISTIC_WRITE` and ownership check; to-account locked the same way before balance updates. |
| **Debit-side ownership only** | The authenticated user must own `from_account_id`. `to_account_id` may belong to any user (peer-to-peer transfer). |
| **Post-commit messaging** | [`TransactionSucceededMessage`](backend-api/src/Message/TransactionSucceededMessage.php) is dispatched **after** `commit()`, so email failures do not roll back the transfer. |
| **Notification idempotency** | [`TransactionSucceededHandler`](backend-api/src/MessageHandler/TransactionSucceededHandler.php) skips sending if `notifications_sent_at` is already set (safe retries). |
| **Transfer idempotency (Redis)** | Required `Idempotency-Key` header on `POST /api/v1/transactions`. [`IdempotencyService`](backend-api/src/Services/IdempotencyService.php) claims keys in Redis (`SET NX` + TTL); retries replay the cached response without a second transfer or Messenger dispatch. |
| **Structured API errors** | [`ApiExceptionSubscriber`](backend-api/src/EventSubscriber/ApiExceptionSubscriber.php) normalizes `/api/*` errors to JSON with `success`, `message`, and optional `errors[]`. |
| **Request correlation** | [`RequestLifecycleSubscriber`](backend-api/src/EventSubscriber/RequestLifecycleSubscriber.php) accepts `X-Request-Id` or generates one; echoed on responses and attached to logs. |
| **Redis for cache** | [`config/packages/cache.yaml`](backend-api/config/packages/cache.yaml) uses `cache.adapter.redis`. In **prod**, Doctrine query/result pools use Redis-backed pools ([`doctrine.yaml`](backend-api/config/packages/doctrine.yaml)). |

### Data model

Schema is defined in migration [`Version20260507194306.php`](backend-api/migrations/Version20260507194306.php):

- **users** — credentials and profile
- **accounts** — balance, currency, status, FK to user
- **transactions** — amount, status, from/to accounts, optional note/receipt, `notifications_sent_at`

---

## API reference

Base URL (Docker): **http://localhost:8080**

All `/api/*` routes except `/api/login` and `/api/health` require:

```http
Authorization: Bearer <JWT>
Content-Type: application/json
```

Optional correlation header:

```http
X-Request-Id: <uuid-or-custom-id>
```

Required on transfer `POST` (scoped per authenticated user):

```http
Idempotency-Key: <uuid-or-client-generated-id>
```

Use a new key for each distinct transfer attempt. Reuse the same key only when retrying the **same** request body after a timeout or ambiguous response.

### Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `GET` | `/api/health` | Public | Liveness check |
| `POST` | `/api/login` | Public | Issue JWT (`email`, `password`) |
| `POST` | `/api/v1/transactions` | JWT | Transfer funds (requires `Idempotency-Key`) |
| `GET` | `/api/v1/users/{id}` | JWT | Get user by ID |
| `GET` | `/api/v1/users/{id}/accounts` | JWT | List accounts for user ID (own ID only) |

### `GET /api/health`

**Response 200:**

```json
{
  "status": "ok"
}
```

### `POST /api/login`

**Request:**

```json
{
  "email": "mohangade118@gmail.com",
  "password": "password"
}
```

**Response 200** (Lexik JWT bundle): includes `token` (and related fields per bundle defaults).

**Response 401** on invalid credentials.

Login is throttled: **2 attempts per 15 minutes** per identity (disabled in `test` env).

### `POST /api/v1/transactions`

**Required header:**

```http
Idempotency-Key: 7c9e6679-7425-40de-944b-e07fc1f90ae7
```

| Header | Rules |
|--------|--------|
| `Idempotency-Key` | Required. 1–128 characters; letters, numbers, underscores, hyphens only. Unique per user for each distinct transfer. |

**Request body:**

```json
{
  "from_account_id": 1,
  "to_account_id": 2,
  "amount": 10.5,
  "note": "optional",
  "receipt": "optional"
}
```

| Field | Rules |
|-------|--------|
| `from_account_id` | Required, positive integer |
| `to_account_id` | Required, positive integer, must differ from `from_account_id` |
| `amount` | Required, positive number |
| `note`, `receipt` | Optional strings |

**Success 200:**

```json
{
  "message": "add transaction success",
  "transaction_id": 123
}
```

**Error envelope** (most API errors):

```json
{
  "success": false,
  "message": "Human-readable message",
  "errors": [
    { "field": "amount", "message": "amount must be greater than 0" }
  ]
}
```

| Status | Typical cause |
|--------|----------------|
| 400 | Validation failure, inactive account, insufficient balance, missing/invalid `Idempotency-Key` |
| 401 | Missing or invalid JWT |
| 403 | `from_account_id` exists but does not belong to the authenticated user |
| 404 | Unknown `from_account_id` or `to_account_id` |
| 409 | Same `Idempotency-Key` with a different body, or concurrent in-flight request with the same key |
| 429 | Rate limit exceeded (when enforcement is active; see [Security](#security)) |
| 500 | Unexpected server error |

**Idempotency behavior**

| Scenario | Result |
|----------|--------|
| First `POST` with a new key | Transfer runs once; response cached in Redis (default TTL 24h) |
| Retry with same key and same body | Same HTTP status and JSON body; no second transfer |
| Same key, different body | `409` — key reused with different request |
| Concurrent duplicate while first is processing | `409` — key already in use (stale processing records are reclaimed after 30s) |

### `GET /api/v1/users/{id}`

**Response 200:**

```json
{
  "data": {
    "id": 1,
    "firstName": "Amit",
    "lastName": "Sharma",
    "email": "mohangade118@gmail.com"
  },
  "message": "user details"
}
```

**Response 404** when the user does not exist or is soft-deleted.

### `GET /api/v1/users/{id}/accounts`

Returns all accounts belonging to the user `{id}`. The authenticated user may only request their own ID (`{id}` must match the JWT subject’s user ID).

**Response 200:**

```json
{
  "data": [
    { "id": 1, "balance": 100, "currencyType": "INR", "status": 1 },
    { "id": 2, "balance": 100, "currencyType": "INR", "status": 1 }
  ],
  "message": "account details"
}
```

After fixtures, user 1 (`mohangade118@gmail.com`) has **two** accounts; user 2 has **one**.

| Status | When |
|--------|------|
| 403 | Missing JWT, or `{id}` is not the authenticated user’s ID |
| 404 | User `{id}` does not exist or is soft-deleted |

### Example: full transfer with curl

```bash
# 1. Health check
curl -s http://localhost:8080/api/health

# 2. Login and capture token (requires jq)
TOKEN=$(curl -s -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"mohangade118@gmail.com","password":"password"}' \
  | jq -r .token)

# 3. Discover account IDs (after fixtures; IDs are usually 1 and 2 for user1's accounts)
docker compose exec php php bin/console doctrine:query:sql \
  "SELECT id, user_id, balance FROM accounts ORDER BY id"

# 4. Transfer 10.00 from account 1 to account 3 (example IDs — adjust to your DB)
curl -s -X POST http://localhost:8080/api/v1/transactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "X-Request-Id: demo-transfer-001" \
  -d '{"from_account_id":1,"to_account_id":3,"amount":10,"note":"demo"}'
```

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

| Service | URL / port |
|---------|------------|
| API | http://localhost:8080 |
| MySQL | `localhost:3307` (user `paysera` / password `paysera`, DB `paysera-assignment`) |
| Redis | `localhost:6379` |
| RabbitMQ AMQP | `localhost:5672` |
| RabbitMQ management UI | http://localhost:15672 (`paysera` / `paysera`) |

### Async worker (email notifications)

Transfers commit synchronously; emails are sent asynchronously.

```bash
docker compose exec php php bin/console messenger:setup-transports
docker compose exec php composer messenger:consume:async
```

**Messenger DSN**

- **Inside Docker (default):** `MESSENGER_TRANSPORT_DSN` is set in [`docker-compose.yml`](docker-compose.yml) to `amqp://paysera:paysera@rabbitmq:5672/%2f/messages`.
- **On the host (WSL):** copy [`backend-api/.env.local.example`](backend-api/.env.local.example) to `backend-api/.env.local` and use `127.0.0.1` instead of `rabbitmq`. Requires the PHP `amqp` extension.

**Troubleshooting AMQP**

1. `docker compose ps rabbitmq` — container running
2. `docker compose exec rabbitmq rabbitmq-diagnostics ping`
3. Match broker host to where PHP runs: `rabbitmq` in containers, `127.0.0.1` on the host

### Environment variables

See [`backend-api/.env`](backend-api/.env). Important keys:

| Variable | Purpose |
|----------|---------|
| `DATABASE_URL` | MySQL connection (Docker host: `mysql`) |
| `REDIS_URL` | Redis connection (Docker host: `redis`) — cache, rate limiting, and transfer idempotency |
| `IDEMPOTENCY_TTL_SECONDS` | How long completed/failed idempotency records are kept (default `86400`) |
| `IDEMPOTENCY_PROCESSING_TIMEOUT_SECONDS` | When a `processing` record is considered stale and may be reclaimed (default `30`) |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` | Paths to PEM files under `config/jwt/` |
| `MESSENGER_TRANSPORT_DSN` | AMQP (set in Compose for `php` service) |
| `MAILER_DSN` | Default `null://null` (no real mail in dev) |

---

## Demo data

Loaded by [`UserFixtures`](backend-api/src/DataFixtures/UserFixtures.php) and [`AccountFixtures`](backend-api/src/DataFixtures/AccountFixtures.php). Password for all demo users: **`password`**.

| User | Email | Accounts | Balance each | Currency |
|------|-------|----------|--------------|----------|
| Amit Sharma | `mohangade118@gmail.com` | 2 | 100.0 | INR |
| Priya Kulkarni | `mohangade08@gmail.com` | 1 | 100.0 | INR |

Account **IDs are assigned at fixture load** (typically `1`, `2` for user 1 and `3` for user 2). Query the database or use the SQL command in the [curl example](#example-full-transfer-with-curl) before calling the transfer endpoint.

To reset demo data:

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

---

## Testing and quality

### Automated tests (current status)

The project is structured for three PHPUnit suites (see [`backend-api/composer.json`](backend-api/composer.json) scripts), but **`backend-api/test/` is not yet populated** and **`phpunit.dist.xml` is not committed** in this repository. A clean clone cannot run `composer test` until those assets are added.

**Planned coverage** (when implemented):

| Suite | Path | Purpose |
|-------|------|---------|
| `unit` | `test/Unit/` | Services, DTO validation, subscribers (mocked) |
| `functional` | `test/Integration/Api/` | HTTP: login → JWT → protected routes, transfers |
| `integration` | `test/Integration/Repository/` | Doctrine repositories with real test DB |

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

| Control | Implementation |
|---------|----------------|
| **Authentication** | Stateless JWT on `^/api` firewall ([`security.yaml`](backend-api/config/packages/security.yaml)); `POST /api/login` with `json_login` |
| **Authorization** | `IS_AUTHENTICATED_FULLY` for protected routes; [`TransactionService`](backend-api/src/Services/TransactionService.php) enforces from-account ownership |
| **Input validation** | Symfony Validator constraints on [`CreateTransactionRequest`](backend-api/src/Dto/CreateTransactionRequest.php) |
| **Login throttling** | 2 attempts / 15 minutes (Symfony `login_throttling`) |
| **Rate limiting** | Global API bucket (60/min) and stricter transfer bucket (5 burst, 10/min refill) in [`rate_limiter.yaml`](backend-api/config/packages/rate_limiter.yaml); enforced by [`ApiRateLimitSubscriber`](backend-api/src/EventSubscriber/ApiRateLimitSubscriber.php) |
| **Logging** | `transaction_transfer_requested` / `_succeeded` / `_failed` in controller; Monolog request context processor |

### Known limitations (documented intentionally)

1. **Rate limiter timing** — `ApiRateLimitSubscriber` runs on `KernelEvents::REQUEST` at priority **20** and skips when `_route` is empty. Symfony routing typically runs at priority **32**, so limits may not apply until the subscriber matches on `pathInfo` or runs after routing.
2. **Floating-point money** — balances and amounts use float/DOUBLE; production systems should use `DECIMAL` or minor-unit integers.
3. **Redis-backed idempotency** — duplicate `POST` requests with the same `Idempotency-Key` replay the cached response; if Redis is flushed, duplicate transfers are possible again until keys are re-established.
4. **No currency check** — transfers do not verify matching `currency_type` on both accounts.
5. **User profile endpoint** — `GET /api/v1/users/{id}` returns profile data for any active user ID; restrict to the authenticated user’s own ID in production if required.
6. **Deadlock risk** — locks are taken from → to order; concurrent `A→B` and `B→A` transfers can deadlock; production code should lock accounts in ascending ID order.

---

## High-load considerations

| Mechanism | Role |
|-----------|------|
| **Redis** | Symfony `cache.app` and Doctrine metadata/query/result pools in **prod** reduce DB and filesystem pressure under read-heavy load |
| **Pessimistic locking** | Correctness for concurrent writes on the same accounts (trade-off: lock contention) |
| **RabbitMQ** | Offloads email I/O from the HTTP request; retries with exponential backoff ([`messenger.yaml`](backend-api/config/packages/messenger.yaml)) |
| **Rate limiting** | Protects login and transfer endpoints from abuse (once subscriber timing is fixed; shared Redis pool recommended for multi-instance deployments) |

The transfer itself remains **synchronous** in the request — appropriate for strong consistency on balances. Idempotency keys are stored in **shared Redis** so all PHP workers honor the same `Idempotency-Key` scope per user. Further scaling would add read replicas and connection pooling.

---

## Future improvements

Production hardening I would add next:

- **Lock ordering** by account ID + `DECIMAL(19,4)` (or integer minor units) for amounts
- **Currency validation** on transfer
- **Fix rate limiter** to run after routing or match `pathInfo`
- **Commit PHPUnit** config and full integration tests (including pessimistic-lock paths)
- **Transfer history** and audit log endpoints
- **OpenAPI** spec and CI pipeline (PHPStan, tests, CS Fixer on every PR)
- Restrict **user profile** access to the authenticated user’s own ID only

---

## Time spent

Time spent: **~X hours** *(replace with your actual estimate before submission)*

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
│       ├── system-components.drawio   # Editable diagram (draw.io)
│       └── system-components.svg      # Exported diagram for README
├── docker/
│   ├── nginx/
│   └── php/
├── docker-compose.yml
└── README.md
```

---

## License

Proprietary — submission for Paysera technical evaluation.
