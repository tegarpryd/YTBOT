# 🚀 YouTube Multi-Channel Uploader

Selamat datang di **YouTube Multi-Channel Uploader**! Ini adalah platform web berbasis PHP yang dirancang untuk menyederhanakan proses upload video ke berbagai channel YouTube dari berbagai akun Google. Dibuat untuk content creator, manajer media sosial, atau agensi yang menangani banyak channel sekaligus.

Aplikasi ini dibangun dengan PHP native (tanpa framework besar) untuk performa yang ringan dan kemudahan kustomisasi.

---

## ✨ Fitur Utama

-   **Autentikasi Multi-OAuth**: Hubungkan dan kelola beberapa akun Google (profil OAuth) dalam satu dasbor.
-   **Manajemen Multi-Channel**: Secara otomatis mengambil dan menampilkan semua channel YouTube yang terkait dengan setiap akun Google yang terhubung.
-   **Upload Video & Shorts**:
    -   Formulir upload yang intuitif (judul, deskripsi, tag, status privasi).
    -   Mendukung upload video berdurasi panjang dan YouTube Shorts (terdeteksi otomatis untuk video < 60 detik).
    -   Progress bar real-time untuk memantau proses upload.
-   **Panel Admin Lengkap**:
    -   **Dasbor Statistik**: Lihat statistik kunci seperti jumlah pengguna, profil terhubung, total channel, dan jumlah upload.
    -   **Manajemen Pengguna (CRUD)**: Tambah, edit, hapus, dan suspend akun pengguna platform (dengan peran `admin` atau `user`).
    -   **Manajemen Profil & Channel**: Tambah atau hapus profil OAuth dan sinkronkan ulang daftar channel kapan saja.
    -   **Log Aktivitas**: Lacak semua aktivitas penting di platform, mulai dari login pengguna hingga upload video yang berhasil atau gagal.
-   **Keamanan**:
    -   Menggunakan PDO dengan *prepared statements* untuk mencegah SQL Injection.
    -   Token OAuth yang sensitif dienkripsi di dalam database.
    -   Proteksi file-file konfigurasi penting menggunakan `.htaccess`.
-   **Antarmuka Pengguna Modern**: UI yang bersih, responsif, dan mudah digunakan, dibangun dengan Bootstrap, Google Fonts (Inter), dan Font Awesome.

---

## 🛠️ Tumpukan Teknologi (Tech Stack)

-   **Backend**: PHP 8+ (Native)
-   **Database**: MySQL / MariaDB
-   **Web Server**: Apache2 (dengan `mod_rewrite`)
-   **Frontend**: Bootstrap 5, Font Awesome 6, Google Fonts (Inter), JavaScript (untuk AJAX & interaktivitas)
-   **Dependensi**: Google API Client Library for PHP (dikelola via Composer)

---

## 📋 Prasyarat Server

Sebelum memulai, pastikan server Anda memenuhi persyaratan berikut:
-   Web Server (direkomendasikan Apache)
-   PHP 8.0 atau lebih baru, dengan ekstensi:
    -   `pdo_mysql` (untuk koneksi database)
    -   `curl` (untuk Composer dan Guzzle)
    -   `mbstring`
    -   `xml`
    -   `zip`
    -   `gd`
    -   `intl`
-   MySQL 5.7 atau lebih baru (atau MariaDB)
-   Composer 2.x

---

## 🚀 Panduan Instalasi

Ikuti langkah-langkah berikut untuk menginstal dan menjalankan aplikasi di server Anda.

### 1. Dapatkan Kode
Clone repositori ini ke direktori web server Anda (misalnya `/var/www/html/`):
```bash
git clone https://github.com/your-username/youtube-uploader.git
cd youtube-uploader
```

### 2. Instal Dependensi
Jalankan Composer untuk mengunduh pustaka Google API:
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Siapkan Kredensial Google API
Ini adalah langkah yang paling penting.
1.  Buka **[Google Cloud Console](https://console.cloud.google.com/)**.
2.  Buat proyek baru atau pilih yang sudah ada.
3.  Dari menu navigasi, buka **APIs & Services > Library**.
4.  Cari dan **aktifkan** API berikut: **"YouTube Data API v3"**.
5.  Buka **APIs & Services > Credentials**.
6.  Klik **+ CREATE CREDENTIALS** dan pilih **OAuth client ID**.
7.  Pilih **Web application** sebagai tipe aplikasi.
8.  Di bawah **Authorized redirect URIs**, klik **ADD URI** dan masukkan URL lengkap ke file `oauth_callback.php` di server Anda.
    -   Contoh: `http://yourdomain.com/oauth_callback.php`
    -   Untuk development lokal: `http://localhost/youtube-uploader/oauth_callback.php`
9.  Klik **Create**. Salin **Client ID** dan **Client Secret** Anda.

### 4. Konfigurasi Aplikasi
Salin file konfigurasi contoh dan isi dengan detail Anda.
1.  Buka file `config/config.php`.
2.  Isi detail koneksi database Anda: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`.
3.  Tempelkan **Client ID** dan **Client Secret** Anda ke `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET`.
4.  Pastikan `GOOGLE_REDIRECT_URI` cocok persis dengan yang Anda masukkan di Google Cloud Console.
5.  Ubah `ENCRYPTION_KEY` menjadi string acak yang sangat kuat. Anda bisa membuatnya dengan perintah: `openssl rand -base64 32`.

### 5. Siapkan Database
1.  Masuk ke MySQL dan buat database baru sesuai dengan yang Anda definisikan di `DB_NAME`.
    ```sql
    CREATE DATABASE youtube_uploader_db;
    ```
2.  Impor skema tabel dari file `config/database.sql` ke dalam database Anda.
    ```bash
    mysql -u your_db_user -p your_db_name < config/database.sql
    ```
    Ini akan membuat semua tabel dan satu pengguna admin default.

### 6. Konfigurasi Web Server (Apache)
1.  Pastikan modul `rewrite` diaktifkan:
    ```bash
    sudo a2enmod rewrite
    sudo systemctl restart apache2
    ```
2.  Pastikan direktori `uploads/` dapat ditulisi oleh server.
    ```bash
    sudo chown -R www-data:www-data uploads/
    sudo chmod -R 755 uploads/
    ```

---

## 🎮 Panduan Penggunaan Awal

1.  **Akses Aplikasi**: Buka URL proyek Anda di browser. Anda akan diarahkan ke halaman login.
2.  **Login**: Gunakan kredensial admin default:
    -   **Username**: `admin`
    -   **Password**: `password123`
3.  **Ubah Password (Sangat Penting!)**: Setelah login, segera buka halaman **Manajemen User**, edit akun admin, dan ubah password Anda.
4.  **Hubungkan Akun Google**:
    -   Dari sidebar, navigasi ke **Profil OAuth**.
    -   Klik **"Tambah Profil OAuth Baru"**.
    -   Anda akan diarahkan ke halaman persetujuan Google. Pilih akun Google yang ingin Anda hubungkan dan berikan izin.
    -   Setelah berhasil, Anda akan kembali ke dasbor, dan channel dari akun tersebut akan disinkronkan.
5.  **Mulai Upload**: Navigasi ke halaman **Upload Video**, pilih channel tujuan, isi detail video, dan mulailah mengunggah!

---

## 📁 Struktur Direktori

```
.
├── admin/                # File-file untuk panel admin (dasbor, upload, dll.)
├── assets/
│   ├── css/              # File CSS kustom
│   └── js/               # File JavaScript kustom
├── config/
│   ├── config.php        # File konfigurasi utama (DB, Google API, dll.)
│   └── database.sql      # Skema database SQL
├── includes/
│   ├── db.php            # Logika koneksi database (PDO)
│   ├── functions.php     # Fungsi-fungsi pembantu global
│   ├── header.php        # Template header HTML
│   ├── footer.php        # Template footer HTML
│   └── sidebar.php       # Template sidebar navigasi
├── uploads/              # Direktori sementara untuk menyimpan file video (perlu izin tulis)
├── vendor/               # Dependensi Composer (misal: Google API Client)
├── .htaccess             # Aturan keamanan dan rewrite untuk Apache
├── composer.json         # Definisi proyek dan dependensi Composer
├── index.php             # Titik masuk utama aplikasi
├── login.php             # Halaman login pengguna
├── logout.php            # Skrip untuk proses logout
└── oauth_callback.php    # URI pengalihan untuk menangani respons dari Google
```

---

## 🔒 Catatan Keamanan

-   **Jangan pernah membagikan file `config.php` Anda.**
-   Selalu gunakan **password yang kuat** untuk akun pengguna dan database.
-   Pastikan server Anda dikonfigurasi dengan aman dan selalu diperbarui.
-   File `.htaccess` yang disertakan sudah memblokir akses langsung ke file-file sensitif, namun pastikan konfigurasi Apache Anda (`AllowOverride All`) mengizinkannya berfungsi.