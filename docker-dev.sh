#!/usr/bin/env bash
set -e

# Determine project directory
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"

# Detect LAN IP
IP=$(ip -4 addr show wlp39s0f3u2 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then
    IP=$(hostname -I | awk '{print $1}')
fi

# Trim whitespace
IP=$(echo "$IP" | xargs)

# Check if certificates exist
if [ ! -f "${IP}.pem" ] || [ ! -f "${IP}-key.pem" ]; then
    echo "⚠️  Warning: Certificate files for IP '${IP}' (${IP}.pem / ${IP}-key.pem) not found in project root."
    echo "Checking if we can find 192.168.1.100 certificates..."
    if [ -f "192.168.1.100.pem" ] && [ -f "192.168.1.100-key.pem" ]; then
        echo "Found 192.168.1.100 certificates. Forcing DEV_HOST=192.168.1.100 to match certificates."
        IP="192.168.1.100"
    else
        echo "❌ Error: No matching certificates found in project root."
        echo "Please ensure you have '<IP>.pem' and '<IP>-key.pem' in the root directory."
        exit 1
    fi
fi

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

# Run Docker Compose
docker compose up --build
