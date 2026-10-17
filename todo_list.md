## Fase 0 — Persiapan
- [x] init project laravel 13 dengan mysql
- [x] untuk css nya pakai tailwind (via Vite) + Alpine.js untuk interaktivitas ringan
- [x] install Laravel Breeze (auth session-based)
- [x] konfigurasi .env: koneksi MySQL, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` (tanpa Redis)
- [x] pastikan folder /template_mockup tersedia sebagai acuan tema

## Fase 1 — Database, Auth & Master Data
- [x] buat core migration seeds
  - [x] migration: users (kolom `role`), accounts, accounting_periods, transactions, attachments, approvals, journal_entries, journal_lines, gl_entries, account_balances, audit_logs, sequences (nomor referensi), settings, journal_templates
  - [x] semua kolom uang `DECIMAL(18,2)`; constraint CHECK journal_lines (debit/kredit); unik `(account_id, period_id)` di account_balances
  - [x] seeder: user contoh per role (Admin, Staff Finance, Supervisor/Manager, Akuntan, Auditor), COA contoh, periode berjalan (OPEN), template jurnal otomatis, pengaturan umum default
  - [x] factory untuk setiap model (dipakai saat testing)
- [x] RBAC dengan Gates & Policies sesuai matriks akses menu (RANCANGAN.md 10.4)
- [x] middleware/policy penolakan penulisan pada periode CLOSED
- [x] CRUD Chart of Accounts (hirarki parent, postable, akun terpakai hanya bisa dinonaktifkan)
- [x] CRUD Periode Akuntansi (buka/tutup periode)
- [x] Template Jurnal Otomatis (pemetaan jenis transaksi → akun debit/kredit)

## Fase 2 — UI
- [x] buat halaman login dan semua halaman sesuai dengan tema di folder /template_mockup
- [x] layout + sidebar menu sesuai struktur menu (RANCANGAN.md 10.1), menu tampil sesuai role
- [x] Dashboard: ringkasan saldo kas, transaksi menunggu approval, status periode aktif

## Fase 3 — Transaksi & Lampiran
- [x] form Penerimaan Kas, Pengeluaran Kas, Invoice Penjualan, Invoice Pembelian + daftar Semua Transaksi (filter jenis/status/periode/tanggal)
- [x] nomor transaksi otomatis & atomik (tabel sequence + `lockForUpdate()`)
- [x] validasi: tanggal di periode OPEN, nominal > 0, akun aktif, lampiran wajib di atas ambang nominal
- [x] upload lampiran: PDF/JPG/PNG maks 5 MB, nama acak + SHA-256, disimpan di `storage/app/private`, unduh via controller dengan otorisasi

## Fase 4 — Approval & Audit Log
- [x] state transition transaksi (DRAFT → PENDING_REVIEW → APPROVED/REJECTED → JOURNALED, CANCELLED) di Service class + Enum PHP
- [x] Inbox Pending Review (Approve / Reject dengan alasan wajib) + Riwayat Approval
- [x] aturan Maker ≠ Checker; opsional approval bertingkat berdasar nominal
- [x] audit log untuk semua perubahan status/data (siapa, kapan, aksi, nilai lama/baru)

## Fase 5 — Jurnal & General Ledger
- [x] jurnal otomatis dari transaksi APPROVED (pakai template) dan jurnal manual (indikator selisih debit–kredit real-time)
- [x] nomor referensi jurnal atomik (mis. `JV-2026-10-0001`)
- [x] validasi: debit = kredit, minimal 2 baris, akun aktif & postable, periode OPEN
- [x] `PostingService`: posting dalam `DB::transaction()` + `lockForUpdate()`, idempotent, update account_balances & gl_entries (saldo berjalan)
- [x] Posting Batch (tombol) + opsional Laravel Scheduler
- [x] Jurnal Pembalik (reversal); jurnal POSTED immutable
- [x] Buku Besar per Akun, Ringkasan Saldo Akun, Trial Balance

## Fase 6 — Pengaturan
- [x] Pengguna: CRUD, atur role, aktif/nonaktif, reset password
- [x] Audit Log (read-only, filter)
- [x] Pengaturan Umum: nama perusahaan, format nomor referensi, ambang nominal lampiran/approval, batas ukuran lampiran

## Fase 7 — Verifikasi
- [x] sesuaikan semua nya sesuai dengan rancangan.md (cek ulang poin Aturan Bisnis & Keamanan dan cakupan MVP)
- [x] setelah semua dikerjakan jalankan unit test dan buat laporan .md → [docs/LAPORAN_UNIT_TEST.md](docs/LAPORAN_UNIT_TEST.md)
  - [x] fokus: validasi debit = kredit, PostingService (saldo, idempotent, rollback), state transition, nomor sequence, Maker ≠ Checker, periode CLOSED
- [x] setelah itu lakukan functional test dan buat laporannya juga → [docs/LAPORAN_FUNCTIONAL_TEST.md](docs/LAPORAN_FUNCTIONAL_TEST.md)
  - [x] alur end-to-end: login per role → input transaksi + lampiran → submit → approve/reject → jurnal → posting → buku besar & trial balance
  - [x] uji hak akses per role (akses ditolak sesuai matriks)
- [x] perbaiki bug yang ditemukan, jalankan ulang test sampai lulus, perbarui laporan
- [x] centang Kriteria Penerimaan (RANCANGAN.md bagian 13)

## Fase 8 — Deploy
- [x] buat docker file untuk deploy _(file selesai; build image belum diuji karena Docker tidak terpasang di mesin dev)_
  - [x] multi-stage: build aset Vite di stage Node, image final PHP-FPM tanpa Node
  - [x] docker-compose: app (PHP-FPM) + web server (Nginx) + MySQL, volume untuk database dan `storage/app/private`
  - [x] entrypoint: `migrate --force`, `storage:link`, `config:cache route:cache view:cache`
  - [x] opsional: cron `schedule:run` untuk posting batch terjadwal
- [x] `.env.example` untuk production (`APP_ENV=production`, `APP_DEBUG=false`) + `.env.docker.example` untuk docker-compose
- [x] dokumentasi README: cara instal lokal, akun seeder, cara deploy, backup (`mysqldump` + folder lampiran)
