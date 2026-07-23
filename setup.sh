#!/bin/bash
# ===========================================
# Jurnal Kelas App - Docker Setup Script
# ===========================================
# Run this script AFTER `docker compose up -d --build`

set -e

echo "=========================================="
echo "  Jurnal Kelas App - Initial Setup"
echo "=========================================="

# Step 1: Install Laravel (if not already installed)
if [ ! -f "composer.json" ]; then
    echo ""
    echo "[1/7] Creating new Laravel project..."
    docker compose exec app composer create-project laravel/laravel temp-laravel
    docker compose exec app sh -c "cp -a temp-laravel/. . && rm -rf temp-laravel"
else
    echo ""
    echo "[1/7] Laravel project already exists. Skipping creation."
fi

# Step 2: Install PHP dependencies
echo ""
echo "[2/7] Installing Composer dependencies..."
docker compose exec app composer install

# Step 3: Copy .env if needed
if [ ! -f ".env" ]; then
    echo ""
    echo "[3/7] Creating .env from .env.example..."
    cp .env.example .env
    docker compose exec app php artisan key:generate
else
    echo ""
    echo "[3/7] .env already exists. Skipping."
fi

# Step 4: Run migrations
echo ""
echo "[4/7] Running database migrations..."
docker compose exec app php artisan migrate --force

# Step 5: Seed demo data
echo ""
echo "[5/7] Seeding demo data..."
docker compose exec app php artisan db:seed --force

# Step 6: Install JS dependencies via Bun
echo ""
echo "[6/7] Installing JS dependencies via Bun..."
docker compose exec app bun install

# Step 7: Build frontend assets
echo ""
echo "[7/7] Building frontend assets..."
docker compose exec app bun run build

echo ""
echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "  App:        http://localhost:8888"
echo "  Mailpit:    http://localhost:8025"
echo "  phpMyAdmin: http://localhost:8081  (use: docker compose --profile dev up -d)"
echo "  Vite HMR:   http://localhost:5173  (use: docker compose --profile dev up -d)"
echo ""
echo "  Default Login:"
echo "    Admin:  admin@jurnalkelas.app / password"
echo "    Guru:   budi.santoso@jurnalkelas.app / password"
echo ""
echo "  Useful commands:"
echo "    make up             # Start all containers"
echo "    make up-dev          # Start all containers + dev tools"
echo "    make shell           # Open shell in app container"
echo "    make migrate         # Run database migrations"
echo "    make fresh           # Fresh migration with seeders"
echo "    make dev             # Start Vite dev server inside app container"
echo ""
