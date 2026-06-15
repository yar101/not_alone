# Docker Compose Development Environment Walkthrough

We have successfully configured a fully integrated Docker Compose environment for local development. Everything runs securely over HTTPS matching your host machine's LAN IP and local certificates.

## 🚀 How to Run the Environment

You can start the development stack in two ways:

1. **Via Composer shortcut:**
   ```bash
   composer run docker-dev
   ```

2. **Directly via the startup script:**
   ```bash
   ./docker-dev.sh
   ```

The startup script will automatically:
- Detect the host's LAN IP address.
- Verify that your local SSL certificates (`<IP>.pem` / `<IP>-key.pem`) exist. If not, it will fall back to using your `192.168.1.100` certificates.
- Export `DEV_HOST` so Docker Compose can mount the certificates.
- Start all services: PostgreSQL, Redis, Mailpit, PHP-FPM/Nginx (App), Queue worker, Scheduler, Reverb WebSocket, and Vite.

---

## 🌐 Dev Services Access URLs

Once started, the services are accessible at:

| Service | Access URL | Port (Host -> Container) | Notes |
| :--- | :--- | :--- | :--- |
| **Web Application** | `https://192.168.1.100:8443` | `8443 -> 8443` | Secured via Nginx with your local certificates. |
| **Vite Dev Server** | `https://192.168.1.100:5173` | `5173 -> 5173` | Hot Module Replacement (HMR) operates securely over SSL. |
| **Mailpit UI** | `http://192.168.1.100:8025` | `8025 -> 8025` | Interface for inspecting outgoing dev emails. |
| **PostgreSQL DB** | `192.168.1.100` | `5433 -> 5432` | Exposed to host. Accessible via DBeaver or raw SQL clients on port 5433. |
| **Redis Cache** | `192.168.1.100` | `6379 -> 6379` | Exposed to host. |

---

## 🛠️ Helpful Commands

If you need to run Laravel commands, execute them directly inside the running app container using `docker compose exec`:

- **Run migrations manually:**
  ```bash
  docker compose exec app php artisan migrate
  ```
- **Seed the database:**
  ```bash
  docker compose exec app php artisan db:seed
  ```
- **Clear application cache:**
  ```bash
  docker compose exec app php artisan cache:clear
  ```
- **Enter interactive Tinker shell:**
  ```bash
  docker compose exec app php artisan tinker
  ```
- **Run tests:**
  ```bash
  docker compose exec app php artisan test
  ```

---

## 🗄️ Database Collation Compatibility
By running PostgreSQL in the container (`db` service), all database files and collations are managed under the container OS's stable library environment. This eliminates the collation version mismatch warnings you previously encountered on host-level OS package updates.

> [!NOTE]
> Database files are persisted inside a named Docker volume (`db_data`). If you ever need to perform a clean database reset, you can run:
> `docker compose down -v` to destroy the volume and restart.

---

## ⚠️ Решение частых проблем (Linux/Fedora)

### Ошибка прав доступа (Permission Denied)
Если при открытии страницы в браузере вы получаете ошибку `Failed to open stream: Permission denied` для папок в `storage/` или `bootstrap/cache`, это означает, что пользователь `www-data` внутри Docker-контейнера не имеет прав на запись в смонтированные с хоста директории.

**Решение:**
Запустите следующую команду на хост-машине:
```bash
chmod -R 777 storage bootstrap/cache
```
