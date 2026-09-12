#!/usr/bin/env bash
set -e

# Determine project directory
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"

# Detect LAN IP
IP=$(ip -4 addr show scope global 2>/dev/null | grep -vE '(docker|br-|veth|amn|wg|tun|tap)' | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then
    IP=$(ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '^127\.' | grep -v '^172\.' | head -n 1)
fi

# Trim whitespace
IP=$(echo "$IP" | xargs)

# Check if certificates exist, auto-generate if missing
if [ ! -f "${IP}.pem" ] || [ ! -f "${IP}-key.pem" ]; then
    echo "⚠️  Certificate files for IP '${IP}' (${IP}.pem / ${IP}-key.pem) not found in project root."
    echo "🔐 Generating self-signed SSL certificates for ${IP}..."
    if command -v mkcert >/dev/null 2>&1; then
        mkcert -key-file "${IP}-key.pem" -cert-file "${IP}.pem" "$IP" "localhost" 127.0.0.1 ::1
    elif command -v openssl >/dev/null 2>&1; then
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "${IP}-key.pem" -out "${IP}.pem" \
            -subj "/CN=${IP}" \
            -addext "subjectAltName=IP:${IP},IP:127.0.0.1,DNS:localhost" 2>/dev/null
    else
        echo "❌ Error: Neither mkcert nor openssl is installed to generate certificates."
        echo "Please generate '${IP}.pem' and '${IP}-key.pem' manually."
        exit 1
    fi
    echo "✅ Generated ${IP}.pem and ${IP}-key.pem successfully."
fi

# Ensure .env exists
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "📄 .env file not found. Creating from .env.example..."
        cp .env.example .env
    else
        echo "❌ Error: Neither .env nor .env.example found."
        exit 1
    fi
fi

# Source .env
set -a
[ -f .env ] && . ./.env 2>/dev/null || true
set +a

export DEV_HOST="$IP"

echo "================================================================="
echo "🚀 Starting Docker Compose Dev Environment"
echo "================================================================="
echo "  🌐 LAN IP / Host:       $DEV_HOST"
echo "  🖥️  Web App URL (HTTPS):  https://$DEV_HOST:8443"
echo "  ⚡ Vite HMR (HTTPS):     https://$DEV_HOST:5173"
echo "  📬 Mailpit UI:           http://$DEV_HOST:8025"
echo "  🗄️  PostgreSQL Port:     $DEV_HOST:5433"
echo "  📦 Redis Port:          $DEV_HOST:6379"
echo "================================================================="
echo ""

# Seeding is now handled completely in the container's entrypoint.sh -> DatabaseSeeder

# Run Docker Compose
docker compose up --build --remove-orphans
