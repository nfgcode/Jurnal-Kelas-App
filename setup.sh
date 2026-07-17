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
    echo "[1/6] Creating new Laravel project..."
    docker compose exec app composer create-project laravel/laravel temp-laravel
    docker compose exec app sh -c "cp -a temp-laravel/. . && rm -rf temp-laravel"
else
    echo ""
    echo "[1/6] Laravel project already exists. Skipping creation."
fi

# Step 2: Install PHP dependencies
echo ""
echo "[2/6] Installing Composer dependencies..."
docker compose exec app composer install

# Step 3: Copy .env if needed
if [ ! -f ".env" ]; then
    echo ""
    echo "[3/6] Creating .env from .env.example..."
    cp .env.example .env
    docker compose exec app php artisan key:generate
else
    echo ""
    echo "[3/6] .env already exists. Skipping."
fi

# Step 4: Run migrations
echo ""
echo "[4/6] Running database migrations..."
docker compose exec app php artisan migrate --force

# Step 5: Install dependencies + Bootstrap
echo ""
echo "[5/6] Installing dependencies (Bootstrap, Sass, Vite) via Bun..."
docker compose exec app bun install
docker compose exec app bun add bootstrap @popperjs/core sass

# Step 6: Build frontend assets
echo ""
echo "[6/6] Building frontend assets..."
docker compose exec app bun run build

echo ""
echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "  App:        http://localhost:8080"
echo "  Mailpit:    http://localhost:8025"
echo "  phpMyAdmin: http://localhost:8081  (use: docker compose --profile dev up -d)"
echo ""
echo "  Useful commands:"
echo "    docker compose exec app php artisan ...    # Run Artisan commands"
echo "    docker compose exec app composer ...       # Run Composer commands"
echo "    docker compose exec app bun run dev        # Start Vite dev server"
echo "    docker compose --profile dev up -d         # Start phpMyAdmin + Bun dev"
echo ""
