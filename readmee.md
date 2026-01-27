# 📖 Panduan Setup Proyek Backend Face Recognition

> **Panduan lengkap untuk setup proyek dari awal setelah clone**  
> Cocok untuk pemula hingga developer senior

---

## 📋 Daftar Isi

1. [Persyaratan Sistem](#-persyaratan-sistem)
2. [Langkah Instalasi](#-langkah-instalasi)
3. [Konfigurasi Environment](#-konfigurasi-environment)
4. [Menjalankan Proyek](#-menjalankan-proyek)
5. [Struktur Proyek](#-struktur-proyek)
6. [API Endpoints](#-api-endpoints)
7. [Troubleshooting](#-troubleshooting)
8. [Tips untuk Pemula](#-tips-untuk-pemula)

---

## 🖥 Persyaratan Sistem

Pastikan Anda sudah menginstall software berikut:

| Software     | Versi Minimum | Cara Cek Versi  |
| ------------ | ------------- | --------------- |
| **PHP**      | 8.2+          | `php -v`        |
| **Composer** | 2.x           | `composer -V`   |
| **Node.js**  | 18.x+         | `node -v`       |
| **NPM**      | 9.x+          | `npm -v`        |
| **Git**      | 2.x+          | `git --version` |

### 💡 Rekomendasi Tools

-   **Laragon** (Windows) - Paket lengkap PHP, MySQL, Apache
-   **XAMPP** (Cross-platform) - Alternatif Laragon
-   **VS Code** - Code editor dengan extensions Laravel

---

## 🚀 Langkah Instalasi

### Langkah 1: Clone Repository

```bash
git clone <url-repository>
cd beckand-face
```

### Langkah 2: Install Dependencies PHP (Composer)

```bash
composer install
```

> ⏱ Proses ini memakan waktu 2-5 menit tergantung koneksi internet

### Langkah 3: Install Dependencies JavaScript (NPM)

```bash
npm install
```

### Langkah 4: Salin File Environment

```bash
# Windows (Command Prompt)
copy .env.example .env

# Windows (PowerShell)
Copy-Item .env.example .env

# Linux/Mac
cp .env.example .env
```

### Langkah 5: Generate Application Key

```bash
php artisan key:generate
```

> ✅ Perintah ini akan mengisi `APP_KEY` di file `.env` secara otomatis

### Langkah 6: Setup Database

Proyek ini menggunakan **SQLite** secara default. Buat file database:

```bash
# Windows (Command Prompt)
type nul > database\database.sqlite

# Windows (PowerShell)
New-Item -Path "database\database.sqlite" -ItemType File

# Linux/Mac
touch database/database.sqlite
```

### Langkah 7: Jalankan Migrasi Database

```bash
php artisan migrate
```

> 📝 Ini akan membuat semua tabel yang diperlukan

### Langkah 8: (Opsional) Jalankan Seeder

Jika ada data dummy yang perlu di-seed:

```bash
php artisan db:seed
```

### Langkah 9: Buat Storage Link

```bash
php artisan storage:link
```

> 🔗 Perintah ini membuat symbolic link dari `storage/app/public` ke `public/storage`

---

## ⚙ Konfigurasi Environment

Buka file `.env` dan sesuaikan konfigurasi berikut:

### Konfigurasi Dasar

```env
APP_NAME="Face Recognition Backend"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
```

### Konfigurasi Database

```env
# Database SQLite (Default)
DB_CONNECTION=sqlite

# Atau jika ingin menggunakan MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=nama_database
# DB_USERNAME=root
# DB_PASSWORD=
```

### Konfigurasi Email (Jika Diperlukan)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

---

## ▶ Menjalankan Proyek

### Cara Cepat (Development Server)

```bash
php artisan serve
```

Server akan berjalan di: **http://127.0.0.1:8000**

### Cara Lengkap (Dengan Queue & Vite)

Buka 3 terminal terpisah:

**Terminal 1 - Laravel Server:**

```bash
php artisan serve
```

**Terminal 2 - Queue Worker:**

```bash
php artisan queue:listen
```

**Terminal 3 - Vite Dev Server:**

```bash
npm run dev
```

### Alternatif: Satu Perintah (Concurrently)

```bash
composer run dev
```

> ⚡ Perintah ini menjalankan server, queue, dan vite secara bersamaan

---

## 📁 Struktur Proyek

```
beckand-face/
├── app/
│   ├── Http/
│   │   ├── Controllers/    # Controller aplikasi
│   │   └── Middleware/     # Middleware (auth, admin, employee)
│   └── Models/             # Model Eloquent
├── config/                 # File konfigurasi
├── database/
│   ├── migrations/         # File migrasi database
│   ├── seeders/            # Data seeder
│   └── database.sqlite     # Database SQLite
├── public/                 # File publik & entry point
├── resources/              # Views, assets
├── routes/
│   ├── api.php             # Rute API (utama)
│   ├── auth.php            # Rute autentikasi
│   └── web.php             # Rute web
├── storage/                # File upload, logs, cache
├── .env                    # Environment variables (JANGAN commit!)
├── .env.example            # Template environment
├── composer.json           # Dependencies PHP
└── package.json            # Dependencies Node.js
```

---

## 🔌 API Endpoints

### Autentikasi

| Method | Endpoint               | Deskripsi              |
| ------ | ---------------------- | ---------------------- |
| POST   | `/api/login`           | Login user             |
| POST   | `/api/logout`          | Logout user            |
| POST   | `/api/forgot-password` | Request reset password |
| POST   | `/api/reset-password`  | Reset password         |

### Admin Routes (`/api/admin/*`)

| Method | Endpoint             | Deskripsi              |
| ------ | -------------------- | ---------------------- |
| GET    | `/users`             | Daftar semua user      |
| POST   | `/users`             | Tambah user baru       |
| GET    | `/users/{id}`        | Detail user            |
| PUT    | `/users/{id}`        | Update user            |
| DELETE | `/users/{id}`        | Hapus user             |
| GET    | `/attendance-logs`   | Log absensi            |
| GET    | `/dashboard-summary` | Ringkasan dashboard    |
| GET    | `/leave-requests`    | Daftar permintaan cuti |
| GET    | `/holidays`          | Daftar hari libur      |

### Employee Routes (`/api/employee/*`)

| Method | Endpoint                  | Deskripsi       |
| ------ | ------------------------- | --------------- |
| GET    | `/profile`                | Profil karyawan |
| POST   | `/profile`                | Update profil   |
| POST   | `/check-in`               | Absen masuk     |
| POST   | `/check-out`              | Absen pulang    |
| POST   | `/leave-request`          | Ajukan cuti     |
| GET    | `/leave-requests/history` | Riwayat cuti    |
| GET    | `/attendances/history`    | Riwayat absensi |

---

## ❓ Troubleshooting

### Error: "Class not found"

```bash
composer dump-autoload
```

### Error: "SQLSTATE[HY000]: General error: 1 no such table"

```bash
php artisan migrate:fresh
```

> ⚠️ **Peringatan:** Ini akan menghapus semua data dan membuat ulang tabel

### Error: "The stream or file could not be opened"

```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache

# Windows - Jalankan CMD sebagai Administrator:
icacls storage /grant Everyone:F /T
icacls bootstrap\cache /grant Everyone:F /T
```

### Error: "APP_KEY is missing"

```bash
php artisan key:generate
```

### Error: "CORS blocked"

Periksa file `config/cors.php` dan sesuaikan:

```php
'allowed_origins' => ['*'], // atau domain frontend spesifik
```

### Port 8000 sudah digunakan?

```bash
php artisan serve --port=8001
```

---

## 💡 Tips untuk Pemula

### 1. Memahami Artisan Commands

```bash
# Lihat semua perintah yang tersedia
php artisan list

# Bantuan untuk perintah tertentu
php artisan help migrate
```

### 2. Melihat Routes yang Terdaftar

```bash
php artisan route:list
```

### 3. Clear Cache saat Error Aneh

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Atau sekaligus:
php artisan optimize:clear
```

### 4. Debugging dengan Tinker

```bash
php artisan tinker
```

Contoh penggunaan:

```php
>>> User::all()
>>> User::find(1)
>>> User::where('role', 'admin')->get()
```

### 5. Cek Log Error

File log ada di: `storage/logs/laravel.log`

---

## 📞 Kontak & Bantuan

Jika mengalami kendala:

1. Baca dokumentasi Laravel: [https://laravel.com/docs](https://laravel.com/docs)
2. Cek issues di repository
3. Tanyakan ke tim development

---

## 📝 Checklist Setup

Gunakan checklist ini untuk memastikan setup berhasil:

-   [ ] Clone repository ✓
-   [ ] `composer install` berhasil
-   [ ] `npm install` berhasil
-   [ ] File `.env` sudah dibuat
-   [ ] `APP_KEY` sudah di-generate
-   [ ] Database SQLite sudah dibuat
-   [ ] `php artisan migrate` berhasil
-   [ ] `php artisan storage:link` berhasil
-   [ ] Server berjalan di `localhost:8000`
-   [ ] Bisa mengakses API `/api/login`

---

**🎉 Selamat! Proyek backend sudah siap digunakan.**

---

_Terakhir diperbarui: Januari 2026_
