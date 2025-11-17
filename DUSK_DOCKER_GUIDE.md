# Laravel Dusk Testing dengan Docker

Panduan lengkap untuk menjalankan Laravel Dusk E2E tests di dalam Docker environment.

## 📋 Daftar Isi

- [Arsitektur](#arsitektur)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Cara Menjalankan Tests](#cara-menjalankan-tests)
- [Debugging Tests](#debugging-tests)
- [Troubleshooting](#troubleshooting)
- [Advanced Usage](#advanced-usage)

---

## 🏗️ Arsitektur

Setup ini menggunakan Docker Compose dengan service-service berikut:

```
┌─────────────────────────────────────────────────────────────┐
│                     Docker Network                          │
│                   (laravel-network)                         │
│                                                             │
│  ┌──────────┐   ┌──────────┐   ┌────────────┐            │
│  │   App    │───│  Nginx   │───│  Selenium  │            │
│  │ (PHP-FPM)│   │  (8100)  │   │  Chrome    │            │
│  └────┬─────┘   └──────────┘   └─────┬──────┘            │
│       │                               │                    │
│       │         ┌──────────┐          │                    │
│       └────────→│  MySQL   │          │                    │
│                 │   (db)   │          │                    │
│                 └──────────┘          │                    │
│                                       │                    │
│                 ┌─────────────────────┘                    │
│                 ↓                                          │
│         Dusk Tests Run Here                               │
│         - Port 4444: WebDriver API                        │
│         - Port 7900: VNC Viewer                           │
└─────────────────────────────────────────────────────────────┘
```

### Services:

1. **app** - Laravel application (PHP 8.3-FPM)
2. **nginx** - Web server (port 8100)
3. **db** - MySQL 8.0 database
4. **selenium** - Selenium Standalone Chrome untuk browser testing
5. **dusk** - Service khusus untuk running tests (optional, menggunakan profile)

---

## ✅ Prerequisites

- Docker Desktop atau Docker Engine installed
- Docker Compose v2.x
- Minimal 4GB RAM tersedia untuk Docker
- Port 4444 dan 7900 tidak digunakan oleh aplikasi lain

---

## 🚀 Quick Start

### 1. Start Services

```bash
# Start semua services termasuk Selenium
docker-compose up -d

# Atau start specific services saja
docker-compose up -d app db nginx selenium
```

### 2. Run Tests (Cara Termudah)

```bash
# Menggunakan helper script (RECOMMENDED)
./run-dusk-tests.sh

# Run specific test file
./run-dusk-tests.sh tests/Browser/LoginTest.php

# Run specific test by name
./run-dusk-tests.sh --filter "user can login"
```

### 3. Watch Tests via VNC

Buka browser dan akses:
```
http://localhost:7900
Password: secret
```

Anda akan melihat Chrome browser menjalankan tests secara real-time!

---

## 🧪 Cara Menjalankan Tests

### Metode 1: Helper Script (Recommended)

Script `run-dusk-tests.sh` otomatis handle semua setup:

```bash
# Run all tests
./run-dusk-tests.sh

# Run specific test file
./run-dusk-tests.sh tests/Browser/LoginTest.php

# Run with filter
./run-dusk-tests.sh --filter "login"

# Run in non-headless mode (bisa lihat browser)
DUSK_HEADLESS_DISABLED=true ./run-dusk-tests.sh
```

### Metode 2: Manual Commands

```bash
# 1. Start services
docker-compose up -d app db nginx selenium

# 2. Copy environment config
docker-compose exec app cp .env.dusk.docker .env.dusk.local

# 3. Run migrations
docker-compose exec app php artisan migrate --env=dusk.local --force

# 4. Seed database (jika perlu)
docker-compose exec app php artisan db:seed --class=RoleSeeder --env=dusk.local

# 5. Run tests
docker-compose exec app php artisan dusk
```

### Metode 3: Menggunakan Dusk Service

```bash
# Note: Dusk service menggunakan profile "testing"
docker-compose --profile testing run --rm dusk php artisan dusk
```

---

## 🔍 Debugging Tests

### 1. Watch Tests via VNC

Cara paling mudah untuk debug adalah melihat test berjalan secara live:

1. Start Selenium container: `docker-compose up -d selenium`
2. Buka browser: `http://localhost:7900`
3. Password: `secret`
4. Run tests dan lihat eksekusinya real-time!

### 2. Disable Headless Mode

```bash
# Set environment variable
DUSK_HEADLESS_DISABLED=true ./run-dusk-tests.sh

# Atau via docker-compose exec
docker-compose exec -e DUSK_HEADLESS_DISABLED=true app php artisan dusk
```

### 3. Check Screenshots & Logs

Jika test gagal, Laravel Dusk otomatis create:

**Screenshots:**
```bash
tests/Browser/screenshots/
```

**Console Logs:**
```bash
tests/Browser/console/
```

Lihat file-file ini untuk debug masalah:
```bash
ls -la tests/Browser/screenshots/
ls -la tests/Browser/console/
```

### 4. Check Selenium Logs

```bash
# View Selenium container logs
docker-compose logs selenium

# Follow logs real-time
docker-compose logs -f selenium
```

### 5. Interactive Shell

Jika perlu debug lebih dalam:

```bash
# Masuk ke app container
docker-compose exec app bash

# Di dalam container, jalankan tests
php artisan dusk --filter "specific test"

# Atau run PHP tinker
php artisan tinker
```

---

## ⚠️ Troubleshooting

### Problem: "Connection refused to selenium:4444"

**Solution:**
```bash
# Pastikan Selenium running
docker-compose ps selenium

# Restart Selenium
docker-compose restart selenium

# Check Selenium health
docker-compose exec app curl -v http://selenium:4444/status
```

### Problem: "No such host: selenium"

**Solution:**
```bash
# Pastikan semua services di network yang sama
docker-compose ps

# Restart network
docker-compose down
docker-compose up -d
```

### Problem: Tests Timeout

**Solution:**
```bash
# Increase wait time di test
$browser->waitFor('.selector', 30); // 30 seconds

# Atau increase shared memory
# Edit docker-compose.yml:
selenium:
  shm_size: 4gb  # Increase dari 2gb
```

### Problem: "Class 'RoleSeeder' not found"

**Solution:**
```bash
# Run composer dump-autoload
docker-compose exec app composer dump-autoload

# Atau create RoleSeeder jika belum ada
docker-compose exec app php artisan make:seeder RoleSeeder
```

### Problem: Chrome Crash

**Symptoms:** Chrome crashes atau tests gagal dengan segmentation fault

**Solution:**
```bash
# 1. Increase shared memory di docker-compose.yml
selenium:
  shm_size: 4gb  # atau lebih

# 2. Atau disable /dev/shm
selenium:
  volumes:
    - /dev/shm:/dev/shm
```

### Problem: Database Connection Refused

**Solution:**
```bash
# Check DB container
docker-compose ps db

# Restart DB
docker-compose restart db

# Check environment
docker-compose exec app cat .env.dusk.local | grep DB_
```

### Problem: Port Already in Use

**Symptoms:** `Bind for 0.0.0.0:4444 failed: port is already allocated`

**Solution:**
```bash
# Check apa yang menggunakan port
lsof -i :4444  # macOS/Linux
netstat -ano | findstr :4444  # Windows

# Kill process atau ubah port di docker-compose.yml
selenium:
  ports:
    - "4445:4444"  # Gunakan port berbeda
```

---

## 🔧 Advanced Usage

### Custom Chrome Options

Edit `tests/DuskTestCase.php` untuk menambah Chrome options:

```php
protected function driver(): RemoteWebDriver
{
    $options = (new ChromeOptions)->addArguments([
        '--window-size=1920,1080',
        '--disable-gpu',
        '--headless=new',
        '--no-sandbox',           // Tambahan
        '--disable-dev-shm-usage', // Tambahan
        '--disable-extensions',    // Tambahan
    ]);

    // ... rest of code
}
```

### Parallel Testing

Untuk run multiple tests secara parallel:

```bash
# Scale Selenium container
docker-compose up -d --scale selenium=3

# Run tests dengan parallel option (jika supported)
docker-compose exec app php artisan dusk --parallel
```

### CI/CD Integration

**GitHub Actions Example:**

```yaml
name: Dusk Tests

on: [push, pull_request]

jobs:
  dusk:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Start Docker Compose
        run: docker-compose up -d

      - name: Wait for services
        run: sleep 10

      - name: Run Dusk Tests
        run: ./run-dusk-tests.sh

      - name: Upload Screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: dusk-screenshots
          path: tests/Browser/screenshots
```

### Using Different Chrome Versions

Edit `docker-compose.yml`:

```yaml
selenium:
  # Specific version
  image: selenium/standalone-chrome:120.0

  # Or use Chromium (for Apple Silicon)
  image: selenium/standalone-chromium:latest
```

### Performance Tuning

```yaml
selenium:
  image: selenium/standalone-chrome:latest
  shm_size: 4gb  # Increase untuk stability
  environment:
    - SE_NODE_MAX_SESSIONS=5     # Max concurrent sessions
    - SE_NODE_SESSION_TIMEOUT=300 # Timeout dalam seconds
    - SE_VNC_NO_PASSWORD=1        # Remove VNC password
```

---

## 📊 Test Coverage

Running tests dengan coverage:

```bash
docker-compose exec app php artisan dusk --coverage
```

---

## 🎯 Best Practices

1. **Always Use Helper Script** - `./run-dusk-tests.sh` untuk consistency
2. **Watch via VNC** - Debug lebih mudah dengan visual feedback
3. **Clean Database** - Tests menggunakan `DatabaseMigrations` trait
4. **Use Descriptive Selectors** - Hindari generic class names
5. **Add Wait Conditions** - Gunakan `waitFor()` untuk dynamic content
6. **Keep Tests Isolated** - Setiap test harus bisa run independently
7. **Screenshot on Failure** - Sudah otomatis, check `tests/Browser/screenshots/`

---

## 📚 Resources

- [Laravel Dusk Documentation](https://laravel.com/docs/dusk)
- [Selenium Docker Images](https://github.com/SeleniumHQ/docker-selenium)
- [Chrome DevTools Protocol](https://chromedevtools.github.io/devtools-protocol/)

---

## 🤝 Contributing

Jika menemukan issue atau punya improvement:

1. Check troubleshooting section dulu
2. Check Selenium logs: `docker-compose logs selenium`
3. Watch test via VNC untuk debug visual
4. Create issue dengan detail lengkap (logs, screenshots, steps to reproduce)

---

## 📝 Changelog

### v1.0.0 (Current)
- ✅ Setup Selenium Standalone Chrome
- ✅ Docker Compose integration
- ✅ VNC viewer support
- ✅ Helper script untuk running tests
- ✅ Comprehensive documentation
- ✅ Support untuk local dan Docker environments

---

**Happy Testing!** 🎉
