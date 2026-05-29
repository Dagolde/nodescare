# HHMS Deployment Guide

## Prerequisites

| Tool | Minimum Version | Notes |
|---|---|---|
| Docker | 24.x | [Install Docker](https://docs.docker.com/get-docker/) |
| Docker Compose | 2.x | Bundled with Docker Desktop |
| PHP | 8.2+ | Required for local Artisan commands outside Docker |
| Node.js | 20+ | Required for frontend asset compilation |
| Composer | 2.x | PHP dependency manager |

---

## Installation Guide

### 1. Clone the repository

```bash
git clone https://github.com/your-org/hhms.git
cd hhms
```

### 2. Configure environment variables

```bash
cp backend/.env.example backend/.env
```

Edit `backend/.env` and fill in all required values (see [Environment Variables Reference](#environment-variables-reference) below).

### 3. Generate application key

```bash
docker run --rm -v $(pwd)/backend:/app -w /app php:8.2-cli php artisan key:generate
```

Or if PHP is installed locally:

```bash
cd backend && php artisan key:generate
```

### 4. Install PHP dependencies

```bash
cd backend && composer install --no-dev --optimize-autoloader
```

### 5. Install Node.js dependencies and build frontend assets

```bash
cd backend && npm ci && npm run build
```

### 6. Prepare TLS certificates

For production, place your real TLS certificate and key at the paths referenced by `SSL_CERT_PATH` and `SSL_KEY_PATH` in your `.env`.

For development/staging, generate a self-signed certificate:

```bash
mkdir -p docker/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/key.pem \
  -out docker/nginx/ssl/cert.pem \
  -subj "/CN=localhost"
```

Then set in `.env`:
```
SSL_CERT_PATH=./docker/nginx/ssl/cert.pem
SSL_KEY_PATH=./docker/nginx/ssl/key.pem
```

### 7. Start the production stack

```bash
docker compose -f docker-compose.prod.yml up -d
```

### 8. Run database migrations

```bash
docker compose -f docker-compose.prod.yml exec php php artisan migrate --force
```

### 9. Seed initial Super Admin account

```bash
docker compose -f docker-compose.prod.yml exec php php artisan db:seed --class=SuperAdminSeeder --force
```

### 10. Verify the deployment

```bash
# Check all services are running
docker compose -f docker-compose.prod.yml ps

# Check application health
curl -k https://localhost/api/v1/health
```

---

## Environment Variables Reference

### Application

| Variable | Required | Description |
|---|---|---|
| `APP_KEY` | Yes | Laravel application encryption key (generate with `php artisan key:generate`) |
| `APP_URL` | Yes | Full public URL, e.g. `https://hhms.yourdomain.com` |
| `APP_ENV` | Yes | Set to `production` for production deployments |
| `APP_DEBUG` | Yes | Set to `false` in production |

### Database

| Variable | Required | Description |
|---|---|---|
| `DB_DATABASE` | Yes | Central (landlord) database name, e.g. `hhms_central` |
| `DB_USERNAME` | Yes | MySQL user with full privileges on the central DB |
| `DB_PASSWORD` | Yes | MySQL user password |
| `MYSQL_ROOT_PASSWORD` | Yes | MySQL root password (used only by the MySQL container) |

### Redis

| Variable | Required | Description |
|---|---|---|
| `REDIS_PASSWORD` | Yes | Redis authentication password |

### JWT

| Variable | Required | Description |
|---|---|---|
| `JWT_SECRET` | Yes | Secret key for signing JWTs (generate with `php artisan jwt:secret`) |

### TLS / Nginx

| Variable | Required | Description |
|---|---|---|
| `SSL_CERT_PATH` | Yes | Host path to TLS certificate PEM file |
| `SSL_KEY_PATH` | Yes | Host path to TLS private key PEM file |

### Cloudflare R2 (File Storage)

| Variable | Required | Description |
|---|---|---|
| `R2_ACCESS_KEY_ID` | Yes | R2 API access key ID |
| `R2_SECRET_ACCESS_KEY` | Yes | R2 API secret access key |
| `R2_BUCKET` | Yes | Default R2 bucket name (tenant buckets are named `hhms-tenant-{id}`) |
| `R2_ENDPOINT` | Yes | R2 S3-compatible endpoint, e.g. `https://<account-id>.r2.cloudflarestorage.com` |
| `R2_REGION` | No | Region identifier, defaults to `auto` |

### Soketi (WebSocket)

| Variable | Required | Description |
|---|---|---|
| `SOKETI_APP_ID` | Yes | Soketi application ID |
| `SOKETI_APP_KEY` | Yes | Soketi application key (used by clients) |
| `SOKETI_APP_SECRET` | Yes | Soketi application secret (used by the server) |

### Firebase Cloud Messaging

| Variable | Required | Description |
|---|---|---|
| `FIREBASE_CREDENTIALS` | Yes | Path to Firebase service account JSON file, or JSON string |

### Email (SMTP)

| Variable | Required | Description |
|---|---|---|
| `MAIL_MAILER` | Yes | Mail driver, e.g. `smtp` or `ses` |
| `MAIL_HOST` | Yes | SMTP server hostname |
| `MAIL_PORT` | Yes | SMTP port, typically `587` (TLS) or `465` (SSL) |
| `MAIL_USERNAME` | Yes | SMTP authentication username |
| `MAIL_PASSWORD` | Yes | SMTP authentication password |
| `MAIL_ENCRYPTION` | Yes | `tls` or `ssl` |
| `MAIL_FROM_ADDRESS` | Yes | Sender email address |

### SMS Gateway

| Variable | Required | Description |
|---|---|---|
| `SMS_DRIVER` | Yes | SMS provider: `twilio`, `vonage`, `africastalking`, or `null` (disabled) |
| `TWILIO_SID` | Conditional | Twilio Account SID (required if `SMS_DRIVER=twilio`) |
| `TWILIO_TOKEN` | Conditional | Twilio Auth Token |
| `TWILIO_FROM` | Conditional | Twilio sender phone number |

---

## Secrets Manager Setup Guide

For production, store sensitive credentials in a secrets manager rather than plain `.env` files.

### AWS Secrets Manager

1. Create a secret for each sensitive value (DB password, JWT secret, R2 keys, etc.).
2. Grant the EC2 instance or ECS task role `secretsmanager:GetSecretValue` permission.
3. Use the [AWS Parameter Store Laravel package](https://github.com/aws/aws-sdk-php-laravel) or a startup script to inject secrets into environment variables before the container starts.

Example startup script (`docker/php/entrypoint.sh`):

```bash
#!/bin/sh
# Fetch secrets from AWS Secrets Manager and export as env vars
export DB_PASSWORD=$(aws secretsmanager get-secret-value --secret-id hhms/db_password --query SecretString --output text)
export JWT_SECRET=$(aws secretsmanager get-secret-value --secret-id hhms/jwt_secret --query SecretString --output text)
exec "$@"
```

### HashiCorp Vault

1. Store secrets under `secret/hhms/`.
2. Use the [Vault Agent](https://developer.hashicorp.com/vault/docs/agent-and-proxy/agent) sidecar to render a `.env` file from Vault templates before the PHP container starts.

---

## Scaling Guide

### Horizontal Scaling — API (PHP-FPM + Nginx)

The PHP-FPM and Nginx services are stateless and can be scaled horizontally behind a load balancer.

1. Deploy multiple instances of the `php` and `nginx` services.
2. Place an external load balancer (AWS ALB, Nginx upstream, HAProxy) in front.
3. Ensure all instances share the same Redis instance for sessions and cache.
4. Ensure all instances share the same MySQL central database.

```bash
# Scale PHP-FPM to 3 replicas (Docker Swarm)
docker service scale hhms_php=3
```

### Queue Workers

Scale queue workers independently to handle notification and provisioning load:

```bash
# Run additional queue workers
docker compose -f docker-compose.prod.yml up -d --scale queue-worker=5
```

For high-priority queues (tenant provisioning), use dedicated workers:

```bash
docker compose -f docker-compose.prod.yml exec php \
  php artisan queue:work --queue=high,default --sleep=3 --tries=3
```

### Soketi Instances

Soketi supports horizontal scaling via a shared Redis adapter. All Soketi instances must point to the same Redis server.

```bash
docker compose -f docker-compose.prod.yml up -d --scale soketi=3
```

Place a TCP load balancer (e.g., AWS NLB) in front of Soketi instances on port 6001.

---

## Backup and Restore Procedures

### Database Backup

```bash
# Backup central database
docker compose -f docker-compose.prod.yml exec mysql_central \
  mysqldump -u root -p${MYSQL_ROOT_PASSWORD} --all-databases \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup a specific tenant database
docker compose -f docker-compose.prod.yml exec mysql_central \
  mysqldump -u root -p${MYSQL_ROOT_PASSWORD} tenant_<tenant_id> \
  > tenant_<tenant_id>_backup_$(date +%Y%m%d_%H%M%S).sql
```

Automate with a cron job or AWS RDS automated backups.

### Database Restore

```bash
# Restore from backup
docker compose -f docker-compose.prod.yml exec -T mysql_central \
  mysql -u root -p${MYSQL_ROOT_PASSWORD} < backup_YYYYMMDD_HHMMSS.sql
```

### Redis Backup

Redis persistence is enabled with `appendonly yes`. The AOF file is stored in the `redis_data` named volume.

```bash
# Copy Redis AOF file from the volume
docker run --rm -v hhms_redis_data:/data -v $(pwd):/backup alpine \
  cp /data/appendonly.aof /backup/redis_backup_$(date +%Y%m%d).aof
```

### File Storage (Cloudflare R2)

R2 objects are replicated by Cloudflare. For additional protection, use [rclone](https://rclone.org/) to sync R2 buckets to another storage provider:

```bash
rclone sync r2:hhms-tenant-<id> s3:backup-bucket/hhms-tenant-<id> --progress
```

### Disaster Recovery

1. Restore MySQL from the latest backup.
2. Run `php artisan migrate --force` to apply any pending migrations.
3. Restore Redis AOF file if needed (cache and queue data).
4. R2 file storage is managed by Cloudflare — no restore needed unless objects were deleted.
5. Restart all services: `docker compose -f docker-compose.prod.yml up -d`.
