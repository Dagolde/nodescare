# HHMS Project Structure

## Overview

This document describes the complete project structure for the Hospital Health Management System.

## Directory Structure

```
hhms/
├── backend/                          # Laravel 11 Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/         # API Controllers
│   │   │   └── Middleware/          # Custom middleware
│   │   ├── Models/                  # Eloquent models
│   │   │   ├── Tenant.php          # Tenant model (central DB)
│   │   │   └── Plan.php            # Plan model (central DB)
│   │   ├── Providers/              # Service providers
│   │   │   ├── AppServiceProvider.php
│   │   │   └── TenancyServiceProvider.php
│   │   └── Contracts/              # Interfaces (e.g., SmsGateway)
│   ├── bootstrap/
│   │   ├── app.php                 # Application bootstrap
│   │   └── providers.php           # Provider registration
│   ├── config/
│   │   ├── tenancy.php             # Tenancy configuration
│   │   ├── broadcasting.php        # WebSocket configuration
│   │   └── filesystems.php         # R2/S3 configuration
│   ├── database/
│   │   ├── migrations/             # Central DB migrations
│   │   │   ├── 2024_01_01_000001_create_tenants_table.php
│   │   │   ├── 2024_01_01_000002_create_plans_table.php
│   │   │   ├── 2024_01_01_000003_create_system_settings_table.php
│   │   │   ├── 2024_01_01_000004_create_super_admins_table.php
│   │   │   └── 2024_01_01_000005_create_domains_table.php
│   │   └── migrations/tenant/      # Tenant DB migrations (to be created)
│   ├── resources/
│   │   ├── js/
│   │   │   ├── app.js              # Vue.js entry point
│   │   │   ├── bootstrap.js        # Laravel Echo setup
│   │   │   └── Pages/              # Inertia.js pages
│   │   │       ├── Welcome.vue
│   │   │       └── Dashboard.vue
│   │   ├── css/
│   │   │   └── app.css             # Global styles
│   │   └── views/
│   │       └── app.blade.php       # Inertia root template
│   ├── routes/
│   │   ├── web.php                 # Web routes (Inertia)
│   │   ├── api.php                 # API routes
│   │   └── console.php             # Artisan commands
│   ├── public/
│   │   └── index.php               # Entry point
│   ├── composer.json               # PHP dependencies
│   ├── package.json                # Node dependencies
│   ├── vite.config.js              # Vite configuration
│   └── .env.example                # Environment template
│
├── mobile/                          # Flutter Mobile App
│   ├── lib/
│   │   ├── main.dart               # App entry point
│   │   ├── data/                   # Data Layer
│   │   │   ├── data_sources/
│   │   │   │   ├── remote_data_source.dart  # API client (Dio)
│   │   │   │   └── local_data_source.dart   # SQLCipher DB
│   │   │   ├── models/             # Data models
│   │   │   └── repositories/       # Repository implementations
│   │   ├── domain/                 # Domain Layer
│   │   │   ├── entities/           # Business entities
│   │   │   ├── repositories/       # Repository interfaces
│   │   │   └── use_cases/          # Business logic
│   │   └── presentation/           # Presentation Layer
│   │       ├── screens/            # App screens
│   │       ├── widgets/            # Reusable widgets
│   │       └── providers/          # Riverpod providers
│   ├── android/
│   │   └── app/
│   │       ├── build.gradle        # Android build config
│   │       └── src/main/
│   │           └── AndroidManifest.xml
│   ├── ios/
│   │   └── Runner/
│   │       └── Info.plist          # iOS configuration
│   ├── pubspec.yaml                # Flutter dependencies
│   └── .gitignore
│
├── docker/                          # Docker Configuration
│   ├── php/
│   │   ├── Dockerfile              # PHP-FPM image
│   │   └── php.ini                 # PHP configuration
│   ├── nginx/
│   │   └── default.conf            # Nginx configuration
│   └── mysql/
│       └── init-tenant-dbs.sh      # DB initialization script
│
├── docker-compose.yml               # Docker services definition
├── setup.sh                         # Setup automation script
├── README.md                        # Project documentation
└── PROJECT_STRUCTURE.md             # This file
```

## Key Components

### Backend (Laravel)

#### Central Database Tables
- `tenants`: Hospital registry
- `plans`: Subscription/feature tiers
- `system_settings`: Global configuration
- `super_admins`: Platform administrators
- `domains`: Tenant domain mapping

#### Tenant Database Tables (to be created in Task 2+)
- `users`: Staff accounts
- `departments`: Hospital units
- `patients`: Patient demographics
- `patient_records`: Visit/encounter records
- `patient_journey_events`: Workflow history
- `invoices`: Payment records
- `audit_logs`: System audit trail
- And more...

#### Key Packages
- `stancl/tenancy`: Multi-tenancy (database-per-tenant)
- `php-open-source-saver/jwt-auth`: JWT authentication
- `inertiajs/inertia-laravel`: Vue.js integration
- `league/flysystem-aws-s3-v3`: Cloudflare R2 storage

### Frontend (Vue.js + Inertia.js)

#### Structure
- Served from Laravel application
- No separate frontend deployment
- SPA experience with server-side routing
- Real-time updates via Laravel Echo + Soketi

#### Key Packages
- `vue`: Vue.js 3
- `@inertiajs/vue3`: Inertia.js adapter
- `pusher-js`: WebSocket client
- `laravel-echo`: Broadcasting client

### Mobile (Flutter)

#### Architecture
Layered architecture with clear separation:

1. **Presentation Layer**: UI components, screens, state management
2. **Domain Layer**: Business logic, entities, use cases
3. **Data Layer**: API client, local storage, repositories

#### Key Packages
- `dio`: HTTP client
- `pusher_channels_flutter`: WebSocket/real-time
- `sqflite_sqlcipher`: Encrypted local storage (AES-256)
- `flutter_secure_storage`: Secure key storage
- `connectivity_plus`: Network monitoring
- `firebase_messaging`: Push notifications
- `flutter_riverpod`: State management

### Infrastructure

#### Docker Services
- **PHP-FPM**: PHP 8.2 runtime
- **Nginx**: Web server
- **MySQL 8**: Central + tenant databases
- **Redis**: Cache and queue driver
- **Soketi**: WebSocket server (Pusher-compatible)

#### External Services (to be configured)
- **Cloudflare R2**: Object storage (S3-compatible)
- **Firebase**: Push notifications (FCM)
- **SMS Gateway**: Pluggable (Twilio/Vonage/Africa's Talking)

## Configuration Files

### Laravel
- `.env`: Environment variables
- `config/tenancy.php`: Multi-tenancy settings
- `config/broadcasting.php`: WebSocket configuration
- `config/filesystems.php`: R2/S3 configuration

### Flutter
- `pubspec.yaml`: Dependencies
- `android/app/build.gradle`: Android configuration
- `ios/Runner/Info.plist`: iOS configuration

### Docker
- `docker-compose.yml`: Service orchestration
- `docker/php/Dockerfile`: PHP image
- `docker/nginx/default.conf`: Nginx config

## Next Steps

After Task 1 (Project Scaffolding) is complete:

1. **Task 2**: Implement tenant database migrations
2. **Task 3**: Implement JWT authentication
3. **Task 4**: Implement tenant resolution middleware
4. **Task 5**: Implement Super Admin functionality
5. **Task 6+**: Implement core business features

## Development Workflow

1. Start Docker services: `docker-compose up -d`
2. Backend development: `cd backend && php artisan serve`
3. Frontend hot reload: `cd backend && npm run dev`
4. Mobile development: `cd mobile && flutter run`
5. Run migrations: `php artisan migrate` (central) or `php artisan tenants:migrate` (tenant)

## Testing

- Laravel: `php artisan test`
- Flutter: `flutter test`

## Deployment

See README.md for production deployment checklist.
