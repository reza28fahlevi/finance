# Finance Shafa — Sistem Keuangan (Input → General Ledger)

Aplikasi akuntansi monolith **Laravel 13** yang mengalirkan data dari input transaksi sampai Buku Besar dalam lima tahap: Master Data → Transaksi & Lampiran → Approval (maker–checker) → Journal Entry → Posting ke General Ledger. Rancangan lengkap ada di [RANCANGAN.md](RANCANGAN.md).

**Stack:** PHP 8.3+ · Laravel 13 · MySQL 8 (InnoDB) · Blade + Tailwind CSS 4 + Alpine.js (Vite) · tanpa Redis. Session, cache, dan queue memakai driver `database`/`sync`.

## Daftar isi
1. [Instalasi lokal](#1-instalasi-lokal)
2. [Akun demo](#2-akun-demo)
3. [Konfigurasi penting](#3-konfigurasi-penting)
4. [Menjalankan test](#4-menjalankan-test)
5. [Deploy dengan Docker](#5-deploy-dengan-docker)
6. [Deploy manual (tanpa Docker)](#6-deploy-manual-tanpa-docker)
7. [Backup & restore](#7-backup--restore)
8. [Struktur kode](#8-struktur-kode)

---

## 1. Instalasi lokal

Prasyarat: PHP ≥ 8.3 (ekstensi `pdo_mysql`, `bcmath`, `intl`, `mbstring`, `fileinfo`), Composer 2, MySQL 8, **Node.js ≥ 20.19** (untuk build aset Vite 8).

```bash
composer install
npm install

cp .env.example .env            # sesuaikan DB_*, APP_URL, APP_ENV=local, APP_DEBUG=true
php artisan key:generate

mysql -uroot -e "CREATE DATABASE finflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed      # CoreSeeder + DemoSeeder (data contoh)

npm run build                   # atau `npm run dev` saat mengembangkan UI
php artisan serve               # http://localhost:8000
```

- `php artisan migrate:fresh --seed` mengembalikan database ke data demo awal.
- Hanya data inti (tanpa transaksi contoh): `php artisan db:seed --class=CoreSeeder`.
- `DemoSeeder` otomatis dilewati bila `APP_ENV=production`.

## 2. Akun demo

Semua akun memakai password **`password`**. Ganti password, atau nonaktifkan akun demo, sebelum dipakai di produksi.

| Peran | Email | Bisa |
|---|---|---|
| Admin | `admin@finance.test` | Kelola COA, periode, template jurnal, pengguna, pengaturan umum; buka kembali periode |
| Staff Finance (Maker) | `staff@finance.test`, `staff2@finance.test` | Input transaksi + lampiran, submit, revisi, batalkan |
| Supervisor (Checker) | `supervisor@finance.test` | Approve/Reject (≤ ambang bila approval bertingkat aktif) |
| Manager Finance (Checker) | `manager@finance.test` | Approve/Reject semua nominal |
| Akuntan | `akuntan@finance.test` | Jurnal manual, posting & posting batch, jurnal pembalik, tutup periode |
| Auditor | `auditor@finance.test` | Read-only: transaksi, approval, jurnal, buku besar, audit log |

Data demo berisi:
- periode 3 bulan lalu (CLOSED) dan bulan berjalan (OPEN),
- setoran modal awal,
- transaksi dalam berbagai status: Draft, Pending, Rejected, Journaled, Cancelled,
- jurnal Draft yang siap diposting batch.

## 3. Konfigurasi penting

Nilai default ada di `.env` (`FINANCE_*`, dibaca `config/finance.php`). Sebagian besar bisa diubah saat runtime lewat menu **Pengaturan → Pengaturan Umum** (tersimpan di tabel `settings`).

| Variabel | Default | Keterangan |
|---|---|---|
| `FINANCE_COMPANY_NAME` | – | Nama perusahaan di UI |
| `FINANCE_JOURNAL_REF_FORMAT` | `JV-{YYYY}-{MM}-{SEQ:4}` | Format nomor jurnal |
| `FINANCE_TRX_REF_FORMAT` | `{TYPE}-{YYYY}-{MM}-{SEQ:4}` | `{TYPE}` = BKM / BKK / INV-IN / INV-OUT |
| `FINANCE_ATTACHMENT_REQUIRED_ABOVE` | `1000000` | Nominal di atas ini wajib minimal 1 lampiran saat submit |
| `FINANCE_ATTACHMENT_MAX_KB` | `5120` | Batas ukuran per lampiran (PDF/JPG/PNG) |
| `FINANCE_ATTACHMENT_URL_TTL` | `30` | Masa berlaku link unduh bertoken (menit) |
| `FINANCE_APPROVAL_TIERED` / `…_MANAGER_THRESHOLD` | `false` / `10000000` | Approval bertingkat: ≥ ambang wajib Manager |
| `FINANCE_AUTO_POST_ON_APPROVE` | `false` | `true` = jurnal otomatis langsung diposting saat approve |
| `FINANCE_SCHEDULED_POSTING` | `false` | `true` = `finance:post-batch` dijadwalkan setiap hari pukul 23:30 |
| `FINANCE_ANTIVIRUS_ENABLED` | `false` | Pindai lampiran dengan ClamAV (`clamscan`) |

Aturan bisnis yang dijaga sistem:
- debit = kredit di setiap jurnal (presisi `DECIMAL(18,2)` + bcmath),
- jurnal POSTED immutable dan dikoreksi lewat jurnal pembalik,
- tidak ada transaksi, jurnal, atau posting di periode CLOSED,
- maker ≠ checker,
- audit log append-only untuk setiap perubahan status dan data master,
- lampiran disimpan di `storage/app/private` dan diunduh lewat signed URL,
- posting idempotent dalam satu DB transaction,
- nomor referensi atomik.

## 4. Menjalankan test

Test memakai database MySQL **`finance_db_test`** (lihat `phpunit.xml`).

```bash
mysql -uroot -e "CREATE DATABASE finance_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test                       # 190 test (unit + feature)
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Uji tambahan berikut butuh server dev berisi data demo (`php artisan serve --port=8123`):

```bash
npm run test:e2e                                  # E2E browser (Chrome headless)
bash tests/e2e/smoke-pages.sh                     # semua halaman × 6 peran
php tests/e2e/concurrency.php sequence            # penomoran paralel
php tests/e2e/concurrency.php posting             # posting ganda paralel
```

Laporan hasil pengujian: [docs/LAPORAN_UNIT_TEST.md](docs/LAPORAN_UNIT_TEST.md) dan [docs/LAPORAN_FUNCTIONAL_TEST.md](docs/LAPORAN_FUNCTIONAL_TEST.md).

## 5. Deploy dengan Docker

Komponen: `Dockerfile` multi-stage (Composer → build aset Node → **PHP-FPM 8.4 tanpa Node** → Nginx) dan `docker-compose.yml` (service `app`, `web`, `mysql`, serta `scheduler` opsional).

```bash
cp .env.docker.example .env.docker
# Isi password DB (DB_PASSWORD = MYSQL_PASSWORD), MYSQL_ROOT_PASSWORD, APP_URL

docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
# Salin hasilnya ke APP_KEY di .env.docker

docker compose up -d
# Aplikasi: http://localhost:8080  (ubah port dengan WEB_PORT=80 docker compose up -d)
```

Saat container `app` start, entrypoint melakukan hal berikut:
1. menunggu MySQL siap,
2. `migrate --force`,
3. menjalankan `CoreSeeder` bila tabel users masih kosong (`SEED_ON_FIRST_RUN=true`),
4. `storage:link`,
5. `config:cache`, `route:cache`, `view:cache`.

Data persisten tersimpan di tiga volume: `mysql-data` (database), `attachments` (`storage/app/private`), dan `logs`.

Posting batch terjadwal (opsional): set `FINANCE_SCHEDULED_POSTING=true` lalu jalankan `docker compose --profile scheduler up -d`.

Update versi aplikasi: `git pull && docker compose build && docker compose up -d` (migrasi berjalan otomatis).

> HTTPS: pasang reverse proxy (mis. Caddy/Traefik/Nginx host) di depan service `web`, lalu set `SESSION_SECURE_COOKIE=true` dan `APP_URL=https://…`.

## 6. Deploy manual (tanpa Docker)

Sesuai RANCANGAN.md 11.1: satu server dengan Nginx/Apache + PHP-FPM + MySQL, tanpa Redis, tanpa queue worker, dan tanpa Node di server.

```bash
# Di mesin build/CI
npm ci && npm run build           # hasil: public/build (ikut di-upload)

# Di server
composer install --no-dev --optimize-autoloader
cp .env.example .env              # APP_ENV=production, APP_DEBUG=false, isi DB_*
php artisan key:generate          # sekali saja
php artisan migrate --force
php artisan db:seed --class=CoreSeeder --force   # sekali, saat instalasi pertama
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Document root web server diarahkan ke `public/`.
- Folder `storage/` dan `bootstrap/cache/` harus bisa ditulis oleh user PHP-FPM.
- Opsional, untuk posting batch terjadwal, tambahkan cron: `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`.

## 7. Backup & restore

Yang perlu dibackup: **database** dan **folder lampiran** (`storage/app/private`).

```bash
# Manual / cron harian
mysqldump --single-transaction --routines -u USER -p finflow_db | gzip > backup/db-$(date +%F).sql.gz
tar czf backup/lampiran-$(date +%F).tar.gz -C storage/app private

# Docker
docker compose exec mysql sh -c 'mysqldump --single-transaction -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' | gzip > db-$(date +%F).sql.gz
docker run --rm -v finance-shafa_attachments:/data -v "$PWD":/backup alpine tar czf /backup/lampiran-$(date +%F).tar.gz -C /data .

# Restore database
gunzip < db-YYYY-MM-DD.sql.gz | mysql -u USER -p finflow_db
```

Simpan salinan backup di luar server (offsite), dan uji restore secara berkala.

## 8. Struktur kode

| Lokasi | Isi |
|---|---|
| `app/Enums` | Role (+ matriks permission), tipe/status transaksi, status/sumber jurnal, tipe akun |
| `app/Services` | `TransactionService`, `AttachmentService`, `ApprovalService`, `JournalService`, `PostingService`, `LedgerService`, `PeriodService`, `SequenceService`, `AuditLogger` |
| `app/Support` | `Money` (aritmetika uang bcmath), `Settings` (Pengaturan Umum), `Navigation` (menu per peran) |
| `app/Policies`, `app/Providers/AppServiceProvider.php` | RBAC: Gate per permission + `TransactionPolicy` |
| `app/Http/Controllers` | Controller per modul (Master Data, Transaksi, Approval, Jurnal, GL, Pengaturan) |
| `database/migrations` | Skema sesuai ERD (RANCANGAN.md 6) + CHECK constraint `journal_lines` |
| `database/seeders` | `CoreSeeder` (data inti), `DemoSeeder` (data contoh lewat service) |
| `resources/views` | Blade dengan tema *Velvet Glass* dari `template_mockup/` |
| `tests/Unit`, `tests/Feature`, `tests/e2e` | Unit test, functional test HTTP, E2E browser & uji konkurensi |
| `Dockerfile`, `docker/`, `docker-compose.yml` | Deploy container |
