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
