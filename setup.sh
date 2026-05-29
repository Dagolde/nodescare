#!/bin/bash

echo "========================================="
echo "HHMS Project Setup Script"
echo "========================================="
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running. Please start Docker and try again."
    exit 1
fi

echo "Step 1: Starting Docker services..."
docker-compose up -d

echo ""
echo "Step 2: Waiting for MySQL to be ready..."
sleep 10

echo ""
echo "Step 3: Setting up Laravel backend..."
cd backend

if [ ! -f ".env" ]; then
    echo "Creating .env file..."
    cp .env.example .env
fi

echo "Installing Composer dependencies..."
docker-compose exec php composer install

echo "Generating application key..."
docker-compose exec php php artisan key:generate

echo "Running central database migrations..."
docker-compose exec php php artisan migrate --force

echo "Installing Node dependencies..."
npm install

echo "Building frontend assets..."
npm run build

cd ..

echo ""
echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Services running:"
echo "  - Laravel API: http://localhost"
echo "  - MySQL: localhost:3306"
echo "  - Redis: localhost:6379"
echo "  - Soketi WebSocket: localhost:6001"
echo ""
echo "Next steps:"
echo "  1. Configure Cloudflare R2 credentials in backend/.env"
echo "  2. Set up Firebase project for mobile app"
echo "  3. Run 'cd mobile && flutter pub get' to setup Flutter app"
echo ""
echo "To stop services: docker-compose down"
echo "To view logs: docker-compose logs -f"
