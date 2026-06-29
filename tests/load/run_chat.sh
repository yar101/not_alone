#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"

IP=$(ip -4 addr show wlp39s0f3u2 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then IP=$(hostname -I | awk '{print $1}'); fi
IP=$(echo "$IP" | xargs)
if [ -f "$DIR/../../192.168.1.100.pem" ]; then IP="192.168.1.100"; fi

echo "========================================================"
echo "🚀 Запуск нагрузочного теста чатов (/conversations)"
echo "🎯 Цель: https://$IP:8443"
echo "========================================================"

echo ""
echo "⚠️  Для теста чата нужна ваша активная сессия."
echo "Скопируйте значение куки no_alone_session (или laravel_session) из браузера."
echo "Вставьте её сюда и нажмите Enter:"
read -r SESSION_COOKIE

if [ -z "$SESSION_COOKIE" ]; then
    echo "❌ Ошибка: Кука не введена! Тест отменён."
    exit 1
fi

echo "Начинаем тестирование..."
docker run --rm -i -e BASE_URL=https://$IP:8443 -e COOKIE="laravel_session=$SESSION_COOKIE" grafana/k6 run --insecure-skip-tls-verify - < "$DIR/chat_stress.js"
