# Hospital Health Management System (HHMS)

Multi-tenant, cloud-based healthcare platform with Laravel backend, Vue.js web dashboard, and Flutter mobile app.

## Architecture Overview

- **Backend**: Laravel 11 REST API with `stancl/tenancy` for database-per-tenant isolation
- **Web Dashboard**: Vue.js 3 + Inertia.js served from Laravel
- **Mobile App**: Flutter with layered architecture (presentation/domain/data)
- **Database**: MySQL 8 (central + per-tenant databases)
- **Cache/Queue**: Redis
- **Real-time**: Soketi WebSocket server (Pusher-compatible)
- **File Storage**: Cloudflare R2 (S3-compatible)
- **Push Notifications**: Firebase Cloud Messaging (FCM)

## Project Structure

```
.
├── backend/              # Laravel 11 application
│   ├── app/             # Application code
│   ├── config/          # Configuration files
│   ├── database/        # Migrations and seeders
│   ├── resources/       # Vue.js frontend + views
│   └── routes/          # API and web routes
├── mobile/              # Flutter application
│   ├── lib/
│   │   ├── data/        # Data layer (remote/local sources)
│   │   ├── domain/      # Domain layer (entities, repositories, use cases)
│   │   └── presentation/# Presentation layer (screens, widgets)
│   └── pubspec.yaml
└── docker/              # Docker configuration
    ├── php/
    ├── nginx/
    └── mysql/
```

## Prerequisites

- Docker & Docker Compose
- PHP 8.2+ (for local development)
- Composer
- Node.js 18+ & npm
- Flutter SDK 3.2+

## Quick Start

### 1. Start Docker Services

```bash
docker-compose up -d
```

This starts:
- PHP-FPM (port 9000)
- Nginx (port 80)
- MySQL Central DB (port 3306)
- Redis (port 6379)
- Soketi WebSocket (port 6001)

### 2. Setup Laravel Backend

```bash
cd backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret
php artisan jwt:secret

# Run central database migrations
php artisan migrate

# Install Node dependencies
npm install

# Build frontend assets
npm run build
```

### 3. Setup Flutter Mobile App

```bash
cd mobile

# Get Flutter dependencies
flutter pub get

# Run on connected device/emulator
flutter run
```

## Configuration

### Laravel Environment Variables

Key configurations in `backend/.env`:

- **Database**: Central (landlord) database connection
- **Redis**: Cache and queue driver
- **Cloudflare R2**: S3-compatible object storage
- **Soketi**: WebSocket server configuration
- **JWT**: Authentication token settings

### Tenancy Configuration

Multi-tenancy is configured via `stancl/tenancy`:

- **Strategy**: Database-per-tenant
- **Resolution**: Subdomain or `X-Tenant-ID` header
- **Tenant Database Naming**: `tenant{tenant_id}`

### Flutter Configuration

Mobile app dependencies:

- `dio`: HTTP client
- `pusher_channels_flutter`: WebSocket/real-time
- `sqflite_sqlcipher`: Encrypted local storage
- `flutter_secure_storage`: Secure key storage
- `connectivity_plus`: Network monitoring
- `firebase_messaging`: Push notifications

## Development

### Running Laravel Development Server

```bash
cd backend
php artisan serve
```

### Running Vite Dev Server (Hot Reload)

```bash
cd backend
npm run dev
```

### Running Flutter in Debug Mode

```bash
cd mobile
flutter run
```

## Database Migrations

### Central (Landlord) Migrations

```bash
php artisan migrate
```

### Tenant Migrations

```bash
php artisan tenants:migrate
```

## API Documentation

API endpoints are prefixed with `/api/v1/`:

- **Authentication**: `/api/v1/auth/*`
- **Super Admin**: `/api/v1/admin/*`
- **Hospital Admin**: `/api/v1/departments/*`, `/api/v1/staff/*`
- **Patient Management**: `/api/v1/patients/*`
- **Department Workflow**: `/api/v1/department/*`
- **Accounts**: `/api/v1/accounts/*`, `/api/v1/invoices/*`

## Testing

### Laravel Tests

```bash
cd backend
php artisan test
```

### Flutter Tests

```bash
cd mobile
flutter test
```

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure Cloudflare R2 credentials
- [ ] Set up Firebase project for FCM
- [ ] Configure production database
- [ ] Set up SSL certificates
- [ ] Configure production Soketi instance
- [ ] Build production assets: `npm run build`

## License

Proprietary - Hospital Health Management System

## Support

For technical support, contact the development team.
