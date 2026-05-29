# HHMS Quick Start Guide

## Prerequisites

Ensure you have the following installed:

- **Docker Desktop** (with Docker Compose)
- **PHP 8.2+** and **Composer** (for local Laravel development)
- **Node.js 18+** and **npm**
- **Flutter SDK 3.2+** (for mobile development)

## Step-by-Step Setup

### 1. Clone and Navigate to Project

```bash
cd /path/to/hhms
```

### 2. Start Docker Services

```bash
docker-compose up -d
```

This starts all required services:
- PHP-FPM (Laravel runtime)
- Nginx (web server)
- MySQL 8 (central database)
- Redis (cache/queue)
- Soketi (WebSocket server)

### 3. Setup Laravel Backend

```bash
cd backend

# Copy environment file
cp .env.example .env

# Install PHP dependencies (inside Docker container)
docker-compose exec php composer install

# Generate application key
docker-compose exec php php artisan key:generate

# Run central database migrations
docker-compose exec php php artisan migrate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### 4. Configure Environment

Edit `backend/.env` and set:

```env
# Cloudflare R2 Configuration
AWS_ACCESS_KEY_ID=your_r2_access_key
AWS_SECRET_ACCESS_KEY=your_r2_secret_key
AWS_BUCKET=hhms-tenant-default
AWS_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com

# JWT Secret (generate with: php artisan jwt:secret)
JWT_SECRET=your_jwt_secret_here
```

### 5. Setup Flutter Mobile App

```bash
cd mobile

# Get Flutter dependencies
flutter pub get

# Configure Firebase (optional for now)
# - Add google-services.json to android/app/
# - Add GoogleService-Info.plist to ios/Runner/

# Run on connected device/emulator
flutter run
```

## Verify Installation

### Check Docker Services

```bash
docker-compose ps
```

All services should show "Up" status.

### Test Laravel API

```bash
curl http://localhost/api/v1/auth/login
```

Should return a JSON response.

### Test Web Dashboard

Open browser: http://localhost

Should display the HHMS welcome page.

### Test Mobile App

Run Flutter app on emulator/device:

```bash
cd mobile
flutter run
```

Should display "Hospital Health Management System" home screen.

## Common Commands

### Docker

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Restart a service
docker-compose restart php
```

### Laravel

```bash
# Run migrations (central DB)
docker-compose exec php php artisan migrate

# Run migrations (tenant DBs)
docker-compose exec php php artisan tenants:migrate

# Create a new tenant
docker-compose exec php php artisan tinker
>>> App\Models\Tenant::create(['name' => 'Test Hospital', 'subdomain' => 'test'])

# Clear cache
docker-compose exec php php artisan cache:clear
```

### Frontend Development

```bash
cd backend

# Start Vite dev server (hot reload)
npm run dev

# Build for production
npm run build
```

### Flutter

```bash
cd mobile

# Run in debug mode
flutter run

# Run tests
flutter test

# Build APK
flutter build apk

# Build iOS
flutter build ios
```

## Troubleshooting

### Docker Issues

**Problem**: Services won't start
```bash
# Check Docker is running
docker info

# Remove old containers and volumes
docker-compose down -v
docker-compose up -d
```

**Problem**: Port conflicts
```bash
# Check what's using port 80
lsof -i :80  # macOS/Linux
netstat -ano | findstr :80  # Windows

# Change ports in docker-compose.yml if needed
```

### Laravel Issues

**Problem**: Permission errors
```bash
# Fix storage permissions
docker-compose exec php chmod -R 777 storage bootstrap/cache
```

**Problem**: Database connection failed
```bash
# Check MySQL is running
docker-compose ps mysql_central

# Check credentials in .env match docker-compose.yml
```

### Flutter Issues

**Problem**: Dependencies not found
```bash
# Clean and reinstall
flutter clean
flutter pub get
```

**Problem**: Build errors
```bash
# Update Flutter
flutter upgrade

# Check Flutter doctor
flutter doctor
```

## Next Steps

1. **Configure Cloudflare R2**: Set up your R2 bucket and credentials
2. **Configure Firebase**: Set up FCM for push notifications
3. **Create First Tenant**: Use Tinker or create Super Admin interface
4. **Explore API**: Check `routes/api.php` for available endpoints
5. **Develop Features**: Follow the tasks in `.kiro/specs/hospital-health-management-system/tasks.md`

## Development Workflow

1. Start Docker: `docker-compose up -d`
2. Start Vite (hot reload): `cd backend && npm run dev`
3. Code in your IDE
4. Test API with Postman/Insomnia
5. Test mobile app: `cd mobile && flutter run`
6. Commit changes to Git

## Resources

- **Laravel Docs**: https://laravel.com/docs/11.x
- **Stancl Tenancy**: https://tenancyforlaravel.com/docs
- **Inertia.js**: https://inertiajs.com
- **Vue.js 3**: https://vuejs.org
- **Flutter**: https://flutter.dev/docs
- **Soketi**: https://docs.soketi.app

## Support

For issues or questions:
1. Check PROJECT_STRUCTURE.md for architecture details
2. Check README.md for comprehensive documentation
3. Review the spec files in `.kiro/specs/hospital-health-management-system/`
4. Contact the development team

---

**Happy Coding! 🚀**
