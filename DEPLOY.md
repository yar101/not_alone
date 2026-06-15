# Инструкция по деплою (Релиз)

Этот проект использует Laravel, Vue 3, Vite и PWA. Для того чтобы пользователи всегда получали актуальную версию приложения без проблем с кэшем, необходимо строго соблюдать процесс сборки.

## Шаги для деплоя на боевой сервер (Production)

1. **Получите свежий код из репозитория:**
   ```bash
   git pull origin main
   ```

2. **Обновите зависимости (если необходимо):**
   ```bash
   composer install --optimize-autoloader --no-dev
   npm ci
   ```

3. **Сборка фронтенда (ОБЯЗАТЕЛЬНЫЙ ШАГ):**
   ```bash
   npm run build
   ```
   > **Почему это важно:** Vite скомпилирует ассеты и добавит уникальные хэши в их имена. Плагин `vite-plugin-pwa` обновит файл `public/build/sw.js`, включив в него новые хэши. Только после этого шага браузеры пользователей поймут, что вышла новая версия фронтенда.

4. **Обновление кэшей и базы данных Laravel:**
   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   php artisan optimize
   ```

5. **Перезапуск очередей и Reverb (если обновлялась логика):**
   ```bash
   php artisan queue:restart
   # Если вы используете Supervisor для Reverb, его перезапускать обычно не нужно,
   # но если обновились каналы, можно сделать рестарт:
   # supervisorctl restart reverb
   ```

## Как пользователи получают обновления (PWA)

Благодаря настройкам кэширования в Nginx и логике Service Worker:
- Файл `sw.js` никогда не кэшируется браузером надолго.
- При загрузке страницы браузер проверяет `sw.js`. Если он изменился, в фоновом режиме скачиваются новые файлы (JS, CSS).
- Новый Service Worker мгновенно активируется (`skipWaiting`).
- **Событие контроллера:** Как только новый Service Worker перехватывает управление, в браузере сработает событие `controllerchange`.
- Пользователю будет показано всплывающее уведомление: **"Доступна новая версия. Обновить страницу"**. При нажатии на кнопку страница перезагрузится, и загрузится свежий интерфейс.

---

# Implementation Steps (PWA Update UI)
1. **Translate Keys:**
   - In `lang/en.json` and `lang/ru.json`, add:
     - `app.update.title` ("Update Available" / "Доступно обновление")
     - `app.update.message` ("A new version of the app is available." / "Доступна новая версия приложения.")
     - `app.update.button` ("Refresh Page" / "Обновить страницу")
2. **AppLayout.vue Integration:**
   - Import `h` from `vue`.
   - In the global `onMounted` hook, add a `navigator.serviceWorker.addEventListener('controllerchange', ...)` listener.
   - When the event fires, show an `ElNotification` with the translated texts and a button that calls `window.location.reload()`. Use `duration: 0` to keep the notification on screen until interacted with.

# Verification
- Edit a visible string on the frontend.
- Run `npm run build` and restart the local server if needed.
- In another browser tab that is already open, reload (to trigger the SW update check in background).
- The `controllerchange` event should fire, and the notification should appear prompting for a final reload to apply the new UI.
