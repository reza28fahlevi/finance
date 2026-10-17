# Requirement & Panduan Instalasi — Finance Shafa

Dokumen ini menjelaskan apa saja yang dibutuhkan server, lalu langkah instalasi untuk tiga skenario: **lokal dengan Laragon**, **server produksi tanpa Docker**, dan **Docker**.

Setelah instalasi, jalankan pemeriksaan otomatis:

```bash
php artisan finance:check
```

Perintah ini memeriksa versi PHP, ekstensi, setelan `php.ini`, `.env`, koneksi dan versi database, hak tulis folder, serta aset front-end. Hasilnya: ✔ terpenuhi, ✘ wajib diperbaiki, ! disarankan. Exit code `0` berarti semua requirement wajib terpenuhi.

---

## 1. Requirement

### 1.1 Perangkat lunak server

| Komponen | Versi | Wajib | Keterangan |
|---|---|:-:|---|
| PHP | **≥ 8.3** (teruji 8.4.11) | ✔ | Laravel 13 butuh PHP 8.3+ |
| MySQL | **≥ 8.0.16** (teruji 8.0.30) | ✔ | Versi 8.0.16 ke atas diperlukan agar `CHECK` constraint `journal_lines` benar-benar ditegakkan. Alternatif: MariaDB ≥ 10.4 |
| Web server | Apache 2.4 (mod_rewrite) **atau** Nginx ≥ 1.18 + PHP-FPM | ✔ | Document root **harus** ke folder `public/` |
| Composer | ≥ 2.2 | ✔ | Untuk `composer install` |
| Node.js | **≥ 20.19** (atau ≥ 22.12) + npm | Hanya di mesin build | Untuk build aset (Vite 8, Tailwind 4). **Tidak** dibutuhkan di server produksi bila folder `public/build` ikut di-upload |
| Redis / Memcached | – | ✘ | Tidak dipakai: session, cache, dan queue memakai database/sync |
| Queue worker / Supervisor | – | ✘ | Tidak dibutuhkan: semua proses berjalan sinkron |
| Cron | – | Opsional | Hanya bila posting batch terjadwal diaktifkan (`FINANCE_SCHEDULED_POSTING=true`) |
| ClamAV (`clamscan`) | – | Opsional | Hanya bila pemindaian antivirus lampiran diaktifkan (`FINANCE_ANTIVIRUS_ENABLED=true`) |
| Docker + Compose v2 | Docker ≥ 24 | Opsional | Hanya untuk skenario deploy Docker |

### 1.2 Ekstensi PHP

| Ekstensi | Wajib | Dipakai untuk |
|---|:-:|---|
| `pdo_mysql` | ✔ | Koneksi MySQL |
| `bcmath` | ✔ | Aritmetika uang presisi (debit = kredit tepat sampai sen) |
| `intl` | ✔ | Format tanggal/bulan bahasa Indonesia |
| `mbstring` | ✔ | String multibyte |
| `fileinfo` | ✔ | Deteksi tipe berkas lampiran dari **isi** berkas (bukan dari ekstensi) |
| `openssl` | ✔ | Enkripsi, session, signed URL |
| `tokenizer`, `xml`, `ctype`, `dom`, `session`, `filter`, `json`, `pcre` | ✔ | Kebutuhan Laravel (umumnya sudah bawaan) |
| `opcache` | Disarankan | Performa produksi |
| `curl`, `zip` | Disarankan | Composer & HTTP client |

Ekstensi yang aktif dapat dicek dengan `php -m`.

### 1.3 Setelan `php.ini`

| Setelan | Minimal | Alasan |
|---|---|---|
| `upload_max_filesize` | `10M` | Lampiran maks. 5 MB/berkas |
| `post_max_size` | `60M` | Hingga 10 lampiran per unggahan |
| `max_file_uploads` | `10` | |
| `memory_limit` | `128M` (disarankan `256M`) | |
| `date.timezone` | `Asia/Jakarta` | Konsisten dengan `APP_TIMEZONE` |

Untuk Nginx, set juga `client_max_body_size 60M;`.

### 1.4 Hak tulis folder
User PHP-FPM/Apache harus bisa menulis ke:
- `storage/`, termasuk `storage/app/private`, tempat lampiran bukti transaksi disimpan,
- `bootstrap/cache/`.

### 1.5 Kebutuhan server (perkiraan)
| | Minimal | Disarankan |
|---|---|---|
| CPU | 1 vCPU | 2 vCPU |
| RAM | 1 GB (aplikasi + MySQL) | 2 GB |
| Disk | 2 GB + ruang lampiran | 10 GB+, tergantung volume lampiran (maks. 5 MB/berkas) |

### 1.6 Browser pengguna
Chrome, Edge, Firefox, atau Safari versi terbaru. Tampilan mendukung desktop dan mobile (≥ 360px).

---

## 2. Instalasi lokal dengan Laragon (Windows)

1. **Pilih versi PHP 8.3/8.4.** Di Laragon: Menu → PHP → Version → `php-8.4.x`. Pastikan ekstensi `intl`, `bcmath`, `fileinfo`, dan `pdo_mysql` dicentang (Menu → PHP → Extensions).
2. **Pakai Node.js ≥ 20.19.** Node bawaan Laragon (v18) terlalu lama untuk Vite 8. Unduh Node 22 LTS (zip) dari nodejs.org, ekstrak ke `C:\laragon\bin\nodejs\node-v22`, lalu pilih di Menu → Node.js. Cek dengan `node -v`.
3. **Letakkan project** di salah satu Document Root Laragon (mis. `C:\www\finance-shafa`).
4. **Install dependensi & konfigurasi**
   ```bash
   composer install
   npm install
   copy .env.example .env      # lalu set: APP_ENV=local, APP_DEBUG=true,
                               # APP_URL=http://finance-shafa.test, DB_HOST=127.0.0.1, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   php artisan key:generate
   ```
5. **Buat database** (HeidiSQL atau terminal Laragon):
   ```bash
   mysql -uroot -e "CREATE DATABASE finflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   ```
6. **Migrasi, data awal & aset**
   ```bash
   php artisan migrate --seed     # data inti + data demo
   npm run build
   ```
7. **Virtual host.** Klik **Reload** di Laragon. Pastikan file `C:\laragon\etc\apache2\sites-enabled\auto.finance-shafa.test.conf` berisi `define ROOT "C:/www/finance-shafa/public"` (harus diakhiri **`/public`**).
   > Bila folder sudah didaftarkan Laragon sebelum Laravel terpasang, ROOT bisa menunjuk ke folder project. Akibatnya halaman berupa daftar file dan `.env` bisa diunduh publik. Hapus file `auto.finance-shafa.test.conf`, lalu klik Reload agar Laragon membuatnya ulang.
8. **Verifikasi**
   ```bash
   php artisan finance:check
   ```
   Buka `http://finance-shafa.test` dan login dengan akun demo (lihat [README](../README.md#2-akun-demo)).

---

## 3. Instalasi server produksi (tanpa Docker)

Contoh untuk Ubuntu 22.04/24.04 dengan Nginx dan PHP-FPM 8.4.

### 3.1 Paket sistem
```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
sudo apt install -y nginx mysql-server unzip git \
  php8.4-fpm php8.4-mysql php8.4-bcmath php8.4-intl php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-opcache
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

### 3.2 Database
```sql
CREATE DATABASE finflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'finance'@'localhost' IDENTIFIED BY 'ganti_password_kuat';
GRANT ALL PRIVILEGES ON finflow_db.* TO 'finance'@'localhost';
FLUSH PRIVILEGES;
```

### 3.3 Aplikasi
```bash
# Di mesin build / CI (yang memiliki Node ≥ 20.19)
npm ci && npm run build            # menghasilkan public/build — ikut di-upload

# Di server, mis. /var/www/finance-shafa
composer install --no-dev --optimize-autoloader
cp .env.example .env               # APP_ENV=production, APP_DEBUG=false, APP_URL, DB_* (DB_HOST=127.0.0.1)
php artisan key:generate           # SEKALI saja — jangan diulang setelah ada data
php artisan migrate --force
php artisan db:seed --class=CoreSeeder --force   # sekali, saat instalasi pertama
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
php artisan finance:check
```

### 3.4 Nginx
Gunakan [docker/nginx/default.conf](../docker/nginx/default.conf) sebagai acuan, dengan dua penyesuaian:
- `root /var/www/finance-shafa/public;`
- `fastcgi_pass unix:/run/php/php8.4-fpm.sock;` beserta `SCRIPT_FILENAME $realpath_root$fastcgi_script_name;`

Pasang HTTPS (mis. `certbot --nginx`), lalu set `SESSION_SECURE_COOKIE=true` di `.env` dan jalankan ulang `php artisan config:cache`.

### 3.5 Opsional
- Posting batch terjadwal: set `FINANCE_SCHEDULED_POSTING=true`, lalu tambahkan cron `* * * * * cd /var/www/finance-shafa && php artisan schedule:run >> /dev/null 2>&1`.
- Antivirus: `sudo apt install clamav`, `sudo freshclam`, lalu set `FINANCE_ANTIVIRUS_ENABLED=true`.

### 3.6 Setelah instalasi pertama
1. Login sebagai `admin@finance.test` / `password`, **ganti password**, dan buat akun pengguna sebenarnya (Pengaturan → Pengguna).
2. **Nonaktifkan** akun demo yang tidak dipakai.
3. Sesuaikan **Chart of Accounts**, **Periode Akuntansi**, **Template Jurnal Otomatis**, **Pengaturan Umum**, dan bila perlu **Role & Permission**.
4. Jadwalkan backup (lihat [README §7](../README.md#7-backup--restore)).

---

## 4. Instalasi dengan Docker

Requirement: Docker ≥ 24 dengan Compose v2. PHP, MySQL, dan Node **tidak** perlu dipasang di host.

```bash
cp .env.docker.example .env.docker     # isi DB_PASSWORD = MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD, APP_URL
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show   # salin ke APP_KEY
docker compose up -d
docker compose exec app php artisan finance:check
```

Penjelasan lengkap ada di [README §5](../README.md#5-deploy-dengan-docker).

> Catatan: konfigurasi Docker belum di-build/diuji di mesin pengembangan (Docker tidak terpasang). Lakukan uji build pertama di server target.

---

## 5. Pemecahan masalah

| Gejala | Penyebab | Solusi |
|---|---|---|
| Halaman berupa daftar file / "Index of /" | Document root bukan `public/` | Arahkan document root ke `…/public` (Laragon: lihat §2 langkah 7) |
| Tampilan tanpa gaya (CSS tidak termuat) | Aset belum di-build | `npm run build`, pastikan `public/build/manifest.json` ada |
| `npm run build`: *does not provide an export named 'styleText'* | Node.js terlalu lama | Gunakan Node ≥ 20.19 |
| *could not find driver* | `pdo_mysql` tidak aktif | Aktifkan ekstensi di `php.ini` |
| *Call to undefined function bcadd()* | `bcmath` tidak aktif | Aktifkan ekstensi `bcmath` |
| Nama bulan tampil dalam bahasa Inggris | `intl` tidak aktif / `APP_LOCALE` bukan `id` | Aktifkan `intl`, set `APP_LOCALE=id` |
| Upload lampiran gagal / 413 | Batas upload terlalu kecil | Naikkan `upload_max_filesize`/`post_max_size` (dan `client_max_body_size` di Nginx) |
| *No application encryption key* | `APP_KEY` kosong | `php artisan key:generate` |
| Error 500 setelah mengubah `.env` di produksi | Config masih ter-cache | `php artisan config:cache` |
| Halaman 403 padahal seharusnya boleh | Hak akses peran | Cek Pengaturan → Role & Permission |
