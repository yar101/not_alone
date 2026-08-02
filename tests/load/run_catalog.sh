#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"

# Определение локального IP
IP=$(ip -4 addr show wlp39s0f3u2 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then IP=$(hostname -I | awk '{print $1}'); fi
IP=$(echo "$IP" | xargs)
if [ -f "$DIR/../../192.168.1.100.pem" ]; then IP="192.168.1.100"; fi

echo "========================================================"
echo "🚀 Запуск нагрузочного теста каталога (/search)"
echo "🎯 Цель: https://$IP:8443"
echo "========================================================"

docker run --rm -i -e BASE_URL=https://$IP:8443 grafana/k6 run --insecure-skip-tls-verify - < "$DIR/catalog_stress.js"
